<?php
declare(strict_types=1);
$app=require dirname(__DIR__,3).'/bootstrap.php';$user=$app['auth']->requireRole(['ADMIN']);
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$type=(string)($_POST['source_type']??'');if(!in_array($type,['ALL','WOMEN','CLOSED'],true)){flash('error','Lloji i importit nuk është valid.');redirect('/admin/imports/index.php');}
$file=$_FILES['xlsx']??null;if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){flash('error','Ngarkimi i skedarit dështoi.');redirect('/admin/imports/index.php');}
$size=(int)($file['size']??0);$name=(string)($file['name']??'');$temporary=(string)($file['tmp_name']??'');
if($size<1||$size>$app['config']->int('IMPORT_MAX_BYTES')||strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='xlsx'){flash('error','Lejohet vetëm XLSX brenda kufirit të madhësisë.');redirect('/admin/imports/index.php');}
$zip=new ZipArchive();$valid=$zip->open($temporary)===true&&$zip->locateName('[Content_Types].xml')!==false&&$zip->locateName('xl/worksheets/sheet1.xml')!==false;$expandedBytes=0;if($valid){for($i=0;$i<$zip->numFiles;$i++){$entry=$zip->statIndex($i);$expandedBytes+=(int)($entry['size']??0);if($expandedBytes>1073741824){$valid=false;break;}}}if($zip->status===ZipArchive::ER_OK)$zip->close();
if(!$valid){flash('error','Skedari nuk është XLSX valid.');redirect('/admin/imports/index.php');}
$hash=hash_file('sha256',$temporary);$storageName=bin2hex(random_bytes(16)).'.xlsx';$relative='var/imports/'.$storageName;$destination=dirname(__DIR__,3).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
if(!move_uploaded_file($temporary,$destination)){flash('error','Skedari nuk mund të ruhej.');redirect('/admin/imports/index.php');}
try{$statement=$app['pdo']->prepare("INSERT dbo.business_import_runs(source_type,original_filename,storage_path,file_sha256,uploaded_by_user_id) OUTPUT inserted.id VALUES(:type,:name,:path,:hash,:user)");$statement->execute(['type'=>$type,'name'=>mb_substr(basename($name),0,255),'path'=>$relative,'hash'=>$hash,'user'=>$user['id']]);$id=(int)$statement->fetchColumn();(new App\Service\AuditLogger($app['pdo']))->record($user['id'],'BUSINESS_IMPORT_QUEUED','IMPORT_RUN',(string)$id,null,['source_type'=>$type,'filename'=>basename($name),'sha256'=>$hash]);flash('success','Importi u vendos në radhë.');}
catch(Throwable $e){@unlink($destination);flash('error',str_contains($e->getMessage(),'UQ_business_import_hash_type')?'Ky skedar është importuar më parë.':'Importi nuk mund të regjistrohej.');}
redirect('/admin/imports/index.php');
