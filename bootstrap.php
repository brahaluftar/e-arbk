<?php
declare(strict_types=1);

use App\Infrastructure\Database\ConnectionFactory;
use App\Security\Auth;
use App\Security\Csrf;
use App\Support\Config;

$root = __DIR__;
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) { http_response_code(503); exit('Run composer install first.'); }
require $autoload;
$config = Config::load($root, require $root . '/config/defaults.php');
date_default_timezone_set($config->string('APP_TIMEZONE'));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name($config->string('APP_SESSION_NAME'));
session_set_cookie_params(['httponly'=>true,'secure'=>(($_SERVER['HTTPS']??'')==='on'),'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$pdo = (new ConnectionFactory($config))->create();
$auth = new Auth($pdo);
$csrf = new Csrf($config->int('CSRF_TTL_SECONDS'));

function app_url(string $path=''): string { global $config; return $config->basePath() . $path; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . app_url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
function take_flashes(): array { $f=$_SESSION['_flash']??[]; unset($_SESSION['_flash']); return is_array($f)?$f:[]; }
function render(string $template,string $title,array $view=[]): void { global $root,$config,$auth,$csrf; $user=$auth->user(); $flashes=take_flashes(); $templateFile=$root.'/templates/'.$template.'.php'; if(!is_file($templateFile)) throw new RuntimeException('Template not found.'); extract($view,EXTR_SKIP); require $root.'/templates/layout.php'; }

return compact('root','config','pdo','auth','csrf');
