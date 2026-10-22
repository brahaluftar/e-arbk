<?php
declare(strict_types=1);

use App\Infrastructure\Database\ConnectionFactory;
use App\Security\Auth;
use App\Security\Csrf;
use App\Support\Config;
use App\Support\ProductionValidator;

$root = __DIR__;
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(503); exit('Run composer install first.'); }
require $autoload;
require $root . '/src/Support/helpers.php';
$config = Config::load($root, require $root . '/config/defaults.php');
ProductionValidator::enforce($config);
date_default_timezone_set($config->string('APP_TIMEZONE'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    $forwardedHttps=$config->bool('TRUST_PROXY_HEADERS') && strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]))==='https';
    $requestHttps=(($_SERVER['HTTPS']??'')==='on') || $forwardedHttps;
    if($config->bool('FORCE_HTTPS') && !$requestHttps) {
        if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(400);exit('HTTPS is required.');}
        $target=rtrim($config->string('APP_URL'),'/').(string)($_SERVER['REQUEST_URI']??'/');header('Location: '.$target,true,308);exit;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name($config->string('APP_SESSION_NAME'));
    session_set_cookie_params(['httponly'=>true,'secure'=>$requestHttps||$config->bool('FORCE_HTTPS'),'samesite'=>'Lax']);
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
} else {
    // CLI workers never use cookies or sessions.
    $_SESSION = [];
}
$pdo = (new ConnectionFactory($config))->create();
$auth = new Auth($pdo);
$csrf = new Csrf($config->int('CSRF_TTL_SECONDS'));

return compact('root','config','pdo','auth','csrf');
