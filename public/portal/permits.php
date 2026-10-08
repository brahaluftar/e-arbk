<?php
declare(strict_types=1);
$app=require dirname(__DIR__,2).'/bootstrap.php';
$user=$app['auth']->requireBusiness();
$permits=(new App\Repository\PortalRepository($app['pdo']))->permits($user['id']);
render('portal/permits','Lejet e punës',compact('permits'));
