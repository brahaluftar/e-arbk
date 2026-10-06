<?php
declare(strict_types=1);

use App\Service\AuditLogger;
use App\Service\UserManagementService;
use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class UserManagementServiceTest extends TestCase
{
    private function input(): array
    {
        return ['id'=>'0','full_name'=>'Test Official','email'=>'official@example.test','role_code'=>'OFFICIAL','is_active'=>'1','password'=>'Test-password-123','password_confirmation'=>'Test-password-123'];
    }

    private function service(PDO $pdo): UserManagementService
    {
        return new UserManagementService($pdo,new AuditLogger($pdo),Config::load(sys_get_temp_dir().'/arbk-no-env-'.bin2hex(random_bytes(8)),['APP_KEY'=>'test-key']));
    }

    public function testRejectsInvalidInputBeforeAnyWrite(): void
    {
        foreach ([['email'=>'invalid'],['role_code'=>'UNKNOWN'],['full_name'=>''],['password'=>'short'],['password_confirmation'=>'different'],['is_active'=>'2'],['password'=>str_repeat('a',73)],['id'=>'-1']] as $invalid) {
            $pdo=$this->createMock(PDO::class);
            $pdo->expects(self::never())->method('beginTransaction');
            try { $this->service($pdo)->save(array_replace($this->input(),$invalid),1); self::fail('Invalid input accepted'); }
            catch (DomainException $e) { self::assertNotSame('',$e->getMessage()); }
        }
    }

    private function lockedService(array $accounts): UserManagementService
    {
        $pdo=$this->createMock(PDO::class);
        $statement=$this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn($accounts);
        $pdo->expects(self::once())->method('beginTransaction')->willReturn(true);
        $pdo->expects(self::once())->method('query')->willReturn($statement);
        $pdo->method('inTransaction')->willReturn(true);
        $pdo->expects(self::once())->method('rollBack')->willReturn(true);
        $pdo->expects(self::never())->method('prepare');
        $pdo->expects(self::never())->method('commit');
        return $this->service($pdo);
    }

    private function admin(): array
    {
        return ['id'=>1,'email'=>'admin@example.test','normalized_email'=>'ADMIN@EXAMPLE.TEST','full_name'=>'Admin','role_code'=>'ADMIN','is_active'=>1,'version'=>'0x0000000000000001'];
    }

    public function testRejectsDuplicateEmailRegardlessOfCase(): void
    {
        $service=$this->lockedService([$this->admin()]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Ky email është përdorur');
        $service->save(array_replace($this->input(),['email'=>'Admin@Example.Test']),1);
    }

    public function testOfficialCannotManageUsers(): void
    {
        $service=$this->lockedService([array_replace($this->admin(),['role_code'=>'OFFICIAL'])]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Vetëm administratori aktiv');
        $service->save($this->input(),1);
    }

    public function testAdministratorCannotDisableOwnAccount(): void
    {
        $service=$this->lockedService([$this->admin()]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Nuk mund ta çaktivizoni');
        $service->save(array_replace($this->input(),['id'=>'1','role_code'=>'ADMIN','is_active'=>'0','version'=>'0x0000000000000001']),1);
    }

    public function testStaleFormCannotOverwriteNewerAccountChanges(): void
    {
        $service=$this->lockedService([$this->admin()]);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Llogaria është ndryshuar ndërkohë');
        $service->save(array_replace($this->input(),['id'=>'1','role_code'=>'ADMIN','version'=>'0x0000000000000000']),1);
    }
}
