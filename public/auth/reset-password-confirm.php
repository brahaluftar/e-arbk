<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 2) . '/bootstrap.php';
if ($app['auth']->user() !== null) redirect('/admin/dashboard.php');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$token = is_string($_POST['token'] ?? null) ? $_POST['token'] : (is_string($_GET['token'] ?? null) ? $_GET['token'] : '');
$service = new App\Security\PasswordResetService($app['pdo'], $app['config'], new App\Service\GraphMailer($app['config']), new App\Service\AuditLogger($app['pdo']));
$validToken = $service->isValid($token);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$app['csrf']->verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
        http_response_code(400);
        flash('error', 'Kërkesa ka skaduar. Rifreskoni faqen dhe provoni përsëri.');
    } elseif (!$validToken) {
        flash('error', 'Lidhja është e pavlefshme ose ka skaduar. Kërkoni një lidhje të re.');
    } else {
        try {
            if ($service->reset($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirmation'] ?? ''))) {
                flash('success', 'Fjalëkalimi u ndryshua. Mund të hyni me fjalëkalimin e ri.');
                redirect('/auth/login.php');
            }
            $validToken = false;
            flash('error', 'Lidhja është e pavlefshme ose ka skaduar. Kërkoni një lidhje të re.');
        } catch (DomainException $error) {
            flash('error', $error->getMessage());
        }
    }
}
render('auth/reset-password-confirm', 'Konfirmoni fjalëkalimin e ri', ['token' => $token, 'validToken' => $validToken]);