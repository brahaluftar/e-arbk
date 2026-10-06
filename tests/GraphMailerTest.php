<?php
declare(strict_types=1);

use App\Service\GraphMailer;
use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class GraphMailerTest extends TestCase
{
    public function testGetsAppTokenAndSendsEscapedResetEmail(): void
    {
        $config = Config::load(sys_get_temp_dir() . '/arbk-graph-' . bin2hex(random_bytes(8)), [
            'GRAPH_TENANT_ID' => 'tenant-id',
            'GRAPH_CLIENT_ID' => 'client-id',
            'GRAPH_CLIENT_SECRET' => 'client-secret',
            'GRAPH_SENDER_MAILBOX' => 'no-reply@example.test',
            'GRAPH_BASE_URL' => 'https://graph.microsoft.com/v1.0/',
            'GRAPH_TIMEOUT_SECONDS' => '15',
        ]);
        $requests = [];
        $mailer = new GraphMailer($config, static function (string $method, string $url, array $headers, string $body, int $timeout) use (&$requests): array {
            $requests[] = compact('method', 'url', 'headers', 'body', 'timeout');
            return count($requests) === 1 ? [200, '{"access_token":"test-access-token"}'] : [202, ''];
        });

        $mailer->sendPasswordReset('admin@example.test', '<Admin>', 'https://example.test/reset?token=abc');

        self::assertCount(2, $requests);
        self::assertSame('https://login.microsoftonline.com/tenant-id/oauth2/v2.0/token', $requests[0]['url']);
        self::assertStringContainsString('grant_type=client_credentials', $requests[0]['body']);
        self::assertSame('https://graph.microsoft.com/v1.0/users/no-reply%40example.test/sendMail', $requests[1]['url']);
        self::assertSame('Bearer test-access-token', $requests[1]['headers']['Authorization']);
        self::assertStringContainsString('&lt;Admin&gt;', $requests[1]['body']);
        self::assertStringContainsString('https://example.test/reset?token=abc', $requests[1]['body']);
    }

    public function testRejectsIncompleteGraphConfigurationBeforeMakingRequests(): void
    {
        $config = Config::load(sys_get_temp_dir() . '/arbk-graph-' . bin2hex(random_bytes(8)), []);
        $transport = static function (): array { self::fail('Transport must not run without credentials.'); };
        $mailer = new GraphMailer($config, Closure::fromCallable($transport));

        $this->expectException(RuntimeException::class);
        $mailer->sendPasswordReset('admin@example.test', 'Admin', 'https://example.test/reset');
    }

    public function testRejectsNonMicrosoftGraphHost(): void
    {
        $config = Config::load(sys_get_temp_dir() . '/arbk-graph-' . bin2hex(random_bytes(8)), [
            'GRAPH_TENANT_ID' => 'tenant-id',
            'GRAPH_CLIENT_ID' => 'client-id',
            'GRAPH_CLIENT_SECRET' => 'client-secret',
            'GRAPH_SENDER_MAILBOX' => 'no-reply@example.test',
            'GRAPH_BASE_URL' => 'https://attacker.example/v1.0',
            'GRAPH_TIMEOUT_SECONDS' => '15',
        ]);
        $transport = static function (): array { self::fail('Transport must not receive a Graph token at another host.'); };

        $this->expectException(RuntimeException::class);
        (new GraphMailer($config, Closure::fromCallable($transport)))->sendPasswordReset('admin@example.test', 'Admin', 'https://example.test/reset');
    }
}