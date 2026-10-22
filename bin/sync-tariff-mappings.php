<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app=require dirname(__DIR__).'/bootstrap.php';
$result=(new App\Service\TariffMappingService($app['pdo'],new App\Service\AuditLogger($app['pdo'])))->synchronize();
echo "Codes filled: {$result['codes']}; activities filled: {$result['activities']}; GUID links filled: {$result['guids']}.\n";
