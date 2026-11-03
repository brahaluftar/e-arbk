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
            'MUNICIPALITY_NAME' => 'Komuna e Prishtinës',
            'MUNICIPALITY_ADDRESS' => 'Rruga UÇK 2, Prishtinë',
            'MUNICIPALITY_CONTACT' => 'info@prishtinaonline.com',
            'MUNICIPAL_BANK_ACCOUNT' => '1000000000000000',
        ];
    }

    public function testAcceptsCompleteGraphConfiguration(): void
    {
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $this->validProductionValues());
        self::assertSame([], ProductionValidator::errors($config));
        self::assertSame([], ProductionValidator::warnings($config));
    }

    public function testMissingGraphSecretIsOnlyAWarning(): void
    {
        $values = $this->validProductionValues();
        $values['GRAPH_CLIENT_SECRET'] = 'REPLACE_WITH_MICROSOFT_ENTRA_CLIENT_SECRET';
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $values);
        self::assertSame([], ProductionValidator::errors($config));
        self::assertContains('GRAPH_CLIENT_SECRET is not set: password reset and invoice emails cannot be sent.', ProductionValidator::warnings($config));
    }

    public function testMissingDocumentSettingsAreWarningsNotErrors(): void
    {
        $values = $this->validProductionValues();
        $values['MUNICIPALITY_ADDRESS'] = '';
        $values['MUNICIPAL_BANK_ACCOUNT'] = 'PENDING';
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $values);
        self::assertSame([], ProductionValidator::errors($config));
        self::assertSame([
            'MUNICIPALITY_ADDRESS is not set: invoices and permits print it blank.',
            'MUNICIPAL_BANK_ACCOUNT is not set: invoices and permits print it blank.',
        ], ProductionValidator::warnings($config));
    }

    public function testRejectsUntrustedGraphHost(): void
    {
        $values = $this->validProductionValues();
        $values['GRAPH_BASE_URL'] = 'https://attacker.example/v1.0';
        $config = Config::load(sys_get_temp_dir() . '/arbk-production-' . bin2hex(random_bytes(8)), $values);
        self::assertContains('GRAPH_BASE_URL must use https://graph.microsoft.com.', ProductionValidator::errors($config));
    }
}
