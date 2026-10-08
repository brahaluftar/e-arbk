<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$current=$app['auth']->user();if($current!==null) redirect(App\Security\Auth::landingPath($current));
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email=(string)($_POST['email']??'');$ip=request_ip();$limiter=new App\Security\LoginRateLimiter($app['pdo'],$app['config']);
    if(!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);flash('error','Kërkesa ka skaduar. Provoni përsëri.');}
    elseif(!$limiter->allowed($email,$ip)){http_response_code(429);header('Retry-After: '.$app['config']->int('LOGIN_RATE_BLOCK_SECONDS'));flash('error','Shumë tentativa. Provoni përsëri më vonë.');}
    elseif($app['auth']->login($email,(string)($_POST['password']??''))){$limiter->succeeded($email,$ip);redirect(App\Security\Auth::landingPath($app['auth']->user()??['role_code'=>'BUSINESS']));}
    else {$limiter->failed($email,$ip);usleep(250000);flash('error','Emaili ose fjalëkalimi nuk është i saktë.');}
}
render('auth/login','Hyrje');
