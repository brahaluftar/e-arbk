<?php
declare(strict_types=1);

use App\Infrastructure\Database\ConnectionFactory;
use App\Support\Config;
use App\Support\ProductionValidator;

$root=dirname(__DIR__);require $root.'/vendor/autoload.php';
$config=Config::load($root,require $root.'/config/defaults.php');$errors=ProductionValidator::errors($config);
if($config->string('APP_ENV')!=='production') $errors[]='APP_ENV must be production for this preflight.';
foreach(['pdo','pdo_sqlsrv','mbstring','openssl','zip','xmlreader','simplexml'] as $extension) if(!extension_loaded($extension)) $errors[]="Missing PHP extension: $extension.";
if(PHP_VERSION_ID<80100) $errors[]='PHP 8.1 or newer is required.';
if(!is_file($root.'/public/.htaccess')) $errors[]='public/.htaccess is missing.';
try {
    $pdo=(new ConnectionFactory($config))->create();$pdo->query('SELECT 1')->fetchColumn();
    foreach(['ARBK_LIST','NACE_LIST','app_users','login_rate_limits','business_import_runs','business_import_staging','schema_migrations'] as $table){$s=$pdo->prepare("SELECT OBJECT_ID(:name,'U')");$s->execute(['name'=>'dbo.'.$table]);if(!$s->fetchColumn())$errors[]="Missing database table: dbo.$table.";}
} catch(Throwable $e){$errors[]='Database check failed: '.$e->getMessage();}
if($errors!==[]){foreach($errors as $error)fwrite(STDERR,"FAIL: $error\n");exit(1);}
echo "Production preflight passed.\n";
