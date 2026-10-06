<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 3) . '/bootstrap.php';
$app['auth']->requireRole(['ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$app['csrf']->verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
    http_response_code(400);
    exit('Invalid request.');
}
$year = filter_var($_POST['fiscal_year'] ?? date('Y'), FILTER_VALIDATE_INT);
if ($year === false || $year < 2000 || $year > 2100) $year = (int)date('Y');
try {
    (new App\Service\FinancialLedgerService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->recordPayment($_POST,(int)$app['auth']->user()['id']);
    flash('success','Pagesa u regjistrua.');
} catch (DomainException $error) {
    flash('error',$error->getMessage());
} catch (Throwable $error) {
    error_log($error->__toString());
    flash('error','Pagesa nuk mund të ruhej.');
}
redirect('/admin/finance/index.php?'.http_build_query(['year'=>$year]));