<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$app = require dirname(__DIR__) . '/bootstrap.php';
[$script,$email,$name,$password] = array_pad($argv,4,null);
$email = trim((string)$email);
$name = trim((string)$name);
$password = (string)$password;
if ($email === '' || $name === '' || mb_strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Usage: php bin/create-admin.php email \"Full Name\" \"password (12-72 bytes)\"\n");
    exit(1);
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 254 || mb_strlen($name) > 200) {
    fwrite(STDERR, "The email address or name is not valid.\n");
    exit(1);
}
$normalized = mb_strtoupper($email, 'UTF-8');
$exists = $app['pdo']->prepare('SELECT 1 FROM dbo.app_users WHERE normalized_email=:email');
$exists->execute(['email'=>$normalized]);
if ($exists->fetchColumn() !== false) {
    fwrite(STDERR, "An account with this email already exists. Use the Users screen to change it.\n");
    exit(1);
}
$exists->closeCursor();
$statement=$app['pdo']->prepare("INSERT dbo.app_users(email,normalized_email,password_hash,full_name,role_code) VALUES(:email,:normalized,:hash,:name,'ADMIN')");
$statement->execute(['email'=>$email,'normalized'=>$normalized,'hash'=>password_hash($password,PASSWORD_DEFAULT),'name'=>$name]);
echo "Administrator created.\n";
