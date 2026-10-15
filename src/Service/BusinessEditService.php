<?php
declare(strict_types=1);
namespace App\Service;
use DomainException;use PDO;use Throwable;

final class BusinessEditService
{
    private const TEXT=['NUMRI_FISKAL'=>30,'ADRESA'=>500,'EMAIL'=>254,'MENAXHERI'=>255,'NUMRI_TELEFONIT'=>50];
    private const INTEGERS=['NR_PUNETOREVE','TOTAL_M','TOTAL_F'];
    private const BITS=['Pasiv','ATK_MBYLLUR','pronare_grua','pronar_veteran'];
    private const DECIMALS=['pronesia_grua','perqindja_veteran'];
    public function __construct(private PDO $pdo,private AuditLogger $audit){}
    /** @param array<string,mixed> $input */
    public function update(int $businessId,array $input,int $userId):void
    {
        $values=[];
        foreach(self::TEXT as $field=>$limit){$value=$this->nullable($input[$field]??null);if($value!==null&&mb_strlen($value)>$limit)throw new DomainException("Fusha $field është më e gjatë se kufiri $limit.");$values[$field]=$value;}
        if($values['EMAIL']!==null&&filter_var($values['EMAIL'],FILTER_VALIDATE_EMAIL)===false)throw new DomainException('Emaili i biznesit nuk është valid.');
        foreach(self::INTEGERS as $field){$raw=$this->nullable($input[$field]??null);if($raw!==null&&filter_var($raw,FILTER_VALIDATE_INT)===false)throw new DomainException("Fusha $field duhet të jetë numër i plotë.");$value=$raw===null?null:(int)$raw;if($value!==null&&$value<0)throw new DomainException("Fusha $field nuk mund të jetë negative.");$values[$field]=$value;}
        foreach(self::BITS as $field){$raw=$this->nullable($input[$field]??null);if($raw!==null&&!in_array($raw,['0','1'],true))throw new DomainException("Fusha $field nuk është valide.");$values[$field]=$raw===null?null:(int)$raw;}
        foreach(self::DECIMALS as $field){$raw=$this->nullable($input[$field]??null);$raw=$raw===null?null:str_replace(',','.',$raw);if($raw!==null&&!is_numeric($raw))throw new DomainException("Fusha $field duhet të jetë numerike.");$value=$raw===null?null:(float)$raw;if($value!==null&&($value<0||$value>100))throw new DomainException("Fusha $field duhet të jetë ndërmjet 0 dhe 100.");$values[$field]=$value;}
        if($values['pronare_grua']!==1)$values['pronesia_grua']=null;if($values['pronar_veteran']!==1)$values['perqindja_veteran']=null;
        $this->pdo->beginTransaction();try{$statement=$this->pdo->prepare('SELECT * FROM dbo.ARBK_LIST WITH(UPDLOCK,ROWLOCK) WHERE REGULATION_ID=:id');$statement->execute(['id'=>$businessId]);$previous=$statement->fetch();if(!is_array($previous))throw new DomainException('Biznesi nuk u gjet.');$assignments=[];foreach(array_keys($values) as $field)$assignments[]="[$field]=:$field";$values['id']=$businessId;$this->pdo->prepare('UPDATE dbo.ARBK_LIST SET '.implode(',',$assignments).',import_updated_at=SYSUTCDATETIME() WHERE REGULATION_ID=:id')->execute($values);$current=$this->pdo->prepare('SELECT * FROM dbo.ARBK_LIST WHERE REGULATION_ID=:id');$current->execute(['id'=>$businessId]);$this->audit->record($userId,'BUSINESS_MANUAL_UPDATE','BUSINESS',(string)$businessId,$this->auditValues($previous),$this->auditValues($current->fetch()?:[]));$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    private function nullable(mixed $value):?string{$value=trim((string)$value);return $value===''?null:$value;}
    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function auditValues(array $row):array{$fields=array_merge(array_keys(self::TEXT),self::INTEGERS,self::BITS,self::DECIMALS);return array_intersect_key($row,array_flip($fields));}
}
