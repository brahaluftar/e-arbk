<?php
declare(strict_types=1);

$app = require dirname(__DIR__,3).'/bootstrap.php';
$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$accounts = $app['pdo']->query('SELECT id,email,full_name,role_code,is_active,last_login_at,created_at,CONVERT(varchar(18),CAST(row_version AS varbinary(8)),1) version FROM dbo.app_users ORDER BY is_active DESC,full_name,id')->fetchAll();
$id = filter_var($_GET['id'] ?? '0', FILTER_VALIDATE_INT);
if ($id === false || $id < 0) { http_response_code(400); exit('Invalid user ID.'); }
$editing = null;
foreach ($accounts as $account) if ((int)$account['id'] === $id) $editing = $account;
if ($id !== 0 && $editing === null) { http_response_code(404); exit('User not found.'); }
$form = $editing ?? ['id'=>0,'full_name'=>'','email'=>'','role_code'=>'OFFICIAL','is_active'=>1,'version'=>''];
$old = $_SESSION['_user_form'] ?? null;
unset($_SESSION['_user_form']);
if (is_array($old) && (int)($old['id'] ?? -1) === $id) $form = array_merge($form, $old);
$roles = App\Service\UserManagementService::ROLES;
render('admin/users/index','Përdoruesit',compact('accounts','editing','form','roles'));
