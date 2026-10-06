<?php
declare(strict_types=1);

if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/vendor/autoload.php';

use App\Deployment\DatabaseTransfer;
use App\Support\Config;
use App\Support\ProductionValidator;

$root=dirname(__DIR__);$action=$argv[1]??'help';$directory=$argv[2]??'';
if (!in_array($action,['export','import','verify','check'],true) || $directory==='') {
    echo "Usage: php bin/database-transfer.php export|import|verify|check DIRECTORY [--create-database]\n";exit($action==='help'?0:1);
}
try {
    $transfer=new DatabaseTransfer();
    if ($action==='check') {$m=$transfer->manifest($directory);echo 'Validated transfer: '.count($m['tables']).' tables, '.array_sum(array_column($m['tables'],'rows'))." rows.\n";exit;}
    $config=Config::load($root,require $root.'/config/defaults.php');
    ProductionValidator::enforce($config);
    $database=$config->string('DB_NAME');
    if (preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,127}\z/D',$database)!==1 || in_array(strtolower($database),['master','model','msdb','tempdb'],true)) throw new RuntimeException('Use a dedicated application database name.');
    $connect=static function(string $name) use($config): PDO {
        $host=str_replace([';',"\0","\r","\n"],'',$config->string('DB_HOST'));
        return new PDO('sqlsrv:Server='.$host.';Database='.$name.';LoginTimeout=15;Encrypt='.($config->bool('DB_ENCRYPT')?'true':'false').';TrustServerCertificate='.($config->bool('DB_TRUST_SERVER_CERTIFICATE')?'true':'false'),$config->bool('DB_TRUSTED_CONNECTION')?null:$config->string('DB_USER'),$config->bool('DB_TRUSTED_CONNECTION')?null:$config->string('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    };
    if ($action==='import' && in_array('--create-database',$argv,true)) {
        $m=$transfer->manifest($directory);$master=$connect('master');$s=$master->prepare('SELECT DB_ID(:name)');$s->execute(['name'=>$database]);
        if (!$s->fetchColumn()) {
            if (preg_match('/\A[A-Za-z0-9_]+\z/D',$m['collation'])!==1) throw new RuntimeException('Invalid database collation.');
            $master->exec('CREATE DATABASE '.DatabaseTransfer::quote($database).' COLLATE '.$m['collation']);
            echo "Created configured database.\n";
        }
        $master=null;
    }
    $pdo=$connect($database);
    if ($action==='export') $transfer->export($pdo,$directory,$root);
    elseif ($action==='import') $transfer->import($pdo,$directory);
    else $transfer->verify($pdo,$directory);
} catch(Throwable $e) {
    $message=$e->getMessage();
    if (isset($config)) foreach(['DB_PASSWORD','APP_KEY'] as $key) { $secret=$config->string($key);if($secret!=='')$message=str_replace($secret,'[redacted]',$message); }
    fwrite(STDERR,"Database transfer failed: ".$message."\n");exit(1);
}
