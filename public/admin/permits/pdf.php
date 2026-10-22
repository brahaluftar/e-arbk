<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$app['auth']->requireStaff();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$statement=$app['pdo']->prepare('SELECT pdf_storage_key,serial_number FROM dbo.business_permits WHERE id=:id');$statement->execute(['id'=>(int)$id]);$permit=$statement->fetch();
if(!$id||!is_array($permit)||$permit['pdf_storage_key']===null){http_response_code(404);exit('Not found.');}
try{$path=(new App\Document\DocumentStore(dirname(__DIR__,3).'/var/documents'))->path((string)$permit['pdf_storage_key']);}catch(RuntimeException){http_response_code(404);exit('Dokumenti nuk u gjet.');}
send_file($path,'leja-'.$permit['serial_number'].'.pdf','application/pdf');
