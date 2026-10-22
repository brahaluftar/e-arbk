<?php
declare(strict_types=1);
// Clients and their unpaid amounts for the Finance Directorate report.
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);
$raw=(string)($_GET['year']??'');$year=$raw===''?null:filter_var($raw,FILTER_VALIDATE_INT);
if($year===false||($year!==null&&($year<2000||$year>2100))){http_response_code(400);exit('Invalid year.');}
$columns=['registration_number'=>'NRB','legal_name'=>'Klienti','fiscal_number'=>'Numri fiskal','address'=>'Adresa','email'=>'Emaili','phone_number'=>'Telefoni','manager_name'=>'Menaxheri','unpaid_invoices'=>'Fatura të papaguara','first_year'=>'Nga viti','last_year'=>'Deri në vitin','invoiced_amount'=>'Shuma e faturave (€)','paid_amount'=>'Paguar (€)','outstanding'=>'E papaguar (€)','overdue_amount'=>'Me afat të kaluar (€)','oldest_due_on'=>'Afati më i vjetër','last_payment_on'=>'Pagesa e fundit'];
$rows=(static function()use($app,$year){$statement=(new App\Repository\InvoiceRepository($app['pdo']))->obligations($year);try{while($row=$statement->fetch(PDO::FETCH_ASSOC)){foreach(['unpaid_invoices','first_year','last_year'] as $k)$row[$k]=(int)$row[$k];foreach(['invoiced_amount','paid_amount','outstanding','overdue_amount'] as $k)$row[$k]=round((float)$row[$k],2);yield $row;}}finally{$statement->closeCursor();}})();
$temporary=tempnam(sys_get_temp_dir(),'arbk_obligations_');if($temporary===false){http_response_code(500);exit('Export failed.');}
set_time_limit(0);session_write_close();
try{(new App\Export\XlsxWriter())->write($temporary,$columns,$rows,'Detyrimet');}catch(Throwable $e){@unlink($temporary);throw $e;}
send_file($temporary,'detyrimet-e-papaguara-'.($year===null?'te-gjitha-vitet':'deri-'.$year).'-'.date('Y-m-d').'.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',true);
