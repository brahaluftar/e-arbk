<?php
declare(strict_types=1);
// Revenue drill-down: NACE section → division → code → invoices / not invoiced, and month → payments / invoices.
use App\Repository\RevenueRepository;
use App\Support\NaceSections;

$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$year=filter_var($_GET['year']??date('Y'),FILTER_VALIDATE_INT);if($year===false||$year<2000||$year>2100)$year=(int)date('Y');
$mode=($_GET['view']??'')==='months'||isset($_GET['month'])?'months':'nace';
$pick=static fn(string $key,string $pattern): ?string => is_string($_GET[$key]??null)&&preg_match($pattern,$_GET[$key])===1?$_GET[$key]:null;
$nace=$pick('nace','/\A(?:[0-9A-Z]{1,20}|-)\z/');
$division=$nace!==null?($nace===RevenueRepository::NO_CODE?NaceSections::UNKNOWN:NaceSections::division($nace)):$pick('division','/\A(?:[0-9]{2}|\?)\z/');
$section=$nace!==null?($nace===RevenueRepository::NO_CODE?NaceSections::UNKNOWN:NaceSections::section($nace)):$pick('section','/\A(?:[A-U]|\?)\z/');
if($division!==null&&$section===null)$section=$division===NaceSections::UNKNOWN?NaceSections::UNKNOWN:NaceSections::section($division.'00');
$month=filter_var($_GET['month']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>12]]);if($month===false)$month=null;
$export=isset($_GET['export']);
$repository=new RevenueRepository($app['pdo']);$policy=App\Billing\BillingPolicy::fromConfig($app['config']);
$limit=$export?null:200;

$groups=[];$invoices=[];$notInvoiced=[];$months=[];$payments=[];
if($mode==='months'){
    if($month===null)$months=$repository->months($year);
    else{$payments=$repository->monthPayments($year,$month,$limit);$invoices=$repository->monthInvoices($year,$month,$limit);}
}elseif($nace!==null){
    $invoices=$repository->invoices($year,$nace,$limit);$notInvoiced=$repository->notInvoiced($year,$nace,$policy,$limit);
    $codeRow=RevenueRepository::rollUp($repository->byNace($year,$policy),$section,$division);
    $codeRow=array_values(array_filter($codeRow,static fn(array $g): bool => $g['key']===$nace))[0]??null;
}else{
    $groups=RevenueRepository::rollUp($repository->byNace($year,$policy),$section,$division);
}

if($export){
    $sheets=[];
    if($mode==='months'&&$month===null){$sheets[]=['Muajt',['month'=>'Muaji','invoiced'=>'Faturuar (€)','invoice_count'=>'Fatura','paid'=>'Paguar (€)','payment_count'=>'Pagesa'],$months,['invoiced','paid']];}
    elseif($mode==='months'){$sheets[]=['Pagesat',['paid_on'=>'Data','amount'=>'Shuma (€)','invoice_number'=>'Fatura','fiscal_year'=>'Viti','business_name_snapshot'=>'Biznesi','registration_number'=>'NRB','reference'=>'Referenca'],$payments,['amount']];
        $sheets[]=['Faturat',['invoice_number'=>'Fatura','issued_on'=>'Data','business_name_snapshot'=>'Biznesi','registration_number'=>'NRB','unit_name'=>'Njësia','amount'=>'Shuma (€)','paid_amount'=>'Paguar (€)','outstanding'=>'Mbetja (€)','status_code'=>'Statusi'],$invoices,['amount','paid_amount','outstanding']];}
    elseif($nace!==null){$sheets[]=['Faturat',['invoice_number'=>'Fatura','issued_on'=>'Data','business_name_snapshot'=>'Biznesi','registration_number'=>'NRB','unit_name'=>'Njësia','amount'=>'Shuma (€)','paid_amount'=>'Paguar (€)','outstanding'=>'Mbetja (€)','status_code'=>'Statusi'],$invoices,['amount','paid_amount','outstanding']];
        $sheets[]=['Pa faturë',['legal_name'=>'Biznesi','registration_number'=>'NRB','unit_name'=>'Njësia','estimated'=>'Vlerësuar (€)','invoiced'=>'Faturuar (€)','to_be_invoiced'=>'Për t’u faturuar (€)'],$notInvoiced,['estimated','invoiced','to_be_invoiced']];}
    else{$sheets[]=['Të hyrat',['key'=>'Kodi','label'=>'Përshkrimi','estimated_count'=>'Biznese/njësi me tarifë','estimated'=>'Vlerësuar (€)','invoiced'=>'Faturuar (€)','to_be_invoiced'=>'Për t’u faturuar (€)','invoice_count'=>'Fatura','paid'=>'Paguar (€)','outstanding'=>'Për t’u paguar (€)'],$groups,['estimated','invoiced','to_be_invoiced','paid','outstanding']];}
    // One sheet per file: the lists below a code or a month are exported one at a time (?export=1&sheet=N).
    $sheetIndex=max(0,min(count($sheets)-1,(int)($_GET['sheet']??0)));[$sheetName,$columns,$rows,$amounts]=$sheets[$sheetIndex];
    $rows=array_map(static function(array $row)use($columns,$amounts): array {$out=[];foreach(array_keys($columns) as $k){$v=$row[$k]??null;$out[$k]=in_array($k,$amounts,true)?round((float)$v,2):($k==='status_code'?label('invoice',(string)$v):$v);}return $out;},$rows);
    $temporary=tempnam(sys_get_temp_dir(),'arbk_revenue_');if($temporary===false){http_response_code(500);exit('Export failed.');}
    set_time_limit(0);session_write_close();
    try{(new App\Export\XlsxWriter())->write($temporary,$columns,$rows,mb_substr($sheetName,0,31));}catch(Throwable $e){@unlink($temporary);throw $e;}
    $scope=$mode==='months'?($month===null?'muajt':'muaji-'.$month):($nace!==null?'nace-'.$nace:($division!==null?'divizioni-'.$division:($section!==null?'seksioni-'.$section:'seksionet')));
    $slug=static fn(string $text): string => trim(preg_replace('/[^a-z0-9]+/','-',strtolower(strtr($text,['ë'=>'e','Ë'=>'e','ç'=>'c','Ç'=>'c'])))??'','-');
    send_file($temporary,'te-hyrat-'.$year.'-'.$slug($scope).(count($sheets)>1?'-'.$slug($sheetName):'').'.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',true);
}

$years=$app['pdo']->query('SELECT DISTINCT fiscal_year FROM dbo.business_invoices ORDER BY fiscal_year DESC')->fetchAll(PDO::FETCH_COLUMN);
$years=array_map('intval',$years);if(!in_array($year,$years,true))$years[]=$year;rsort($years);
render('admin/finance/revenue','Të hyrat',compact('year','years','mode','section','division','nace','month','groups','invoices','notInvoiced','months','payments','limit')+['codeRow'=>$codeRow??null]);
