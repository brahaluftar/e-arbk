<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireRole(['ADMIN']);require_post();
$q=$app['pdo']->prepare("UPDATE dbo.annual_invoice_jobs SET artifact_status='PENDING',artifact_error=NULL WHERE id=:id AND status_code IN('COMPLETED','COMPLETED_WITH_ERRORS') AND artifact_status='FAILED'");$q->execute(['id'=>(int)($_POST['job_id']??0)]);
flash($q->rowCount()===1?'success':'error',$q->rowCount()===1?'Eksportet u kthyen në radhë.':'Eksportet nuk mund të riprovohen.');redirect('/admin/finance/annual.php');
