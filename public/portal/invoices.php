<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireBusiness();
$invoices=(new App\Repository\PortalRepository($app['pdo']))->invoices($user['id']);
render('portal/invoices','Faturat e mia',compact('invoices'));
