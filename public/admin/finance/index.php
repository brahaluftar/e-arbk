<?php
declare(strict_types=1);
$app = require dirname(__DIR__, 3) . '/bootstrap.php';
$app['auth']->requireRole(['ADMIN']);
header('Cache-Control: private, no-store');
$year = filter_var($_GET['year'] ?? date('Y'), FILTER_VALIDATE_INT);
if ($year === false || $year < 2000 || $year > 2100) $year = (int)date('Y');
$query = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$repository = new App\Repository\BusinessRepository($app['pdo']);
$financialStats = $repository->financialDashboard($year);
$businesses = $repository->financeBusinesses($year, $query);
$payableInvoices = $repository->payableInvoices();
$annualJobs=$app['pdo']->query('SELECT TOP(20) * FROM dbo.annual_invoice_jobs ORDER BY id DESC')->fetchAll();
render('admin/finance/index', 'Financat', compact('financialStats','businesses','payableInvoices','query','annualJobs'));
