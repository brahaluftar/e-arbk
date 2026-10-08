<?php
declare(strict_types=1);
$app=require dirname(__DIR__).'/bootstrap.php';
$user=$app['auth']->user();
redirect($user===null?'/auth/login.php':App\Security\Auth::landingPath($user));
