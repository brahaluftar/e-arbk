<?php
declare(strict_types=1);

$app=require dirname(__DIR__).'/bootstrap.php';$config=$app['config'];
if(PHP_SAPI!=='cli') exit("CLI only.\n");
if($config->string('BACKUP_MODE')==='managed'){echo "Managed backup mode: verify the CloudClusters backup schedule and restore test in the control panel.\n";exit(0);}
$directory=rtrim($config->string('DB_BACKUP_PATH'),"/\\");if($directory==='') throw new RuntimeException('DB_BACKUP_PATH is required.');
$database=$config->string('DB_NAME');if(preg_match('/\A[A-Za-z0-9_-]+\z/',$database)!==1) throw new RuntimeException('Unsafe database name.');
$separator=str_contains($directory,'\\')?'\\':'/';$file=$directory.$separator.$database.'_'.gmdate('Ymd_His').'.bak';
$escaped=str_replace("'","''",$file);$quoted='['.str_replace(']',']]',$database).']';
$app['pdo']->exec("BACKUP DATABASE $quoted TO DISK=N'$escaped' WITH COPY_ONLY,COMPRESSION,CHECKSUM,INIT");
$app['pdo']->exec("RESTORE VERIFYONLY FROM DISK=N'$escaped' WITH CHECKSUM");
echo "Backup created and verified: $file\n";
