<?php
declare(strict_types=1);

namespace App\Security;

final class Csrf
{
    public function __construct(private int $ttl) {}
    public function token(): string
    {
        $record = $_SESSION['_csrf'] ?? null;
        if (!is_array($record) || time() - (int) ($record['issued'] ?? 0) > $this->ttl) {
            $record = ['value' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='), 'issued' => time()];
            $_SESSION['_csrf'] = $record;
        }
        return (string) $record['value'];
    }
    public function verify(?string $value): bool
    {
        $record = $_SESSION['_csrf'] ?? null;
        return is_string($value) && is_array($record) && time() - (int) ($record['issued'] ?? 0) <= $this->ttl && hash_equals((string) ($record['value'] ?? ''), $value);
    }
}
