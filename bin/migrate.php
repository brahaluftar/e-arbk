<?php
declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap.php';
$pdo = $app['pdo'];
$directory = dirname(__DIR__) . '/database/migrations';
$pdo->exec("IF OBJECT_ID('dbo.schema_migrations','U') IS NULL CREATE TABLE dbo.schema_migrations (migration varchar(255) NOT NULL PRIMARY KEY, applied_at datetime2(3) NOT NULL DEFAULT SYSUTCDATETIME())");
$appliedStatement = $pdo->query('SELECT migration FROM dbo.schema_migrations');
$applied = $appliedStatement->fetchAll(PDO::FETCH_COLUMN);
$appliedStatement->closeCursor();
unset($appliedStatement);
foreach (glob($directory . '/*.sql') ?: [] as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) { echo "skip $name\n"; continue; }
    $sql = (string) file_get_contents($file);
    $batches = preg_split('/^\s*GO\s*$/mi', $sql) ?: [];
    $pdo->beginTransaction();
    try {
        foreach ($batches as $batch) if (trim($batch) !== '') $pdo->exec($batch);
        $insert = $pdo->prepare('INSERT dbo.schema_migrations(migration) VALUES(:migration)');
        $insert->execute(['migration'=>$name]);
        $insert->closeCursor();
        unset($insert);
        $pdo->commit(); echo "applied $name\n";
        $applied[] = $name;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

// Release ODBC resources before PHP's global shutdown. Some Linux pdo_sqlsrv
// builds can abort while destroying an open statement after its PDO connection.
$completionFile = getenv('ARBK_COMPLETION_FILE');
if ($completionFile !== false && $completionFile !== '') {
    if (file_put_contents($completionFile, "complete\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write the migration completion marker.');
    }
}
unset($pdo, $app);
