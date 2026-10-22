<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);if(!$id){http_response_code(400);exit('Invalid import ID.');}
$repository=new App\Repository\InvoiceRepository($app['pdo']);$batch=$repository->paymentImport((int)$id);
if($batch===null){http_response_code(404);exit('Import not found.');}
$onlyReview=($_GET['all']??'')!=='1';
$rows=$repository->paymentImportRows((int)$id,$onlyReview);
render('admin/finance/payment-import','Raporti i pagesave #'.(int)$id,compact('batch','rows','onlyReview'));
