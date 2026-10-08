<?php
declare(strict_types=1);
namespace App\Service;

use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class BusinessEditService
{
    private const TEXT=[
        'NRBIZ'=>20,'Emri'=>255,'EMRI_TREGTAR'=>510,'Lloji'=>50,'NACE_CODE_REG'=>20,
        'NACEPERSHKRIMI'=>255,'SEKTORI'=>510,'MADHESIA'=>40,'Qyteti'=>100,'Statusi'=>40,
        'Viti'=>4,'MUAJI'=>20,'ATK_DATEMBYLLJE'=>20,'NACE_CODE_TARIFF'=>10,
        'nace_veprimtaria_tariff'=>255,'NUMRI_FISKAL'=>30,'ADRESA'=>500,'EMAIL'=>254,
    ];
    private const INTEGERS=['NR_PUNETOREVE','TOTAL_M','TOTAL_F'];
    private const BITS=['Pasiv','ATK_MBYLLUR','pronare_grua','pronar_veteran'];
    private const DATES=['date_pasivizimit','DATA_SHUARJES'];
    private const DECIMALS=['pronesia_grua','perqindja_veteran','NACE_REG_TARIFF','tarifa_me_lirim'];

    public function __construct(private PDO $pdo,private AuditLogger $audit){}

    /** @param array<string,mixed> $input */
    public function update(int $businessId,array $input,int $userId):void
    {
        $values=[];
        foreach(self::TEXT as $field=>$limit){$value=$this->nullable($input[$field]??null);if($value!==null&&mb_strlen($value)>$limit)throw new DomainException("Fusha $field është më e gjatë se kufiri $limit.");$values[$field]=$value;}
        if($values['EMAIL']!==null&&filter_var($values['EMAIL'],FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Emaili i biznesit nuk është valid.');
        foreach(self::INTEGERS as $field){$raw=$this->nullable($input[$field]??null);if($raw!==null&&filter_var($raw,FILTER_VALIDATE_INT)===false)throw new DomainException("Fusha $field duhet të jetë numër i plotë.");$value=$raw===null?null:(int)$raw;if($value!==null&&$value<0)throw new DomainException("Fusha $field nuk mund të jetë negative.");$values[$field]=$value;}
        foreach(self::BITS as $field){$raw=$this->nullable($input[$field]??null);if($raw!==null&&!in_array($raw,['0','1'],true))throw new DomainException("Fusha $field nuk është valide.");$values[$field]=$raw===null?null:(int)$raw;}
        foreach(self::DATES as $field){$raw=$this->nullable($input[$field]??null);if($raw!==null&&!$this->validDate($raw))throw new DomainException("Data në fushën $field nuk është valide.");$values[$field]=$raw;}
        foreach(self::DECIMALS as $field){$raw=$this->nullable($input[$field]??null);$raw=$raw===null?null:str_replace(',','.',$raw);if($raw!==null&&!is_numeric($raw))throw new DomainException("Fusha $field duhet të jetë numerike.");$value=$raw===null?null:(float)$raw;if(in_array($field,['pronesia_grua','perqindja_veteran'],true)&&$value!==null&&($value<0||$value>100))throw new DomainException("Fusha $field duhet të jetë ndërmjet 0 dhe 100.");if(in_array($field,['NACE_REG_TARIFF','tarifa_me_lirim'],true)&&$value!==null&&$value<0)throw new DomainException("Fusha $field nuk mund të jetë negative.");$values[$field]=$value;}

        if($values['Statusi']!==null&&preg_match('/^Pasiv\s*-\s*(\d{2}\/\d{2}\/\d{4})$/iu',$values['Statusi'],$match)===1){$date=DateTimeImmutable::createFromFormat('!d/m/Y',$match[1]);if($date===false)throw new DomainException('Data e pasivizimit në status nuk është valide.');$values['Statusi']='Pasiv';$values['Pasiv']=1;$values['date_pasivizimit']=$date->format('Y-m-d');}
        if($values['Pasiv']===1){$values['ATK_MBYLLUR']=1;$values['Statusi']='Pasiv';}
        if($values['Pasiv']===0)$values['date_pasivizimit']=null;
        if($values['pronare_grua']!==1)$values['pronesia_grua']=null;
        if($values['pronar_veteran']!==1)$values['perqindja_veteran']=null;

        $this->pdo->beginTransaction();
        try{
            $statement=$this->pdo->prepare('SELECT * FROM dbo.ARBK_LIST WITH(UPDLOCK,ROWLOCK) WHERE REGULATION_ID=:id');$statement->execute(['id'=>$businessId]);$previous=$statement->fetch();if(!is_array($previous))throw new DomainException('Biznesi nuk u gjet.');
            $assignments=[];foreach(array_keys($values) as $field)$assignments[]="[$field]=:$field";
            $naceChanged=trim((string)($previous['NACE_CODE_REG']??''))!==trim((string)($values['NACE_CODE_REG']??''));
            if($naceChanged){$values['NACE_CODE_TARIFF']=null;$values['nace_veprimtaria_tariff']=null;$values['NACE_REG_TARIFF']=null;$values['tarifa_me_lirim']=null;}
            $values['id']=$businessId;$this->pdo->prepare('UPDATE dbo.ARBK_LIST SET '.implode(',',$assignments).',import_updated_at=SYSUTCDATETIME() WHERE REGULATION_ID=:id')->execute($values);
            if($naceChanged)$this->pdo->prepare("UPDATE dbo.business_nace_assignments SET ended_at=SYSUTCDATETIME(),end_reason=N'Registered NACE code changed by manual edit' WHERE business_id=:id AND ended_at IS NULL")->execute(['id'=>$businessId]);
            $current=$this->pdo->prepare('SELECT * FROM dbo.ARBK_LIST WHERE REGULATION_ID=:id');$current->execute(['id'=>$businessId]);$this->audit->record($userId,'BUSINESS_MANUAL_UPDATE','BUSINESS',(string)$businessId,$this->auditValues($previous),$this->auditValues($current->fetch()?:[]));
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function nullable(mixed $value):?string{$value=trim((string)$value);return $value===''?null:$value;}
    private function validDate(string $value):bool{$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $date!==false&&$date->format('Y-m-d')===$value;}
    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function auditValues(array $row):array{$fields=array_merge(array_keys(self::TEXT),self::INTEGERS,self::BITS,self::DATES,self::DECIMALS);return array_intersect_key($row,array_flip($fields));}
}
