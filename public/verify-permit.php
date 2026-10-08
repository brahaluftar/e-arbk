<?php
declare(strict_types=1);
$app=require dirname(__DIR__).'/bootstrap.php';$token=(string)($_GET['token']??'');$permit=null;
try{$parsed=(new App\Permitting\PublicPermitToken())->parse($token);$statement=$app['pdo']->prepare("SELECT serial_number,business_name_snapshot,business_address_snapshot,registered_nace_code_snapshot,nace_description_snapshot,issued_on,valid_from,valid_until,status_code,CASE WHEN status_code='ACTIVE' AND valid_until>=CONVERT(date,SYSUTCDATETIME()) THEN 1 ELSE 0 END is_valid FROM dbo.business_permits WHERE public_selector=:selector AND public_secret_hash=CONVERT(binary(32),:hash,2)");$statement->execute(['selector'=>$parsed['selector'],'hash'=>$parsed['hash']]);$permit=$statement->fetch()?:null;}catch(InvalidArgumentException){}
render('public/verify-permit','Verifikimi i lejes',compact('permit'));
