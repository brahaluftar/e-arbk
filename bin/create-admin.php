<?php
declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
if (PHP_SAPI !== 'cli') exit("CLI only.\n");
[$script,$email,$name,$password] = array_pad($argv,4,null);
if (!$email || !$name || !$password || strlen($password)<12) exit("Usage: php bin/create-admin.php email \"Full Name\" \"password (12+ chars)\"\n");
$statement=$app['pdo']->prepare("INSERT dbo.app_users(email,normalized_email,password_hash,full_name,role_code) VALUES(:email,:normalized,:hash,:name,'ADMIN')");
$statement->execute(['email'=>$email,'normalized'=>mb_strtoupper(trim($email),'UTF-8'),'hash'=>password_hash($password,PASSWORD_DEFAULT),'name'=>$name]);
echo "Administrator created.\n";
