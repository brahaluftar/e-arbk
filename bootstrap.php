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
$config = Config::load($root, require $root . '/config/defaults.php');
ProductionValidator::enforce($config);
date_default_timezone_set($config->string('APP_TIMEZONE'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
$forwardedHttps=$config->bool('TRUST_PROXY_HEADERS') && strtolower(trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))[0]))==='https';
$requestHttps=(($_SERVER['HTTPS']??'')==='on') || $forwardedHttps;
if($config->bool('FORCE_HTTPS') && !$requestHttps && PHP_SAPI!=='cli') {
    if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true)){http_response_code(400);exit('HTTPS is required.');}
    $target=rtrim($config->string('APP_URL'),'/').(string)($_SERVER['REQUEST_URI']??'/');header('Location: '.$target,true,308);exit;
}
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name($config->string('APP_SESSION_NAME'));
session_set_cookie_params(['httponly'=>true,'secure'=>$requestHttps||$config->bool('FORCE_HTTPS'),'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pdo = (new ConnectionFactory($config))->create();
$auth = new Auth($pdo);
$csrf = new Csrf($config->int('CSRF_TTL_SECONDS'));

function app_url(string $path=''): string { global $config; return $config->basePath() . $path; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . app_url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
function take_flashes(): array { $f=$_SESSION['_flash']??[]; unset($_SESSION['_flash']); return is_array($f)?$f:[]; }
function request_ip(): string { global $config; if($config->bool('TRUST_PROXY_HEADERS')){$forwarded=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??''))[0]);if(filter_var($forwarded,FILTER_VALIDATE_IP)!==false)return $forwarded;} $remote=(string)($_SERVER['REMOTE_ADDR']??'unknown');return filter_var($remote,FILTER_VALIDATE_IP)!==false?$remote:'unknown'; }
function business_list_path(mixed $query): string {
    parse_str(ltrim((string)$query,'?'),$input);
    $allowed=['q','nace','classification','atk_status','pronare_grua','pronar_veteran','page'];$params=[];
    foreach($allowed as $key)if(isset($input[$key])&&is_scalar($input[$key])&&(string)$input[$key]!=='')$params[$key]=(string)$input[$key];
    if(isset($params['page']))$params['page']=(string)max(1,(int)$params['page']);
    if(isset($params['classification'])&&!in_array($params['classification'],['UNCLASSIFIED','AUTO','MANUAL','AMBIGUOUS','NO_RELATION'],true))unset($params['classification']);
    if(isset($params['atk_status'])&&!in_array($params['atk_status'],['ACTIVE','DEACTIVATED','NEEDS_REVIEW'],true))unset($params['atk_status']);
    foreach(['pronare_grua','pronar_veteran'] as $key)if(isset($params[$key])&&!in_array($params[$key],['0','1'],true))unset($params[$key]);
    return '/admin/businesses/index.php'.($params===[]?'':'?'.http_build_query($params));
}
function render(string $template,string $title,array $view=[]): void { global $root,$config,$auth,$csrf; $user=$auth->user(); $flashes=take_flashes(); $templateFile=$root.'/templates/'.$template.'.php'; if(!is_file($templateFile)) throw new RuntimeException('Template not found.'); extract($view,EXTR_SKIP); require $root.'/templates/layout.php'; }

return compact('root','config','pdo','auth','csrf');
