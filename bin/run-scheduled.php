<?php
declare(strict_types=1);

use App\Service\AtkStatusService;
use App\Service\AuditLogger;
use App\Service\ClassificationService;
use App\Service\TariffMappingService;

if(PHP_SAPI!=='cli')exit("CLI only.\n");
// Imports have their own every-minute worker (bin/process-imports.php); this job only synchronizes and classifies.
$app=require dirname(__DIR__).'/bootstrap.php';
$lockPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'e-arbk-scheduled.lock';$lock=fopen($lockPath,'c');
if($lock===false||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Another scheduled run is active.\n");exit(2);}
try {
    $audit=new AuditLogger($app['pdo']);
    $atk=(new AtkStatusService($app['pdo'],$audit))->synchronize();
    $mapping=(new TariffMappingService($app['pdo'],$audit))->synchronize();
    $classified=(new ClassificationService($app['pdo'],$audit))->autoAssign();
    $app['pdo']->exec("DELETE dbo.login_rate_limits WHERE updated_at<DATEADD(day,-7,SYSUTCDATETIME())");
    $app['pdo']->exec("DELETE dbo.password_reset_tokens WHERE expires_at<DATEADD(day,-30,SYSUTCDATETIME())");
    echo json_encode(['atk'=>$atk,'mapping'=>$mapping,'classified'=>$classified,'completed_at'=>gmdate(DATE_ATOM)],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;
} finally {flock($lock,LOCK_UN);fclose($lock);}
