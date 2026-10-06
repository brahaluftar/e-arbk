<?php
declare(strict_types=1);

use App\Security\PasswordResetService;
use App\Service\AuditLogger;
use App\Service\GraphMailer;
use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class PasswordResetServiceTest extends TestCase
{
    private function service(PDO $pdo): PasswordResetService
    {
        $config = Config::load(sys_get_temp_dir() . '/arbk-reset-' . bin2hex(random_bytes(8)), ['APP_KEY' => 'test-key']);
        return new PasswordResetService($pdo, $config, new GraphMailer($config), new AuditLogger($pdo));
    }

    public function testInvalidTokenIsRejectedWithoutDatabaseAccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('prepare');
        self::assertFalse($this->service($pdo)->isValid('not-a-token'));
        self::assertFalse($this->service($pdo)->reset('not-a-token', 'long-enough-password', 'long-enough-password'));
    }

    public function testRejectsShortPasswordBeforeDatabaseAccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->reset(str_repeat('a', 43), 'short', 'short');
    }

    public function testRejectsPasswordMismatchBeforeDatabaseAccess(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->reset(str_repeat('a', 43), 'long-enough-password', 'different-password');
    }
}