<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
if($app['auth']->user()!==null) redirect('/public/admin/dashboard.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);flash('error','Kërkesa ka skaduar. Provoni përsëri.');}
    elseif($app['auth']->login((string)($_POST['email']??''),(string)($_POST['password']??''))) redirect('/public/admin/dashboard.php');
    else {usleep(250000);flash('error','Emaili ose fjalëkalimi nuk është i saktë.');}
}
render('auth/login','Hyrje');
