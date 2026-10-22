<?php
declare(strict_types=1);

use App\Support\Labels;

/*
 * Global helpers shared by controllers and templates. They read the objects created by
 * bootstrap.php ($root, $config, $auth, $csrf) from the global scope.
 */

function app_url(string $path=''): string { global $config; return $config->basePath() . $path; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . app_url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][]=['type'=>$type,'message'=>$message]; }
function take_flashes(): array { $f=$_SESSION['_flash']??[]; unset($_SESSION['_flash']); return is_array($f)?$f:[]; }
function request_ip(): string { global $config; if($config->bool('TRUST_PROXY_HEADERS')){$forwarded=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??''))[0]);if(filter_var($forwarded,FILTER_VALIDATE_IP)!==false)return $forwarded;} $remote=(string)($_SERVER['REMOTE_ADDR']??'unknown');return filter_var($remote,FILTER_VALIDATE_IP)!==false?$remote:'unknown'; }

/** Business list filters accepted from the query string, normalized. @return array<string,string> */
function business_filters(array $input, string $defaultClassification=''): array
{
    $text=static fn(string $key): string => is_scalar($input[$key]??null) ? trim((string)$input[$key]) : '';
    $filters=['q'=>mb_substr($text('q'),0,120),'nace'=>mb_substr($text('nace'),0,20),'classification'=>array_key_exists('classification',$input)?$text('classification'):$defaultClassification,'atk_status'=>$text('atk_status'),'pronare_grua'=>$text('pronare_grua'),'pronar_veteran'=>$text('pronar_veteran')];
    if(!in_array($filters['classification'],['','UNCLASSIFIED','AUTO','MANUAL','AMBIGUOUS','NO_RELATION'],true))$filters['classification']='';
    if(!in_array($filters['atk_status'],['','ACTIVE','DEACTIVATED','NEEDS_REVIEW'],true))$filters['atk_status']='';
    foreach(['pronare_grua','pronar_veteran'] as $key)if(!in_array($filters[$key],['','0','1'],true))$filters[$key]='';
    return $filters;
}

function business_list_path(mixed $query): string {
    parse_str(ltrim((string)$query,'?'),$input);
    $params=array_filter(business_filters($input),static fn(string $v): bool => $v!=='');
    if(isset($input['page'])&&is_scalar($input['page']))$params['page']=(string)max(1,(int)$input['page']);
    return '/admin/businesses/index.php'.($params===[]?'':'?'.http_build_query($params));
}

/** Validates an internal "return to" path posted by a form; falls back when unsafe. */
function safe_return(mixed $path, string $fallback): string
{
    $path=is_string($path)?trim($path):'';
    if($path===''||preg_match('~\A/(?:admin|portal)/[A-Za-z0-9/_.-]+\.php(?:\?[A-Za-z0-9=&_%.+-]*)?\z~',$path)!==1||str_contains($path,'..')||str_contains($path,'//'))return $fallback;
    return $path;
}

/** Path of the current request relative to the application base path, without the query string. */
function current_path(): string
{
    global $config;
    $path=(string)parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH);
    $base=$config->basePath();
    if($base!==''&&str_starts_with($path,$base))$path=substr($path,strlen($base));
    return $path===''?'/':$path;
}

function render(string $template,string $title,array $view=[]): void {
    global $root,$config,$auth,$csrf;
    $user=$auth->user(); $flashes=take_flashes(); $templateFile=$root.'/templates/'.$template.'.php';
    if(!is_file($templateFile)) throw new RuntimeException('Template not found.');
    extract($view,EXTR_SKIP);
    require $root.'/templates/layout.php';
}

function csrf_field(): string { global $csrf; return '<input type="hidden" name="_csrf" value="'.e($csrf->token()).'">'; }

/** Amount formatted the Albanian way: 1.234,56 € */
function money(mixed $amount, string $currency='€'): string { return number_format((float)$amount,2,',','.').($currency===''?'':' '.$currency); }

/** Date (Y-m-d or datetime) shown as dd.mm.yyyy. */
function date_sq(mixed $value): string
{
    if($value===null||$value==='')return '—';
    $time=strtotime(substr((string)$value,0,10));
    return $time===false?(string)$value:date('d.m.Y',$time);
}

/** UTC database timestamp shown in the application timezone. */
function datetime_sq(mixed $value): string
{
    if($value===null||$value==='')return '—';
    try{$date=new DateTimeImmutable((string)$value,new DateTimeZone('UTC'));}catch(Exception){return (string)$value;}
    return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d.m.Y H:i');
}

function label(string $domain, mixed $code): string { return Labels::label($domain,(string)$code); }

function badge(string $domain, mixed $code): string
{
    $code=(string)$code;
    return '<span class="badge badge--'.e(Labels::tone($domain,$code)).'">'.e(Labels::label($domain,$code)).'</span>';
}

/** Marks the navigation entry whose path prefix matches the current page. */
function nav_current(string ...$prefixes): string
{
    $path=current_path();
    foreach($prefixes as $prefix)if(str_starts_with($path,$prefix))return ' aria-current="page"';
    return '';
}

/** Streams a generated file to the browser. */
function send_download(string $bytes, string $filename, string $contentType): never
{
    header('Content-Type: '.$contentType);
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',$filename).'"');
    header('Content-Length: '.strlen($bytes));
    header('Cache-Control: private, no-store');
    echo $bytes;
    exit;
}

function send_file(string $path, string $filename, string $contentType, bool $deleteAfter=false): never
{
    if($deleteAfter)register_shutdown_function(static function()use($path){if(is_file($path))@unlink($path);});
    header('Content-Type: '.$contentType);
    header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','-',$filename).'"');
    header('Content-Length: '.filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

/** Rejects anything but a POST carrying a valid CSRF token. */
function require_post(): void
{
    global $csrf;
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||!$csrf->verify(is_string($_POST['_csrf']??null)?$_POST['_csrf']:null)){http_response_code(400);exit('Invalid request.');}
}

/** Runs a write action and turns its outcome into a flash message. Unexpected errors are logged with a reference. */
function run_action(callable $action, string $failure): void
{
    try { $message=$action(); if(is_string($message)&&$message!=='')flash('success',$message); }
    catch(DomainException $e){ flash('error',$e->getMessage()); }
    catch(Throwable $e){ $reference=gmdate('YmdHis').'-'.bin2hex(random_bytes(3)); error_log($failure.' '.$reference."\n".$e->__toString()); flash('error',$failure.' Referenca: '.$reference); }
}

/** Mobile toggle for a filter form; place before <div class="filters-body">. */
function filter_toggle(int $active, string $id='filters-toggle'): string
{
    return '<input type="checkbox" id="'.e($id).'" class="filters-toggle-input"'.($active>0?' checked':'').'><label for="'.e($id).'" class="filters-toggle">Filtrat'.($active>0?' ('.$active.' aktivë)':'').'</label>';
}

/** Hidden form fields that preserve the current query string values. @param array<string,scalar|null> $values */
function hidden_fields(array $values): string
{
    $html='';
    foreach($values as $name=>$value)if($value!==null&&$value!=='')$html.='<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
    return $html;
}
