<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 2) . '/bootstrap.php';
if ($app['auth']->user() !== null) redirect('/admin/dashboard.php');
$delivery = null;
$service = new App\Security\PasswordResetService($app['pdo'], $app['config'], new App\Service\GraphMailer($app['config']), new App\Service\AuditLogger($app['pdo']));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$app['csrf']->verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
        http_response_code(400);
        flash('error', 'Kërkesa ka skaduar. Provoni përsëri.');
    } else {
        try {
            $delivery = $service->prepareRequest((string) ($_POST['email'] ?? ''), request_ip());
        } catch (Throwable $error) {
            error_log('Password reset request failed: ' . $error->getMessage());
        }
        flash('success', 'Nëse ekziston një llogari aktive me atë email, do të dërgohet një lidhje për rivendosjen e fjalëkalimit.');
    }
}
render('auth/reset-password', 'Rivendosja e fjalëkalimit');
if ($delivery !== null) {
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else {
        while (ob_get_level() > 0) @ob_end_flush();
        flush();
    }
    try {
        $service->sendPrepared($delivery);
    } catch (Throwable $error) {
        error_log('Password reset email delivery failed: ' . $error->getMessage());
    }
}