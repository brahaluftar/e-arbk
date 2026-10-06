<?php
declare(strict_types=1);

$app = require dirname(__DIR__,3).'/bootstrap.php';
$user = $app['auth']->requireRole(['ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$app['csrf']->verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
    http_response_code(400); exit('Invalid request.');
}
$id = filter_var($_POST['id'] ?? '0', FILTER_VALIDATE_INT);
if ($id === false || $id < 0) { http_response_code(400); exit('Invalid user ID.'); }
try {
    (new App\Service\UserManagementService($app['pdo'],new App\Service\AuditLogger($app['pdo']),$app['config']))->save($_POST,$user['id']);
    unset($_SESSION['_user_form']);
    flash('success', $id === 0 ? 'Përdoruesi u krijua. Dorëzojani kredencialet zyrtarit në mënyrë të sigurt.' : 'Të dhënat e përdoruesit u ruajtën.');
    redirect('/admin/users/index.php');
} catch (DomainException $e) {
    flash('error',$e->getMessage());
} catch (PDOException $e) {
    if (in_array((int)($e->errorInfo[1] ?? 0),[2601,2627],true)) flash('error','Ky email është përdorur nga një llogari tjetër.');
    else { error_log($e->__toString()); flash('error','Përdoruesi nuk mund të ruhej. Provoni përsëri.'); }
} catch (Throwable $e) {
    error_log($e->__toString());
    flash('error','Përdoruesi nuk mund të ruhej. Provoni përsëri.');
}
// Never retain passwords in session or redisplay them after a validation failure.
$_SESSION['_user_form'] = ['id'=>$id];
foreach (['full_name','email','role_code','is_active','version'] as $field) {
    if (is_string($_POST[$field] ?? null)) $_SESSION['_user_form'][$field] = $_POST[$field];
}
redirect('/admin/users/index.php'.($id ? '?id='.$id : ''));
