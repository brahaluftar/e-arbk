<?php
declare(strict_types=1);
namespace App\Permitting;
use App\Document\DocumentStore;
use App\Repository\InvoiceRepository;
use App\Service\AuditLogger;
use App\Support\Config;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

/** Work permits (Leje e ushtrimit të veprimtarisë). A permit is issued only to an active business without unpaid debt. */
final class PermitService
{
    public function __construct(private PDO $pdo,private Config $config,private AuditLogger $audit,private DocumentStore $store){}

    /** Reason why a permit cannot be issued now, or null when it can. */
    public function blockingReason(int $businessId):?string
    {
        $statement=$this->pdo->prepare('SELECT status_code FROM dbo.v_business_billing WHERE business_id=:id');$statement->execute(['id'=>$businessId]);$status=$statement->fetchColumn();$statement->closeCursor();
        if($status===false)return 'Biznesi nuk u gjet.';
        if($status!=='ACTIVE')return 'Leja lëshohet vetëm për biznes aktiv.';
        $debt=(new InvoiceRepository($this->pdo))->debtOfBusiness($businessId);
        if($debt>0.004)return 'Leja nuk mund të lëshohet: biznesi ka borxh të papaguar prej '.number_format($debt,2,',','.').' €. Regjistroni pagesat dhe provoni përsëri.';
        return null;
    }

    public function issue(int $businessId,string $validFrom,string $validUntil,int $actorId):int
    {
        $from=DateTimeImmutable::createFromFormat('!Y-m-d',$validFrom);$until=DateTimeImmutable::createFromFormat('!Y-m-d',$validUntil);$today=new DateTimeImmutable('today');
        if(!$from||!$until||$from->format('Y-m-d')!==$validFrom||$until->format('Y-m-d')!==$validUntil||$until<$from||$from<$today)throw new DomainException('Vlefshmëria e lejes nuk është valide.');
        $reason=$this->blockingReason($businessId);if($reason!==null)throw new DomainException($reason);
        $statement=$this->pdo->prepare("SELECT a.REGULATION_ID,a.Emri,a.ADRESA,a.NACE_CODE_REG,a.NACEPERSHKRIMI,COALESCE(NULLIF(LTRIM(RTRIM(a.NACE_CODE_TARIFF)),''),a.NACE_CODE_REG) tariff_nace_code,COALESCE(x.nace_category,NULLIF(LTRIM(RTRIM(a.nace_veprimtaria_tariff)),''),n.Veprimtaria) nace_description FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL OUTER APPLY(SELECT TOP(1) Veprimtaria FROM dbo.NACE_LIST n WHERE LTRIM(RTRIM(n.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG)) ORDER BY TRY_CONVERT(decimal(18,2),n.Tarifa),n.NACErowGUID)n WHERE a.REGULATION_ID=:id");$statement->execute(['id'=>$businessId]);$business=$statement->fetch();$statement->closeCursor();if(!is_array($business))throw new DomainException('Biznesi nuk u gjet.');
        $token=(new PublicPermitToken())->issue();$serial='LP-'.date('Y').'-'.strtoupper(bin2hex(random_bytes(4)));$url=rtrim($this->config->string('APP_URL'),'/').'/verify-permit.php?token='.rawurlencode($token['token']);$issued=$today->format('Y-m-d');
        $data=['serial_number'=>$serial,'business_name_snapshot'=>$business['Emri'],'business_address_snapshot'=>$business['ADRESA'],'registered_nace_code_snapshot'=>$business['NACE_CODE_REG'],'registered_nace_activity_snapshot'=>$business['NACEPERSHKRIMI'],'tariff_nace_code_snapshot'=>$business['tariff_nace_code'],'nace_description_snapshot'=>$business['nace_description'],'issued_on'=>$issued,'valid_from'=>$validFrom,'valid_until'=>$validUntil,'verification_url'=>$url];
        $pdf=(new PermitPdfRenderer($this->config))->render($data,(new VerificationQrRenderer())->render($url));$key=$this->store->put('permits',$pdf);
        $this->pdo->beginTransaction();
        try{
            // Re-check the debt inside the transaction so a concurrent invoice cannot slip through.
            $reason=$this->blockingReason($businessId);if($reason!==null)throw new DomainException($reason);
            $insert=$this->pdo->prepare("INSERT dbo.business_permits(business_id,serial_number,issued_on,valid_from,valid_until,business_name_snapshot,business_address_snapshot,registered_nace_code_snapshot,registered_nace_activity_snapshot,tariff_nace_code_snapshot,nace_description_snapshot,public_selector,public_secret_hash,pdf_storage_key,issued_by_user_id) OUTPUT inserted.id VALUES(:business,:serial,:issued,:from,:until,:name,:address,:registered_code,:registered_activity,:tariff_code,:description,:selector,CONVERT(binary(32),:hash,2),:pdf,:actor)");
            $insert->execute(['business'=>$businessId,'serial'=>$serial,'issued'=>$issued,'from'=>$validFrom,'until'=>$validUntil,'name'=>$business['Emri'],'address'=>$business['ADRESA'],'registered_code'=>$business['NACE_CODE_REG'],'registered_activity'=>$business['NACEPERSHKRIMI'],'tariff_code'=>$business['tariff_nace_code'],'description'=>$business['nace_description'],'selector'=>$token['selector'],'hash'=>$token['hash'],'pdf'=>$key,'actor'=>$actorId]);$id=(int)$insert->fetchColumn();$insert->closeCursor();
            $this->audit->record($actorId,'WORK_PERMIT_ISSUED','BUSINESS_PERMIT',(string)$id,null,['business_id'=>$businessId,'serial_number'=>$serial]);
            $this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();$this->store->delete($key);throw $e;}
    }

    /** Revokes an active permit; public verification then shows it as not valid. */
    public function revoke(int $permitId,string $reason,int $actorId):int
    {
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>500)throw new DomainException('Shënoni arsyen e revokimit (deri në 500 karaktere).');
        $this->pdo->beginTransaction();
        try{
            $statement=$this->pdo->prepare("UPDATE dbo.business_permits SET status_code='REVOKED',revoked_at=SYSUTCDATETIME(),revoked_by_user_id=:actor,revocation_reason=:reason OUTPUT inserted.business_id WHERE id=:id AND status_code='ACTIVE'");
            $statement->execute(['actor'=>$actorId,'reason'=>$reason,'id'=>$permitId]);$businessId=$statement->fetchColumn();$statement->closeCursor();
            if($businessId===false)throw new DomainException('Leja aktive nuk u gjet.');
            $this->audit->record($actorId,'WORK_PERMIT_REVOKED','BUSINESS_PERMIT',(string)$permitId,['status'=>'ACTIVE'],['status'=>'REVOKED'],['reason'=>$reason]);
            $this->pdo->commit();return (int)$businessId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
}
