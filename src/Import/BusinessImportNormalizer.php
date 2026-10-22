<?php
declare(strict_types=1);
namespace App\Import;

final class BusinessImportNormalizer
{
    /** @param array<int,string|null> $row @param array<string,int> $columns @return array<string,mixed> */
    public function normalize(array $row,array $columns,string $sourceType): array
    {
        $get=static fn(string $name):?string=>isset($columns[$name])&&isset($row[$columns[$name]])&&trim((string)$row[$columns[$name]])!==''?trim((string)$row[$columns[$name]]):null;
        if($sourceType==='CONTACTS'){
            $businessNumber=$get('NRB');$error=$businessNumber===null||preg_match('/^[0-9A-Za-z]{5,20}$/',$businessNumber)!==1?'NRB mungon ose nuk është valid.':null;
            return ['business_number'=>$businessNumber,'manager_name'=>$get('MENAXHERI'),'phone_number'=>$get('NUMRI_TELEFONIT'),'employee_count'=>$this->integer($get('NUMRI_PUNETOREVE')),'business_size'=>$get('Madhesia'),'validation_error'=>$error];
        }
        if($sourceType==='UNITS'){
            $businessNumber=$get('NRB');$name=$get('EMRI_NJESISE');$address=$get('ADRESA');$nace=strtoupper((string)$get('NACE'));$email=$get('EMAILI');
            $rawTariff=$get('TARIFA_VJETORE');$tariff=$rawTariff!==null&&is_numeric(str_replace(',','.',$rawTariff))?round((float)str_replace(',','.',$rawTariff),2):null;
            $auto=in_array(mb_strtolower((string)$get('FATURIM_AUTOMATIK')),['1','po','yes','true','x'],true)?1:0;
            $error=null;
            if($businessNumber===null||preg_match('/^[0-9A-Za-z]{5,20}$/',$businessNumber)!==1)$error='NRB mungon ose nuk është valid.';
            elseif($name===null||$address===null)$error='Emri i njësisë dhe adresa kërkohen.';
            elseif(preg_match('/^[0-9A-Z]{4}$/',$nace)!==1)$error='Kodi NACE duhet të ketë saktësisht 4 karaktere.';
            elseif($rawTariff!==null&&($tariff===null||$tariff<=0))$error='Tarifa vjetore duhet të jetë numër më i madh se zero.';
            elseif($auto===1&&$tariff===null)$error='Faturimi automatik kërkon tarifë vjetore.';
            elseif($email!==null&&filter_var($email,FILTER_VALIDATE_EMAIL)===false)$error='Emaili i njësisë nuk është valid.';
            return ['business_number'=>$businessNumber,'unit_name'=>$name,'unit_address'=>$address,'unit_email'=>$email,'phone_number'=>$get('NUMRI_TELEFONIT'),'unit_manager'=>$get('MENAXHERI'),'unit_nace_code'=>$nace,'unit_nace_description'=>$get('VEPRIMTARIA_NACE'),'unit_annual_tariff'=>$error===null?$tariff:null,'unit_auto_invoice'=>$auto,'validation_error'=>$error];
        }
        $naceRaw=$get('Aktiv. Nace2 - Përshkrimi');[$naceCode,$naceDescription]=$this->splitNace($naceRaw);
        $sectorRaw=$get('Sektori');$status=$get('Statusi i biznesit');[$status,$passive,$passiveDate]=$this->passiveStatus($status);if($sourceType==='CLOSED')$status='Shuar';
        $businessNumber=$get('Nr. i biznesit');$closedRaw=$get('Data Shuarjes');$closedDate=$this->date($closedRaw);$error=null;if($businessNumber===null||preg_match('/^[0-9A-Za-z]{5,20}$/',$businessNumber)!==1)$error='Numri i biznesit mungon ose nuk është valid.';elseif($naceRaw!==null&&$naceCode===null)$error='Formati NACE nuk është valid.';elseif($closedRaw!==null&&$closedDate===null)$error='Data e shuarjes nuk është valide.';elseif($status==='Pasiv'&&$passive===1&&$passiveDate===null)$error='Data e pasivizimit nuk është valide.';
        return ['business_number'=>$businessNumber,'legal_name'=>$get('Emri i biznesit'),'trade_name'=>$get('Emri tregtare'),'business_type'=>$get('Lloji i biznesit'),'nace_raw'=>$naceRaw,'nace_code'=>$naceCode,'nace_description'=>$naceDescription,'sector_raw'=>$sectorRaw,'sector_clean'=>$this->cleanSector($sectorRaw),'employee_count'=>$this->integer($get('Nr. punëtorëve')),'business_size'=>$get('Madhësia'),'total_m'=>$this->integer($get('Total M')),'total_f'=>$this->integer($get('Total F')),'city'=>$get('Qyteti'),'business_status'=>$status,'business_year'=>$get('Years'),'business_month'=>$get('Months'),'closed_date'=>$closedDate,'passive'=>$passive,'passive_date'=>$passiveDate,'validation_error'=>$error];
    }
    /** @return array{?string,?string} */
    public function splitNace(?string $value): array{if($value===null)return[null,null];if(preg_match('/^\s*([0-9]{4})\s*[-–—]\s*(.+?)\s*$/u',$value,$m)!==1)return[null,trim($value)];return[$m[1],trim($m[2])];}
    public function cleanSector(?string $value):?string{if($value===null)return null;return preg_replace('/^\s*[A-Za-z]\s*[-–—]\s*/u','',trim($value))?:null;}
    /** @return array{?string,?int,?string} */
    private function passiveStatus(?string $value):array{if($value===null)return[null,null,null];if(preg_match('/^Pasiv\s*-\s*(\d{2}\/\d{2}\/\d{4})$/iu',$value,$matches)!==1)return[$value,0,null];$date=\DateTimeImmutable::createFromFormat('!d/m/Y',$matches[1]);return['Pasiv',1,$date===false||$date->format('d/m/Y')!==$matches[1]?null:$date->format('Y-m-d')];}
    private function integer(?string $value):?int{return $value!==null&&is_numeric($value)?max(0,(int)$value):null;}
    private function date(?string $value):?string{if($value===null)return null;if(is_numeric($value)){$timestamp=((float)$value-25569)*86400;return gmdate('Y-m-d',(int)$timestamp);}foreach(['d/m/Y','d.m.Y','Y-m-d'] as $format){$date=\DateTimeImmutable::createFromFormat('!'.$format,$value);if($date!==false&&$date->format($format)===$value)return $date->format('Y-m-d');}return null;}
}
