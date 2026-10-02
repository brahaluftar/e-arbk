<?php
declare(strict_types=1);
namespace App\Import;

final class BusinessImportNormalizer
{
    /** @param array<int,string|null> $row @param array<string,int> $columns @return array<string,mixed> */
    public function normalize(array $row,array $columns,string $sourceType): array
    {
        $get=static fn(string $name):?string=>isset($columns[$name])&&isset($row[$columns[$name]])&&trim((string)$row[$columns[$name]])!==''?trim((string)$row[$columns[$name]]):null;
        $naceRaw=$get('Aktiv. Nace2 - Përshkrimi');[$naceCode,$naceDescription]=$this->splitNace($naceRaw);
        $sectorRaw=$get('Sektori');$status=$get('Statusi i biznesit');if($sourceType==='CLOSED')$status='Shuar';
        $businessNumber=$get('Nr. i biznesit');$error=null;if($businessNumber===null||preg_match('/^[0-9A-Za-z]{5,20}$/',$businessNumber)!==1)$error='Numri i biznesit mungon ose nuk është valid.';elseif($naceRaw!==null&&$naceCode===null)$error='Formati NACE nuk është valid.';
        return ['business_number'=>$businessNumber,'legal_name'=>$get('Emri i biznesit'),'trade_name'=>$get('Emri tregtare'),'business_type'=>$get('Lloji i biznesit'),'nace_raw'=>$naceRaw,'nace_code'=>$naceCode,'nace_description'=>$naceDescription,'sector_raw'=>$sectorRaw,'sector_clean'=>$this->cleanSector($sectorRaw),'employee_count'=>$this->integer($get('Nr. punëtorëve')),'business_size'=>$get('Madhësia'),'total_m'=>$this->integer($get('Total M')),'total_f'=>$this->integer($get('Total F')),'city'=>$get('Qyteti'),'business_status'=>$status,'business_year'=>$get('Years'),'business_month'=>$get('Months'),'closed_date'=>$this->date($get('Data Shuarjes')),'validation_error'=>$error];
    }
    /** @return array{?string,?string} */
    public function splitNace(?string $value): array{if($value===null)return[null,null];if(preg_match('/^\s*([0-9]{4})\s*[-–—]\s*(.+?)\s*$/u',$value,$m)!==1)return[null,trim($value)];return[$m[1],trim($m[2])];}
    public function cleanSector(?string $value):?string{if($value===null)return null;return preg_replace('/^\s*[A-Za-z]\s*[-–—]\s*/u','',trim($value))?:null;}
    private function integer(?string $value):?int{return $value!==null&&is_numeric($value)?max(0,(int)$value):null;}
    private function date(?string $value):?string{if($value===null)return null;if(is_numeric($value)){$timestamp=((float)$value-25569)*86400;return gmdate('Y-m-d',(int)$timestamp);}foreach(['d/m/Y','m/d/Y','Y-m-d'] as $format){$date=\DateTimeImmutable::createFromFormat('!'.$format,$value);if($date!==false)return $date->format('Y-m-d');}return null;}
}
