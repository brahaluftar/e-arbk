<?php
declare(strict_types=1);

use App\Support\Config;
use App\Support\ProductionValidator;
use PHPUnit\Framework\TestCase;

final class ProductionValidatorTest extends TestCase
{
    private function validProductionValues(): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_URL' => 'https://arbk.kryeqyteti.net',
            'APP_KEY' => str_repeat('k', 48),
            'FORCE_HTTPS' => 'true',
            'DB_TRUSTED_CONNECTION' => 'false',
            'DB_HOST' => 'sql.example.test,1433',
            'DB_NAME' => 'ARBK',
            'DB_USER' => 'arbk',
            'DB_PASSWORD' => 'test-db-password',
            'DB_ENCRYPT' => 'true',
            'APP_DEBUG' => 'false',
            'BACKUP_MODE' => 'managed',
            'GRAPH_TENANT_ID' => 'tenant-id',
            'GRAPH_CLIENT_ID' => 'client-id',
            'GRAPH_CLIENT_SECRET' => 'client-secret',
            'GRAPH_SENDER_MAILBOX' => 'no-reply@kryeqyteti.net',
            'GRAPH_BASE_URL' => 'https://graph.microsoft.com/v1.0',
            'GRAPH_TIMEOUT_SECONDS' => '15',
            'PASSWORD_RESET_TTL_SECONDS' => '1800',
        ];
    }

    public function testAcceptsCompleteGraphConfiguration(): void
    {
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $this->validProductionValues());
        self::assertSame([], ProductionValidator::errors($config));
    }

    public function testRejectsMissingGraphSecret(): void
    {
        $values = $this->validProductionValues();
        $values['GRAPH_CLIENT_SECRET'] = 'REPLACE_WITH_MICROSOFT_ENTRA_CLIENT_SECRET';
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $values);
        self::assertContains('GRAPH_CLIENT_SECRET is required for password reset email.', ProductionValidator::errors($config));
    }

    public function testRejectsUntrustedGraphHost(): void
    {
        $values = $this->validProductionValues();
        $values['GRAPH_BASE_URL'] = 'https://attacker.example/v1.0';
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $values);
        self::assertContains('GRAPH_BASE_URL must use https://graph.microsoft.com.', ProductionValidator::errors($config));
    }
}