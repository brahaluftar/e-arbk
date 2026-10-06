<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 3) . '/bootstrap.php';
$user = $app['auth']->requireRole(['ADMIN']);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$app['csrf']->verify(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
    http_response_code(400);
    exit('Invalid request.');
}
$year = filter_var($_POST['fiscal_year'] ?? date('Y'), FILTER_VALIDATE_INT);
if ($year === false || $year < 2000 || $year > 2100) $year = (int)date('Y');
try {
    (new App\Service\FinancialLedgerService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->createInvoice($_POST,$user['id']);
    flash('success','Fatura u regjistrua.');
} catch (DomainException $error) {
    flash('error',$error->getMessage());
} catch (PDOException $error) {
    if (in_array((int)($error->errorInfo[1] ?? 0),[2601,2627],true)) flash('error','Ky biznes ka faturë për vitin ose numri i faturës është përdorur.');
    else { error_log($error->__toString()); flash('error','Fatura nuk mund të ruhej.'); }
} catch (Throwable $error) {
    error_log($error->__toString());
    flash('error','Fatura nuk mund të ruhej.');
}
redirect('/admin/finance/index.php?'.http_build_query(['year'=>$year]));