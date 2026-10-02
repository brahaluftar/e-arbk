<?php
declare(strict_types=1);

use App\Service\AtkStatusService;
use App\Service\AuditLogger;
use App\Service\ClassificationService;
use App\Service\TariffMappingService;

$app=require dirname(__DIR__).'/bootstrap.php';if(PHP_SAPI!=='cli')exit("CLI only.\n");
$lockPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'e-arbk-scheduled.lock';$lock=fopen($lockPath,'c');
if($lock===false||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Another scheduled run is active.\n");exit(2);}
try {
    $audit=new AuditLogger($app['pdo']);
    $imported=(new App\Import\BusinessImportService($app['pdo'],$audit))->processNext();
    $atk=(new AtkStatusService($app['pdo'],$audit))->synchronize();
    $mapping=(new TariffMappingService($app['pdo'],$audit))->synchronize();
    $classified=(new ClassificationService($app['pdo'],$audit))->autoAssign();
    $app['pdo']->exec("DELETE dbo.login_rate_limits WHERE updated_at<DATEADD(day,-7,SYSUTCDATETIME())");
    echo json_encode(['import_processed'=>$imported,'atk'=>$atk,'mapping'=>$mapping,'classified'=>$classified,'completed_at'=>gmdate(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {flock($lock,LOCK_UN);fclose($lock);}
