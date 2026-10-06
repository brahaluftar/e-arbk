<?php
declare(strict_types=1);

namespace App\Service;

use App\Support\Config;
use DomainException;
use PDO;
use Throwable;

final class UserManagementService
{
    public const ROLES = ['OFFICIAL' => 'Zyrtar', 'READ_ONLY' => 'Vetëm lexim', 'ADMIN' => 'Administrator'];

    public function __construct(private PDO $pdo, private AuditLogger $audit, private Config $config) {}

    /** @param array<string,mixed> $input */
    public function save(array $input, int $actorId): int
    {
        $text = static fn(string $key): string => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
        $id = filter_var($input['id'] ?? '0', FILTER_VALIDATE_INT);
        if ($id === false || $id < 0) throw new DomainException('Përdoruesi nuk është valid.');
        $name = $text('full_name');
        $email = $text('email');
        $role = $text('role_code');
        $active = $text('is_active');
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        if ($name === '' || mb_strlen($name) > 200) throw new DomainException('Emri dhe mbiemri janë të detyrueshëm, deri në 200 karaktere.');
        if (mb_strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) throw new DomainException('Shkruani një email të vlefshëm.');
        if (!array_key_exists($role, self::ROLES)) throw new DomainException('Roli nuk është valid.');
        if (!in_array($active, ['0', '1'], true)) throw new DomainException('Statusi nuk është valid.');
        if ($id === 0 || $password !== '') {
            if (mb_strlen($password) < 12 || strlen($password) > 72) throw new DomainException('Fjalëkalimi duhet të ketë të paktën 12 karaktere dhe jo më shumë se 72 bajte.');
            if (!is_string($input['password_confirmation'] ?? null) || $password !== $input['password_confirmation']) throw new DomainException('Fjalëkalimet nuk përputhen.');
        }
        $normalizedEmail = mb_strtoupper($email, 'UTF-8');
        $passwordHash = $password === '' ? null : password_hash($password, PASSWORD_DEFAULT);

        $this->pdo->beginTransaction();
        try {
            // Serialize account administration, including last-administrator checks.
            $accounts = $this->pdo->query('SELECT id,email,normalized_email,full_name,role_code,is_active,CONVERT(varchar(18),CAST(row_version AS varbinary(8)),1) version FROM dbo.app_users WITH(UPDLOCK,HOLDLOCK) ORDER BY id')->fetchAll();
            $actor = null;
            $previous = null;
            $activeAdmins = 0;
            foreach ($accounts as $account) {
                if ((int)$account['id'] === $actorId) $actor = $account;
                if ((int)$account['id'] === $id) $previous = $account;
                if ($account['role_code'] === 'ADMIN' && (int)$account['is_active'] === 1) $activeAdmins++;
                if ($account['normalized_email'] === $normalizedEmail && (int)$account['id'] !== $id) throw new DomainException('Ky email është përdorur nga një llogari tjetër.');
            }
            if ($actor === null || $actor['role_code'] !== 'ADMIN' || (int)$actor['is_active'] !== 1) throw new DomainException('Vetëm administratori aktiv mund të menaxhojë përdoruesit.');
            if ($id !== 0 && $previous === null) throw new DomainException('Përdoruesi nuk u gjet.');
            if ($previous !== null && !hash_equals((string)$previous['version'], $text('version'))) throw new DomainException('Llogaria është ndryshuar ndërkohë. Hapeni përsëri nga lista para se ta ruani.');
            if ($id === $actorId && ($role !== 'ADMIN' || $active !== '1')) throw new DomainException('Nuk mund ta çaktivizoni llogarinë tuaj ose t’ia hiqni rolin e administratorit.');
            if ($previous !== null && $previous['role_code'] === 'ADMIN' && (int)$previous['is_active'] === 1 && $activeAdmins <= 1 && ($role !== 'ADMIN' || $active !== '1')) throw new DomainException('Duhet të mbetet të paktën një administrator aktiv.');

            $values = ['email'=>$email, 'normalized'=>$normalizedEmail, 'name'=>$name, 'role'=>$role, 'active'=>(int)$active];
            if ($id === 0) {
                $statement = $this->pdo->prepare('INSERT dbo.app_users(email,normalized_email,full_name,role_code,is_active,password_hash) OUTPUT inserted.id VALUES(:email,:normalized,:name,:role,:active,:password)');
                $statement->execute($values + ['password'=>$passwordHash]);
                $id = (int)$statement->fetchColumn();
            } else {
                $passwordSql = $passwordHash === null ? '' : ',password_hash=:password';
                if ($passwordHash !== null) $values['password'] = $passwordHash;
                $statement = $this->pdo->prepare('UPDATE dbo.app_users SET email=:email,normalized_email=:normalized,full_name=:name,role_code=:role,is_active=:active'.$passwordSql.' WHERE id=:id');
                $statement->execute($values + ['id'=>$id]);
            }
            if ($passwordHash !== null) {
                $key = $this->config->string('APP_KEY') ?: 'development-only-rate-limit-key';
                foreach (array_unique([$normalizedEmail, (string)($previous['normalized_email'] ?? $normalizedEmail)]) as $address) {
                    $this->pdo->prepare("DELETE dbo.login_rate_limits WHERE action_code='login_email' AND dimension_hash=CONVERT(binary(32),:hash,2)")->execute(['hash'=>hash_hmac('sha256',$address,$key)]);
                }
            }
            $publicFields = array_flip(['email','full_name','role_code','is_active']);
            $this->audit->record($actorId, $previous === null ? 'USER_CREATED' : 'USER_UPDATED', 'APP_USER', (string)$id,
                $previous === null ? null : array_intersect_key($previous, $publicFields),
                ['email'=>$email,'full_name'=>$name,'role_code'=>$role,'is_active'=>(int)$active],
                ['password_changed'=>$passwordHash !== null]);
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
