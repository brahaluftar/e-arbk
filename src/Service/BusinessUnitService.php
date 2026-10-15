<?php
declare(strict_types=1);
namespace App\Service;

use App\Billing\UnirefSequence;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class BusinessUnitService
{
    public function __construct(private PDO $pdo,private AuditLogger $audit){}

    /** @param array<string,mixed> $input */
    public function create(int $businessId,array $input,int $actorId):int
    {
        $name=$this->required($input['unit_name']??null,'Emri i njësisë',255);
        $address=$this->required($input['address']??null,'Adresa',500);
        $phone=$this->optional($input['phone_number']??null,50);
        $email=$this->optional($input['email']??null,254);if($email!==null&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Emaili i njësisë nuk është valid.');
        $manager=$this->optional($input['manager_name']??null,255);
        $nace=strtoupper($this->required($input['nace_code']??null,'Kodi NACE',20));
        if(preg_match('/^[0-9A-Z]{4,20}$/',$nace)!==1)throw new DomainException('Kodi NACE nuk është valid.');
        $description=$this->optional($input['nace_description']??null,510);
        $rawTariff=trim(str_replace(',','.',(string)($input['annual_tariff']??'')));
        $tariff=$rawTariff===''?null:(is_numeric($rawTariff)?round((float)$rawTariff,2):-1);
        if($tariff!==null&&$tariff<=0)throw new DomainException('Tarifa vjetore duhet të jetë më e madhe se zero.');
        $auto=isset($input['auto_invoice'])?1:0;
        if($auto===1&&$tariff===null)throw new DomainException('Tarifa vjetore kërkohet për faturim automatik.');
        $q=$this->pdo->prepare('INSERT dbo.business_units(business_id,unit_name,address,phone_number,email,manager_name,nace_code,nace_description,annual_tariff,auto_invoice,created_by_user_id) OUTPUT inserted.id VALUES(:business,:name,:address,:phone,:email,:manager,:nace,:description,:tariff,:auto,:actor)');
        $q->execute(['business'=>$businessId,'name'=>$name,'address'=>$address,'phone'=>$phone,'email'=>$email,'manager'=>$manager,'nace'=>$nace,'description'=>$description,'tariff'=>$tariff,'auto'=>$auto,'actor'=>$actorId]);
        $id=(int)$q->fetchColumn();$q->closeCursor();
        $this->audit->record($actorId,'BUSINESS_UNIT_CREATED','BUSINESS_UNIT',(string)$id,null,['business_id'=>$businessId,'nace_code'=>$nace,'auto_invoice'=>$auto]);
        return $id;
    }

    /** @param array<string,mixed> $input */
    public function updateContacts(int $unitId,int $businessId,array $input,int $actorId):void
    {
        $address=$this->required($input['address']??null,'Adresa',500);$phone=$this->optional($input['phone_number']??null,50);$email=$this->optional($input['email']??null,254);$manager=$this->optional($input['manager_name']??null,255);
        if($email!==null&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Emaili i njësisë nuk është valid.');
        $q=$this->pdo->prepare('UPDATE dbo.business_units SET address=:address,phone_number=:phone,email=:email,manager_name=:manager,updated_at=SYSUTCDATETIME() WHERE id=:id AND business_id=:business');$q->execute(['address'=>$address,'phone'=>$phone,'email'=>$email,'manager'=>$manager,'id'=>$unitId,'business'=>$businessId]);if($q->rowCount()!==1)throw new DomainException('Njësia nuk u gjet.');
        $this->audit->record($actorId,'BUSINESS_UNIT_CONTACTS_UPDATED','BUSINESS_UNIT',(string)$unitId,null,['business_id'=>$businessId]);
    }

    public function generateInvoice(int $unitId,int $year,int $actorId,int $dueDays=30):int
    {
        if($year<2000||$year>2100)throw new DomainException('Viti fiskal nuk është valid.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT u.*,a.Emri,a.NUMRI_FISKAL,a.NRBIZ FROM dbo.business_units u JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=u.business_id WHERE u.id=:id AND u.is_active=1');$q->execute(['id'=>$unitId]);$u=$q->fetch();$q->closeCursor();
            if(!is_array($u))throw new DomainException('Njësia nuk u gjet ose nuk është aktive.');
            if($u['annual_tariff']===null||(float)$u['annual_tariff']<=0)throw new DomainException('Njësia nuk ka tarifë vjetore.');
            $exists=$this->pdo->prepare('SELECT id FROM dbo.business_invoices WITH(UPDLOCK,HOLDLOCK) WHERE business_unit_id=:unit AND fiscal_year=:year');$exists->execute(['unit'=>$unitId,'year'=>$year]);
            if($exists->fetchColumn()!==false)throw new DomainException('Fatura e kësaj njësie për këtë vit ekziston.');$exists->closeCursor();
            $nace=strtoupper(trim((string)$u['nace_code']));if(preg_match('/^[0-9A-Z]{4}$/',$nace)!==1)throw new DomainException('Kodi NACE duhet të ketë 4 karaktere për UNIREF.');
            $issued=(new DateTimeImmutable())->format('Y-m-d');$uniref=(new UnirefSequence($this->pdo))->next($nace);$amount=round((float)$u['annual_tariff'],2);
            $insert=$this->pdo->prepare("INSERT dbo.business_invoices(business_id,business_unit_id,fiscal_year,invoice_number,uniref,amount,issued_on,due_on,notes,created_by_user_id,status_code,period_start,period_end,billed_months,annual_tariff,currency,business_name_snapshot,business_address_snapshot,fiscal_number_snapshot,registration_number_snapshot,registered_nace_code_snapshot,tariff_nace_code_snapshot,nace_description_snapshot) OUTPUT inserted.id VALUES(:business,:unit,:year,:number,:uniref,:amount,:issued,DATEADD(day,:due,:issued),:notes,:actor,'ISSUED',DATEFROMPARTS(:year2,1,1),DATEFROMPARTS(:year3,12,31),12,:annual,'EUR',:name,:address,:fiscal,:registration,:nace,:nace2,:description)");
            $insert->execute(['business'=>$u['business_id'],'unit'=>$unitId,'year'=>$year,'number'=>$uniref,'uniref'=>$uniref,'amount'=>$amount,'issued'=>$issued,'due'=>max(0,min(365,$dueDays)),'notes'=>'Faturë vjetore për njësinë '.$u['unit_name'],'actor'=>$actorId,'year2'=>$year,'year3'=>$year,'annual'=>$amount,'name'=>$u['Emri'].' — '.$u['unit_name'],'address'=>$u['address'],'fiscal'=>$u['NUMRI_FISKAL'],'registration'=>$u['NRBIZ'],'nace'=>$nace,'nace2'=>$nace,'description'=>$u['nace_description']]);
            $invoiceId=(int)$insert->fetchColumn();$insert->closeCursor();$this->audit->record($actorId,'UNIT_INVOICE_CREATED','BUSINESS_INVOICE',(string)$invoiceId,null,['unit_id'=>$unitId,'amount'=>$amount,'year'=>$year]);$this->pdo->commit();return $invoiceId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @return array{created:int,skipped:int} */
    public function generateAutomatic(int $year,int $actorId,int $dueDays=30):array
    {
        $q=$this->pdo->prepare('SELECT id FROM dbo.business_units u WHERE auto_invoice=1 AND is_active=1 AND annual_tariff>0 AND NOT EXISTS(SELECT 1 FROM dbo.business_invoices i WHERE i.business_unit_id=u.id AND i.fiscal_year=:year) ORDER BY id');$q->execute(['year'=>$year]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$q->closeCursor();$created=0;$skipped=0;
        foreach($ids as $id){try{$this->generateInvoice($id,$year,$actorId,$dueDays);$created++;}catch(DomainException){$skipped++;}}
        return ['created'=>$created,'skipped'=>$skipped];
    }

    private function required(mixed $value,string $label,int $limit):string{$v=trim((string)$value);if($v==='')throw new DomainException($label.' kërkohet.');if(mb_strlen($v)>$limit)throw new DomainException($label.' është shumë e gjatë.');return $v;}
    private function optional(mixed $value,int $limit):?string{$v=trim((string)$value);if($v==='')return null;if(mb_strlen($v)>$limit)throw new DomainException('Një fushë është më e gjatë se kufiri i lejuar.');return $v;}
}
