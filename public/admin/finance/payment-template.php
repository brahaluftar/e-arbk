<?php
declare(strict_types=1);
// Empty workbook with the column names accepted by the payment report import.
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);
$temporary=tempnam(sys_get_temp_dir(),'arbk_payment_template_');if($temporary===false){http_response_code(500);exit('Export failed.');}
(new App\Export\XlsxWriter())->write($temporary,['uniref'=>'UNIREF','amount'=>'Shuma','paid_on'=>'Data e pagesës','reference'=>'Referenca','payer'=>'Paguesi'],[['uniref'=>(new App\Billing\UnirefGenerator())->compose('4711',1),'amount'=>125.5,'paid_on'=>date('d.m.Y'),'reference'=>'TRX-0001','payer'=>'Shembull Sh.p.k.']],'Pagesat');
send_file($temporary,'shablloni-raporti-pagesave.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',true);
