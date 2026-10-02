<?php
declare(strict_types=1);

namespace App\Support;

final class Config
{
    /** @param array<string,string> $values */
    private function __construct(private array $values) {}

    /** @param array<string,string> $defaults */
    public static function load(string $root, array $defaults): self
    {
        $values = $defaults;
        $file = $root . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                if (preg_match('/\A[A-Z][A-Z0-9_]*\z/', $key) === 1) $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
        foreach (array_keys($values) as $key) {
            $environment = getenv($key);
            if ($environment !== false) $values[$key] = $environment;
        }
        return new self($values);
    }

    public function string(string $key): string { return $this->values[$key] ?? ''; }
    public function int(string $key): int { return (int) $this->string($key); }
    public function bool(string $key): bool { return filter_var($this->string($key), FILTER_VALIDATE_BOOL); }
    public function basePath(): string { return rtrim((string) parse_url($this->string('APP_URL'), PHP_URL_PATH), '/'); }
}
