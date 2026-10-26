<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Checks the check character of every invoice UNIREF (dbo.usp_fix_uniref_check_characters).
//   php bin/fix-uniref-check-characters.php           report only
//   php bin/fix-uniref-check-characters.php --apply   correct the wrong ones
$apply = in_array('--apply', array_slice($argv, 1), true);
$app = require dirname(__DIR__) . '/bootstrap.php';
$statement = $app['pdo']->prepare('EXEC dbo.usp_fix_uniref_check_characters @apply=:apply');
$statement->execute(['apply' => $apply ? 1 : 0]);
$rows = $statement->fetchAll();
$statement->closeCursor();
unset($statement);
$checked = 0;
$counts = [];
foreach ($rows as $row) {
    $checked = (int) $row['checked_count'];
    if ($row['result'] === 'SUMMARY') continue;
    $counts[$row['result']] = ($counts[$row['result']] ?? 0) + 1;
    printf("%-12s invoice %-8s %s -> %s\n", $row['result'], $row['invoice_id'], $row['current_uniref'], $row['correct_uniref'] ?? '(base is not 15 characters of 0-9A-Z)');
}
echo "UNIREFs checked: $checked; correct: " . ($checked - array_sum($counts)) . ($counts === [] ? '' : '; ' . implode('; ', array_map(fn($k, $v) => "$k: $v", array_keys($counts), $counts))) . ".\n";
if (!$apply && isset($counts['WRONG'])) echo "Run again with --apply to correct them.\n";
unset($app);
