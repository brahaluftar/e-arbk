<?php
declare(strict_types=1);

use App\Security\Auth;
use PHPUnit\Framework\TestCase;

final class AuthTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testUserSessionIsRevokedWhenAuthVersionChanges(): void
    {
        $_SESSION = ['_auth_user_id' => 17, '_auth_user_version' => 2];
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects(self::once())->method('execute')->with(['id' => 17]);
        $statement->method('fetch')->willReturn(['id' => 17, 'email' => 'admin@example.test', 'full_name' => 'Admin', 'role_code' => 'ADMIN', 'auth_version' => 3]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->willReturn($statement);

        self::assertNull((new Auth($pdo))->user());
        self::assertArrayNotHasKey('_auth_user_id', $_SESSION);
        self::assertArrayNotHasKey('_auth_user_version', $_SESSION);
    }

    public function testLegacySessionVersionZeroRemainsValidUntilReset(): void
    {
        $_SESSION = ['_auth_user_id' => 17];
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetch')->willReturn(['id' => 17, 'email' => 'admin@example.test', 'full_name' => 'Admin', 'role_code' => 'ADMIN', 'auth_version' => 0]);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($statement);

        self::assertSame(['id' => 17, 'email' => 'admin@example.test', 'full_name' => 'Admin', 'role_code' => 'ADMIN'], (new Auth($pdo))->user());
    }
}