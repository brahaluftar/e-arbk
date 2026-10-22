<?php
declare(strict_types=1);
namespace App\Service;

use App\Billing\InvoiceFactory;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

/** Business units (branches): registration, editing and their own yearly invoices. */
final class BusinessUnitService
{
    private InvoiceFactory $factory;

    public function __construct(private PDO $pdo,private AuditLogger $audit,?InvoiceFactory $factory=null){$this->factory=$factory??new InvoiceFactory($pdo,$audit);}

    /** @param array<string,mixed> $input */
    public function create(int $businessId,array $input,int $actorId):int
    {
        $values=$this->values($input);
        $this->pdo->beginTransaction();
        try{
            $business=$this->pdo->prepare('SELECT 1 FROM dbo.ARBK_LIST WHERE REGULATION_ID=:id');$business->execute(['id'=>$businessId]);if($business->fetchColumn()===false)throw new DomainException('Biznesi nuk u gjet.');$business->closeCursor();
            $this->assertUniqueName($businessId,$values['name'],$values['address'],null);
            $q=$this->pdo->prepare('INSERT dbo.business_units(business_id,unit_name,address,phone_number,email,manager_name,nace_code,nace_description,annual_tariff,auto_invoice,is_active,created_by_user_id) OUTPUT inserted.id VALUES(:business,:name,:address,:phone,:email,:manager,:nace,:description,:tariff,:auto,:active,:actor)');
            $q->execute(['business'=>$businessId,'actor'=>$actorId]+$values);
            $id=(int)$q->fetchColumn();$q->closeCursor();
            $this->audit->record($actorId,'BUSINESS_UNIT_CREATED','BUSINESS_UNIT',(string)$id,null,['business_id'=>$businessId]+$values);
            $this->pdo->commit();return $id;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @param array<string,mixed> $input */
    public function update(int $unitId,int $businessId,array $input,int $actorId):void
    {
        $values=$this->values($input);
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT unit_name name,address,phone_number phone,email,manager_name manager,nace_code nace,nace_description description,annual_tariff tariff,auto_invoice auto,is_active active FROM dbo.business_units WITH(UPDLOCK,HOLDLOCK) WHERE id=:id AND business_id=:business');$q->execute(['id'=>$unitId,'business'=>$businessId]);$previous=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
            if(!is_array($previous))throw new DomainException('Njësia nuk u gjet.');
            $this->assertUniqueName($businessId,$values['name'],$values['address'],$unitId);
            $this->pdo->prepare('UPDATE dbo.business_units SET unit_name=:name,address=:address,phone_number=:phone,email=:email,manager_name=:manager,nace_code=:nace,nace_description=:description,annual_tariff=:tariff,auto_invoice=:auto,is_active=:active,updated_at=SYSUTCDATETIME() WHERE id=:id')->execute(['id'=>$unitId]+$values);
            $this->audit->record($actorId,'BUSINESS_UNIT_UPDATED','BUSINESS_UNIT',(string)$unitId,$previous,$values,['business_id'=>$businessId]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function generateInvoice(int $unitId,int $year,int $actorId,int $dueDays=30):int
    {
        if($year<2000||$year>2100)throw new DomainException('Viti fiskal nuk është valid.');
        $this->pdo->beginTransaction();
        try{
            $q=$this->pdo->prepare('SELECT u.*,a.Emri,a.NUMRI_FISKAL,a.NRBIZ FROM dbo.business_units u WITH(UPDLOCK,HOLDLOCK) JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=u.business_id WHERE u.id=:id AND u.is_active=1');$q->execute(['id'=>$unitId]);$u=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
            if(!is_array($u))throw new DomainException('Njësia nuk u gjet ose nuk është aktive.');
            if($u['annual_tariff']===null||(float)$u['annual_tariff']<=0)throw new DomainException('Njësia nuk ka tarifë vjetore.');
            $exists=$this->pdo->prepare('SELECT id FROM dbo.business_invoices WITH(UPDLOCK,HOLDLOCK) WHERE business_unit_id=:unit AND fiscal_year=:year');$exists->execute(['unit'=>$unitId,'year'=>$year]);
            if($exists->fetchColumn()!==false)throw new DomainException('Fatura e kësaj njësie për vitin '.$year.' ekziston.');$exists->closeCursor();
            $issued=new DateTimeImmutable('today');$amount=round((float)$u['annual_tariff'],2);
            $invoice=$this->factory->issue(['business_id'=>(int)$u['business_id'],'business_unit_id'=>$unitId,'fiscal_year'=>$year,'nace'=>(string)$u['nace_code'],'amount'=>$amount,'annual_tariff'=>$amount,
                'issued_on'=>$issued->format('Y-m-d'),'due_on'=>$issued->modify('+'.max(0,min(365,$dueDays)).' days')->format('Y-m-d'),'period_start'=>$year.'-01-01','period_end'=>$year.'-12-31','billed_months'=>12,
                'notes'=>'Faturë vjetore për njësinë '.$u['unit_name'],'business_name'=>$u['Emri'].' — '.$u['unit_name'],'business_address'=>$u['address'],'fiscal_number'=>$u['NUMRI_FISKAL'],'registration_number'=>$u['NRBIZ'],
                'registered_nace'=>$u['nace_code'],'tariff_nace'=>$u['nace_code'],'nace_description'=>$u['nace_description']],$actorId,'UNIT_INVOICE_CREATED',['unit_id'=>$unitId]);
            $this->pdo->commit();return $invoice['id'];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    /** @param array<string,mixed> $input @return array{name:string,address:string,phone:?string,email:?string,manager:?string,nace:string,description:?string,tariff:?float,auto:int,active:int} */
    private function values(array $input):array
    {
        $name=$this->required($input['unit_name']??null,'Emri i njësisë',255);
        $address=$this->required($input['address']??null,'Adresa',500);
        $phone=$this->optional($input['phone_number']??null,50,'Telefoni');
        $email=$this->optional($input['email']??null,254,'Emaili');if($email!==null&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Emaili i njësisë nuk është valid.');
        $manager=$this->optional($input['manager_name']??null,255,'Menaxheri');
        $nace=strtoupper($this->required($input['nace_code']??null,'Kodi NACE',4));
        // UNIREF needs exactly four characters, so units are validated the same way as invoices.
        if(preg_match('/\A[0-9A-Z]{4}\z/',$nace)!==1)throw new DomainException('Kodi NACE i njësisë duhet të ketë saktësisht 4 karaktere (p.sh. 4711).');
        $description=$this->optional($input['nace_description']??null,510,'Veprimtaria NACE');
        $rawTariff=trim(str_replace(',','.',(string)($input['annual_tariff']??'')));
        if($rawTariff!==''&&(!is_numeric($rawTariff)||(float)$rawTariff<=0))throw new DomainException('Tarifa vjetore duhet të jetë numër më i madh se zero.');
        $tariff=$rawTariff===''?null:round((float)$rawTariff,2);
        $auto=!empty($input['auto_invoice'])?1:0;
        if($auto===1&&$tariff===null)throw new DomainException('Tarifa vjetore kërkohet për faturim automatik.');
        $active=array_key_exists('is_active',$input)?((string)$input['is_active']==='0'?0:1):1;
        return ['name'=>$name,'address'=>$address,'phone'=>$phone,'email'=>$email,'manager'=>$manager,'nace'=>$nace,'description'=>$description,'tariff'=>$tariff,'auto'=>$auto,'active'=>$active];
    }

    private function assertUniqueName(int $businessId,string $name,string $address,?int $exceptId):void
    {
        $q=$this->pdo->prepare('SELECT id FROM dbo.business_units WHERE business_id=:business AND unit_name=:name AND address=:address');$q->execute(['business'=>$businessId,'name'=>$name,'address'=>$address]);$found=$q->fetchColumn();$q->closeCursor();
        if($found!==false&&(int)$found!==$exceptId)throw new DomainException('Ky biznes ka tashmë një njësi me këtë emër dhe adresë.');
    }

    private function required(mixed $value,string $label,int $limit):string{$v=trim((string)$value);if($v==='')throw new DomainException($label.' kërkohet.');if(mb_strlen($v)>$limit)throw new DomainException($label.' është shumë e gjatë (deri në '.$limit.' karaktere).');return $v;}
    private function optional(mixed $value,int $limit,string $label):?string{$v=trim((string)$value);if($v==='')return null;if(mb_strlen($v)>$limit)throw new DomainException($label.' është shumë e gjatë (deri në '.$limit.' karaktere).');return $v;}
}
