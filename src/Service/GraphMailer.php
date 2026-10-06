<?php
declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use Closure;
use RuntimeException;

final class GraphMailer
{
    /** @param Closure(string,string,array<string,string>,string,int):array{int,string}|null $transport */
    public function __construct(private Config $config, private ?Closure $transport = null) {}

    public function sendPasswordReset(string $email, string $name, string $resetUrl): void
    {
        $tenant = $this->config->string('GRAPH_TENANT_ID');
        $client = $this->config->string('GRAPH_CLIENT_ID');
        $secret = $this->config->string('GRAPH_CLIENT_SECRET');
        $sender = $this->config->string('GRAPH_SENDER_MAILBOX');
        $baseUrl = rtrim($this->config->string('GRAPH_BASE_URL'), '/');
        $timeout = max(1, min(60, $this->config->int('GRAPH_TIMEOUT_SECONDS')));
        if ($tenant === '' || $client === '' || $secret === '' || filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Microsoft Graph mail configuration is incomplete.');
        }
        $graphHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if (parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' || $graphHost !== 'graph.microsoft.com' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Microsoft Graph mail configuration or recipient is invalid.');
        }

        [$status, $body] = $this->request(
            'POST',
            'https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query([
                'client_id' => $client,
                'client_secret' => $secret,
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]),
            $timeout
        );
        if ($status < 200 || $status >= 300) throw new RuntimeException('Microsoft identity token request failed (HTTP ' . $status . ').');
        $tokenData = json_decode($body, true);
        $accessToken = is_array($tokenData) && is_string($tokenData['access_token'] ?? null) ? $tokenData['access_token'] : '';
        if ($accessToken === '') throw new RuntimeException('Microsoft identity response did not contain an access token.');

        $greeting = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $message = [
            'message' => [
                'subject' => 'Rivendosja e fjalëkalimit - Tarifat e bizneseve',
                'body' => [
                    'contentType' => 'HTML',
                    'content' => '<p>Përshëndetje ' . $greeting . ',</p><p>Kërkuat të rivendosni fjalëkalimin. Përdorni lidhjen më poshtë brenda afatit të vlefshmërisë:</p><p><a href="' . $safeUrl . '">Rivendos fjalëkalimin</a></p><p>Nëse nuk e keni kërkuar këtë, mund ta shpërfillni këtë mesazh.</p>',
                ],
                'toRecipients' => [['emailAddress' => ['address' => $email]]],
            ],
            'saveToSentItems' => false,
        ];
        [$status] = $this->request(
            'POST',
            $baseUrl . '/users/' . rawurlencode($sender) . '/sendMail',
            ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json'],
            json_encode($message, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $timeout
        );
        if ($status < 200 || $status >= 300) throw new RuntimeException('Microsoft Graph sendMail failed (HTTP ' . $status . ').');
    }

    /** @param array<string,string> $headers @return array{int,string} */
    private function request(string $method, string $url, array $headers, string $body, int $timeout): array
    {
        if ($this->transport !== null) return ($this->transport)($method, $url, $headers, $body, $timeout);
        $headerLines = [];
        foreach ($headers as $name => $value) $headerLines[] = $name . ': ' . $value;
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $response = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('/\s([0-9]{3})\s/', $responseHeaders[0], $match) === 1) $status = (int) $match[1];
        if ($response === false && $status === 0) throw new RuntimeException('Microsoft Graph request could not be completed.');
        return [$status, $response === false ? '' : $response];
    }
}