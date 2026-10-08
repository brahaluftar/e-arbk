<?php
declare(strict_types=1);

use App\Infrastructure\Database\ConnectionFactory;
use App\Support\Config;
use App\Support\ProductionValidator;

$root=dirname(__DIR__);require $root.'/vendor/autoload.php';
$config=Config::load($root,require $root.'/config/defaults.php');$errors=ProductionValidator::errors($config);
if($config->string('APP_ENV')!=='production') $errors[]='APP_ENV must be production for this preflight.';
foreach(['pdo','pdo_sqlsrv','mbstring','openssl','zip','xmlreader','xmlwriter','simplexml','zlib','gd'] as $extension) if(!extension_loaded($extension)) $errors[]="Missing PHP extension: $extension.";
if(PHP_VERSION_ID<80100) $errors[]='PHP 8.1 or newer is required.';
if(!is_file($root.'/public/.htaccess')) $errors[]='public/.htaccess is missing.';
try {
    $pdo=(new ConnectionFactory($config))->create();$pdo->query('SELECT 1')->fetchColumn();
    $tableStatement=$pdo->query("SELECT name FROM sys.tables WHERE schema_id=SCHEMA_ID('dbo')");
    $existingTables=$tableStatement->fetchAll(PDO::FETCH_COLUMN);
    $tableStatement->closeCursor();unset($tableStatement);
    foreach(App\Deployment\DatabaseTransfer::TABLES as $table)if(!in_array($table,$existingTables,true))$errors[]="Missing database table: dbo.$table.";
    if(!$pdo->query("SELECT OBJECT_ID('dbo.v_business_master','V')")->fetchColumn()) $errors[]='Missing database view: dbo.v_business_master.';
    $migrationStatement=$pdo->query('SELECT migration FROM dbo.schema_migrations');
    $applied=$migrationStatement->fetchAll(PDO::FETCH_COLUMN);
    $migrationStatement->closeCursor();unset($migrationStatement);
    foreach(glob($root.'/database/migrations/*.sql')?:[] as $file) if(!in_array(basename($file),$applied,true))$errors[]='Pending migration: '.basename($file);
} catch(Throwable $e){$errors[]='Database check failed: '.$e->getMessage();}
unset($pdo);
if($errors!==[]){foreach($errors as $error)fwrite(STDERR,"FAIL: $error\n");exit(1);}
$completionFile=getenv('ARBK_COMPLETION_FILE');
if($completionFile!==false&&$completionFile!==''){
    if(file_put_contents($completionFile,"complete\n",LOCK_EX)===false)throw new RuntimeException('Could not write the preflight completion marker.');
}
echo "Production preflight passed.\n";
