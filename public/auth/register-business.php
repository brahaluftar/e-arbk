<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
if($app['auth']->user()!==null)redirect(App\Security\Auth::landingPath($app['auth']->user()));
$token=trim((string)($_REQUEST['token']??''));$service=new App\Security\BusinessRegistrationService($app['pdo'],new App\Service\AuditLogger($app['pdo']));$invite=$service->inspect($token);
if($invite===null){http_response_code(410);render('auth/register-business','Regjistrimi',['invite'=>null,'token'=>'']);return;}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$app['csrf']->verify($_POST['_csrf']??null)){http_response_code(400);exit('Invalid request.');}
    try{$service->register($token,(string)($_POST['full_name']??''),(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));flash('success','Llogaria u krijua. Tani mund të hyni.');redirect('/auth/login.php');}
    catch(DomainException $e){flash('error',$e->getMessage());}
}
render('auth/register-business','Regjistrimi',compact('invite','token'));
