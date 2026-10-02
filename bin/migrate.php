<?php
declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$pdo = $app['pdo'];
$directory = dirname(__DIR__) . '/database/migrations';
$pdo->exec("IF OBJECT_ID('dbo.schema_migrations','U') IS NULL CREATE TABLE dbo.schema_migrations (migration varchar(255) NOT NULL PRIMARY KEY, applied_at datetime2(3) NOT NULL DEFAULT SYSUTCDATETIME())");
foreach (glob($directory . '/*.sql') ?: [] as $file) {
    $name = basename($file);
    $check = $pdo->prepare('SELECT 1 FROM dbo.schema_migrations WHERE migration=:migration');
    $check->execute(['migration'=>$name]);
    if ($check->fetchColumn()) { echo "skip $name\n"; continue; }
    $sql = (string) file_get_contents($file);
    $batches = preg_split('/^\s*GO\s*$/mi', $sql) ?: [];
    $pdo->beginTransaction();
    try {
        foreach ($batches as $batch) if (trim($batch) !== '') $pdo->exec($batch);
        $pdo->prepare('INSERT dbo.schema_migrations(migration) VALUES(:migration)')->execute(['migration'=>$name]);
        $pdo->commit(); echo "applied $name\n";
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
