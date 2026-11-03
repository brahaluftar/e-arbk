<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// DESTRUCTIVE: deletes every invoice with its payments, payment imports, history, appeals, annual jobs and
// invoice emails, resets the UNIREF sequences and removes the old PDF/XLSX export files (database/manual/truncate_all_invoices_and_details.sql).
//   php bin/reset-invoices.php --confirm
if (!in_array('--confirm', array_slice($argv, 1), true)) {
    fwrite(STDERR, "This deletes ALL invoices, payments and annual invoice jobs. Back up the database, then run:\n  php bin/reset-invoices.php --confirm\n");
    exit(1);
}
$root = dirname(__DIR__);
$app = require $root . '/bootstrap.php';
$statement = $app['pdo']->query((string) file_get_contents($root . '/database/manual/truncate_all_invoices_and_details.sql'));
foreach ($statement->fetchAll() as $row) printf("%-26s %s rows\n", $row['table_name'], $row['row_count']);
$statement->closeCursor();
unset($statement);
// Exports rendered before the reset (and PDFs cached by older versions) belong to deleted invoices.
$removed = 0;
foreach (['job-exports', 'invoices'] as $folder) {
    $directory = $root . '/var/documents/' . $folder;
    if (!is_dir($directory)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) { rmdir($file->getPathname()); continue; }
        if (!unlink($file->getPathname())) throw new RuntimeException('Cannot delete ' . $file->getPathname());
        $removed++;
    }
}
echo "Invoice files removed: $removed.\nAll invoices were reset; generate them again from Financat -> Faturimi vjetor.\n";
unset($app);
