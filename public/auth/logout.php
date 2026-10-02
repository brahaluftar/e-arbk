<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
$app['auth']->logout();redirect('/public/auth/login.php');
