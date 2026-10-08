<?php
declare(strict_types=1);

namespace App\Security;

use App\Service\AuditLogger;
use DomainException;
use PDO;
use Throwable;

final class BusinessRegistrationService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit) {}

    /** @return array{token:string,email:string} */
    public function invite(string $email,int $actorId,int $ttlHours=168): array
    {
        $email=trim($email);if(filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Emaili nuk është valid.');
        $token=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');$hash=hash('sha256',$token);
        $statement=$this->pdo->prepare('INSERT dbo.business_registration_invites(email,normalized_email,token_hash,expires_at,created_by_user_id) VALUES(:email,:normalized,CONVERT(binary(32),:hash,2),DATEADD(hour,:hours,SYSUTCDATETIME()),:actor)');
        $statement->execute(['email'=>$email,'normalized'=>mb_strtoupper($email,'UTF-8'),'hash'=>$hash,'hours'=>max(1,min(720,$ttlHours)),'actor'=>$actorId]);
        $this->audit->record($actorId,'BUSINESS_REGISTRATION_INVITED','APP_USER',null,null,['email'=>$email]);
        return ['token'=>$token,'email'=>$email];
    }

    /** @return array{email:string}|null */
    public function inspect(string $token,bool $markOpened=true): ?array
    {
        if(preg_match('/\A[A-Za-z0-9_-]{43}\z/',$token)!==1)return null;
        $statement=$this->pdo->prepare('SELECT id,email FROM dbo.business_registration_invites WHERE token_hash=CONVERT(binary(32),:hash,2) AND consumed_at IS NULL AND expires_at>SYSUTCDATETIME()');$statement->execute(['hash'=>hash('sha256',$token)]);$row=$statement->fetch();
        if(!is_array($row))return null;
        if($markOpened)$this->pdo->prepare('UPDATE dbo.business_registration_invites SET opened_at=COALESCE(opened_at,SYSUTCDATETIME()) WHERE id=:id')->execute(['id'=>$row['id']]);
        return ['email'=>(string)$row['email']];
    }

    public function register(string $token,string $name,string $password,string $confirmation): int
    {
        $name=trim($name);if($name===''||mb_strlen($name)>200)throw new DomainException('Emri është i detyrueshëm.');
        if(mb_strlen($password)<12||strlen($password)>72||!hash_equals($password,$confirmation))throw new DomainException('Fjalëkalimi duhet të ketë 12–72 karaktere dhe konfirmimi duhet të përputhet.');
        if(preg_match('/\A[A-Za-z0-9_-]{43}\z/',$token)!==1)throw new DomainException('Linku i regjistrimit nuk është valid.');
        $this->pdo->beginTransaction();
        try{
            $statement=$this->pdo->prepare('SELECT id,email,normalized_email FROM dbo.business_registration_invites WITH(UPDLOCK,HOLDLOCK) WHERE token_hash=CONVERT(binary(32),:hash,2) AND consumed_at IS NULL AND expires_at>SYSUTCDATETIME()');$statement->execute(['hash'=>hash('sha256',$token)]);$invite=$statement->fetch();if(!is_array($invite))throw new DomainException('Linku i regjistrimit ka skaduar ose është përdorur.');
            $duplicate=$this->pdo->prepare('SELECT 1 FROM dbo.app_users WHERE normalized_email=:email');$duplicate->execute(['email'=>$invite['normalized_email']]);if($duplicate->fetchColumn()!==false)throw new DomainException('Ekziston një llogari me këtë email. Përdorni reset password.');
            $insert=$this->pdo->prepare("INSERT dbo.app_users(email,normalized_email,password_hash,full_name,role_code,is_active) OUTPUT inserted.id VALUES(:email,:normalized,:password,:name,'BUSINESS',1)");
            $insert->execute(['email'=>$invite['email'],'normalized'=>$invite['normalized_email'],'password'=>password_hash($password,PASSWORD_DEFAULT),'name'=>$name]);$userId=(int)$insert->fetchColumn();
            $this->pdo->prepare('UPDATE dbo.business_registration_invites SET consumed_at=SYSUTCDATETIME(),consumed_by_user_id=:user WHERE id=:id')->execute(['user'=>$userId,'id'=>$invite['id']]);
            $this->audit->record($userId,'BUSINESS_USER_REGISTERED','APP_USER',(string)$userId,null,['email'=>$invite['email']]);
            $this->pdo->commit();return $userId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
