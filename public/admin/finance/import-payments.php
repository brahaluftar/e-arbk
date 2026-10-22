<?php
declare(strict_types=1);
// Daily payment report from the Finance Directorate (XLSX or CSV).
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);require_post();
$file=$_FILES['report']??null;
if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??''))){flash('error','Ngarkimi i raportit dështoi.');redirect('/admin/finance/payments.php');}
if((int)($file['size']??0)<1||(int)$file['size']>20*1024*1024){flash('error','Raporti duhet të jetë deri në 20 MB.');redirect('/admin/finance/payments.php');}
set_time_limit(0);
$batchId=null;
run_action(static function()use($app,$file,$user,&$batchId){
    $batchId=(new App\Billing\PaymentImportService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->import((string)$file['tmp_name'],(string)($file['name']??'raport.xlsx'),(int)$user['id']);
    $batch=(new App\Repository\InvoiceRepository($app['pdo']))->paymentImport($batchId);
    return 'Raporti u përpunua: '.(int)$batch['applied_rows'].' pagesa u aplikuan ('.money($batch['applied_amount']).'), '.(int)$batch['review_rows'].' për shqyrtim, '.(int)$batch['duplicate_rows'].' dublikata.';
},'Raporti nuk mund të përpunohej.');
redirect($batchId!==null?'/admin/finance/payment-import.php?id='.$batchId:'/admin/finance/payments.php');
