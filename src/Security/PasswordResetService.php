<?php
declare(strict_types=1);

namespace App\Security;

use App\Service\AuditLogger;
use App\Service\GraphMailer;
use App\Support\Config;
use DomainException;
use PDO;
use Throwable;

final class PasswordResetService
{
    public function __construct(private PDO $pdo, private Config $config, private GraphMailer $mailer, private AuditLogger $audit) {}

    /** @return array{email:string,name:string,url:string,hash:string}|null */
    public function prepareRequest(string $email, string $ip): ?array
    {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || !$this->passwordResetAllowed($email, $ip)) return null;
        $normalizedEmail = mb_strtoupper($email, 'UTF-8');
        $statement = $this->pdo->prepare('SELECT id,email,full_name FROM dbo.app_users WHERE normalized_email=:email AND is_active=1');
        $statement->execute(['email' => $normalizedEmail]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) return null;

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $tokenHash = hash('sha256', $token);
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT id FROM dbo.app_users WITH(UPDLOCK,HOLDLOCK) WHERE id=:user AND is_active=1');
            $lock->execute(['user' => (int) $user['id']]);
            if ($lock->fetchColumn() === false) {
                $this->pdo->commit();
                return null;
            }
            $this->pdo->prepare('UPDATE dbo.password_reset_tokens SET consumed_at=SYSUTCDATETIME() WHERE user_id=:user AND consumed_at IS NULL')->execute(['user' => (int) $user['id']]);
            $this->pdo->prepare('INSERT dbo.password_reset_tokens(user_id,token_hash,expires_at) VALUES(:user,CONVERT(binary(32),:hash,2),DATEADD(second,CONVERT(int,:ttl),SYSUTCDATETIME()))')->execute([
                'user' => (int) $user['id'],
                'hash' => $tokenHash,
                'ttl' => max(60, min(86400, $this->config->int('PASSWORD_RESET_TTL_SECONDS'))),
            ]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $resetUrl = rtrim($this->config->string('APP_URL'), '/') . '/auth/reset-password-confirm.php?token=' . rawurlencode($token);
        return ['email' => (string) $user['email'], 'name' => (string) $user['full_name'], 'url' => $resetUrl, 'hash' => $tokenHash];
    }

    /** @param array{email:string,name:string,url:string,hash:string} $delivery */
    public function sendPrepared(array $delivery): void
    {
        try {
            $this->mailer->sendPasswordReset($delivery['email'], $delivery['name'], $delivery['url']);
        } catch (Throwable $error) {
            $this->pdo->prepare('UPDATE dbo.password_reset_tokens SET consumed_at=SYSUTCDATETIME() WHERE token_hash=CONVERT(binary(32),:hash,2) AND consumed_at IS NULL')->execute(['hash' => $delivery['hash']]);
            throw $error;
        }
    }

    public function isValid(string $token): bool
    {
        if (!$this->validTokenFormat($token)) return false;
        $statement = $this->pdo->prepare('SELECT 1 FROM dbo.password_reset_tokens t JOIN dbo.app_users u ON u.id=t.user_id WHERE t.token_hash=CONVERT(binary(32),:hash,2) AND t.consumed_at IS NULL AND t.expires_at>SYSUTCDATETIME() AND u.is_active=1');
        $statement->execute(['hash' => hash('sha256', $token)]);
        return $statement->fetchColumn() !== false;
    }

    public function reset(string $token, string $password, string $confirmation): bool
    {
        if (mb_strlen($password) < 12 || strlen($password) > 72) throw new DomainException('Fjalëkalimi duhet të ketë të paktën 12 karaktere dhe jo më shumë se 72 bajte.');
        if (!hash_equals($password, $confirmation)) throw new DomainException('Fjalëkalimet nuk përputhen.');
        if (!$this->validTokenFormat($token)) return false;

        $this->pdo->beginTransaction();
        try {
            $lookup = $this->pdo->prepare('SELECT user_id FROM dbo.password_reset_tokens WHERE token_hash=CONVERT(binary(32),:hash,2) AND consumed_at IS NULL AND expires_at>SYSUTCDATETIME()');
            $lookup->execute(['hash' => hash('sha256', $token)]);
            $userId = $lookup->fetchColumn();
            if ($userId === false) {
                $this->pdo->rollBack();
                return false;
            }
            $userStatement = $this->pdo->prepare('SELECT id,email FROM dbo.app_users WITH(UPDLOCK,HOLDLOCK) WHERE id=:user AND is_active=1');
            $userStatement->execute(['user' => (int) $userId]);
            $user = $userStatement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $this->pdo->rollBack();
                return false;
            }
            $statement = $this->pdo->prepare('SELECT id FROM dbo.password_reset_tokens WITH(UPDLOCK,HOLDLOCK) WHERE user_id=:user AND token_hash=CONVERT(binary(32),:hash,2) AND consumed_at IS NULL AND expires_at>SYSUTCDATETIME()');
            $statement->execute(['user' => (int) $user['id'], 'hash' => hash('sha256', $token)]);
            if ($statement->fetchColumn() === false) {
                $this->pdo->rollBack();
                return false;
            }
            $userId = (int) $user['id'];
            $this->pdo->prepare('UPDATE dbo.app_users SET password_hash=:password,auth_version=auth_version+1 WHERE id=:id')->execute(['password' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
            $this->pdo->prepare('UPDATE dbo.password_reset_tokens SET consumed_at=SYSUTCDATETIME() WHERE user_id=:user AND consumed_at IS NULL')->execute(['user' => $userId]);
            (new LoginRateLimiter($this->pdo, $this->config))->clearEmail((string) $user['email']);
            $this->audit->record(null, 'PASSWORD_RESET', 'APP_USER', (string) $userId, null, null);
            $this->pdo->commit();
            return true;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    private function validTokenFormat(string $token): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
    }

    private function passwordResetAllowed(string $email, string $ip): bool
    {
        return (new LoginRateLimiter($this->pdo, $this->config, 'reset'))->attempt($email, $ip);
    }
}
