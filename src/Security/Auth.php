<?php
declare(strict_types=1);

namespace App\Security;

use PDO;

final class Auth
{
    public function __construct(private PDO $pdo) {}

    /** @return array{id:int,email:string,full_name:string,role_code:string}|null */
    public function user(): ?array
    {
        $id = $_SESSION['_auth_user_id'] ?? null;
        if (!is_int($id) && !ctype_digit((string) $id)) return null;
        $statement = $this->pdo->prepare("SELECT id,email,full_name,role_code,auth_version FROM dbo.app_users WHERE id=:id AND is_active=1");
        $statement->execute(['id' => (int) $id]);
        $row = $statement->fetch();
        if (!is_array($row)) return null;
        if ((int) ($_SESSION['_auth_user_version'] ?? 0) !== (int) $row['auth_version']) {
            unset($_SESSION['_auth_user_id'], $_SESSION['_auth_user_version']);
            if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
            return null;
        }
        return ['id'=>(int)$row['id'],'email'=>(string)$row['email'],'full_name'=>(string)$row['full_name'],'role_code'=>(string)$row['role_code']];
    }

    public function login(string $email, string $password): bool
    {
        $statement = $this->pdo->prepare("SELECT id,password_hash,auth_version FROM dbo.app_users WHERE normalized_email=:email AND is_active=1");
        $statement->execute(['email' => mb_strtoupper(trim($email), 'UTF-8')]);
        $row = $statement->fetch();
        if (!is_array($row) || !password_verify($password, (string) $row['password_hash'])) return false;
        session_regenerate_id(true);
        $_SESSION['_auth_user_id'] = (int) $row['id'];
        $_SESSION['_auth_user_version'] = (int) $row['auth_version'];
        $this->pdo->prepare('UPDATE dbo.app_users SET last_login_at=SYSUTCDATETIME() WHERE id=:id')->execute(['id'=>(int)$row['id']]);
        return true;
    }

    public function logout(): void { $_SESSION = []; if (session_id() !== '') session_destroy(); }
    /** @param array{role_code:string} $user */
    public static function landingPath(array $user): string { return $user['role_code']==='BUSINESS' ? '/portal/index.php' : '/admin/dashboard.php'; }
    public function requireUser(): array { $user=$this->user(); if ($user===null) { header('Location: ' . app_url('/auth/login.php')); exit; } return $user; }
    /** @param list<string> $roles */
    public function requireRole(array $roles): array { $user=$this->requireUser(); if (!in_array($user['role_code'],$roles,true)) { http_response_code(403); exit('Access denied.'); } return $user; }
    public function requireBusiness(): array { return $this->requireRole(['BUSINESS']); }
}
