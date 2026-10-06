<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/vendor/autoload.php';
$root=dirname(__DIR__);
try {
    $config=App\Support\Config::load($root,require $root.'/config/defaults.php');
    $errors=App\Support\ProductionValidator::errors($config);
    if ($config->string('APP_ENV')!=='production') $errors[]='APP_ENV must be production.';
    if ($config->string('APP_URL')!=='https://arbk.kryeqyteti.net') $errors[]='APP_URL must be https://arbk.kryeqyteti.net.';
    foreach(['pdo_sqlsrv','mbstring','openssl','zip','xmlreader','xmlwriter','simplexml','zlib'] as $extension) if(!extension_loaded($extension)) $errors[]='Missing extension: '.$extension;
    if(PHP_VERSION_ID<80100) $errors[]='PHP 8.1+ is required.';
    if($errors!==[]) throw new RuntimeException(implode("\n",$errors));
    echo "Production environment and CLI extensions validated.\n";
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");exit(1);}
