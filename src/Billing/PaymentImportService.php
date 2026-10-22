<?php
declare(strict_types=1);
namespace App\Billing;

use App\Import\XlsxRowReader;
use App\Service\AuditLogger;
use App\Service\FinancialLedgerService;
use DateTimeImmutable;
use DomainException;
use Generator;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Applies the daily payment reports received from the Finance Directorate (XLSX or CSV).
 * Each row is matched to an invoice by UNIREF (or invoice number, or a UNIREF found in the description)
 * and applied as a payment; rows that cannot be applied safely are kept for review with the reason.
 */
final class PaymentImportService
{
    public const MAX_ROWS = 20000;

    /** Accepted header names (compared after normalize()). */
    private const COLUMNS = [
        'uniref' => ['uniref', 'unirefi', 'referencaunike', 'refunike', 'paymentreference', 'pozivnabroj'],
        'invoice_number' => ['numriifatures', 'nrfatures', 'nrifatures', 'fatura', 'invoicenumber', 'invoiceno', 'brojfakture'],
        'amount' => ['shuma', 'shumaepaguar', 'shumapaguar', 'shumaeur', 'vlera', 'amount', 'paidamount', 'iznos'],
        'paid_on' => ['data', 'datapageses', 'dataepageses', 'datatransaksionit', 'paidon', 'paymentdate', 'date', 'datum'],
        'reference' => ['referenca', 'nrreferences', 'referencabankare', 'idtransaksionit', 'nrtransaksionit', 'transactionid', 'reference'],
        'payer' => ['paguesi', 'emripaguesit', 'klienti', 'emribiznesit', 'payer', 'uplatilac'],
        'description' => ['pershkrimi', 'pershkrim', 'qellimi', 'qellimiipageses', 'detajet', 'description', 'opis'],
    ];

    private FinancialLedgerService $ledger;
    private UnirefGenerator $unirefs;

    public function __construct(private PDO $pdo, private AuditLogger $audit, ?FinancialLedgerService $ledger = null, private XlsxRowReader $reader = new XlsxRowReader())
    {
        $this->ledger = $ledger ?? new FinancialLedgerService($pdo, $audit);
        $this->unirefs = new UnirefGenerator();
    }

    /** Imports one report and returns the batch ID. */
    public function import(string $path, string $originalName, int $userId): int
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'csv'], true)) throw new DomainException('Lejohen vetëm raporte XLSX ose CSV.');
        $hash = hash_file('sha256', $path);
        if ($hash === false) throw new RuntimeException('Raporti nuk mund të lexohej.');
        $existing = $this->pdo->prepare('SELECT id FROM dbo.payment_import_batches WHERE file_sha256=:hash');
        $existing->execute(['hash' => $hash]);
        if ($existing->fetchColumn() !== false) throw new DomainException('Ky raport pagesash është importuar më parë.');
        $existing->closeCursor();

        try {
            $insert = $this->pdo->prepare('INSERT dbo.payment_import_batches(original_filename,file_sha256,uploaded_by_user_id) OUTPUT inserted.id VALUES(:name,:hash,:user)');
            $insert->execute(['name' => mb_substr(basename($originalName), 0, 255), 'hash' => $hash, 'user' => $userId]);
            $batchId = (int)$insert->fetchColumn();
            $insert->closeCursor();
        } catch (PDOException $e) {
            if (in_array((int)($e->errorInfo[1] ?? 0), [2601, 2627], true)) throw new DomainException('Ky raport pagesash është importuar më parë.');
            throw $e;
        }

        try {
            $columns = null;
            $count = 0;
            foreach ($this->rows($path, $extension) as $rowNumber => $row) {
                if ($columns === null) {
                    if ($this->blank($row)) continue;
                    $columns = $this->columns($row);
                    continue;
                }
                if ($this->blank($row)) continue;
                if (++$count > self::MAX_ROWS) throw new DomainException('Raporti ka më shumë se '.self::MAX_ROWS.' rreshta. Ndajeni në disa skedarë.');
                $this->processRow($batchId, $rowNumber, $this->values($row, $columns), $userId);
            }
            if ($columns === null) throw new DomainException('Raporti është i zbrazët.');
            $summary = $this->finish($batchId);
            $this->audit->record($userId, 'PAYMENT_IMPORT_COMPLETED', 'PAYMENT_IMPORT_BATCH', (string)$batchId, null, $summary + ['filename' => basename($originalName)]);
            return $batchId;
        } catch (Throwable $e) {
            $message = $e instanceof DomainException ? $e->getMessage() : 'Gabim teknik gjatë përpunimit.';
            $this->pdo->prepare("UPDATE dbo.payment_import_batches SET status_code='FAILED',error_message=:error,completed_at=SYSUTCDATETIME() WHERE id=:id")->execute(['error' => mb_substr($message, 0, 1900), 'id' => $batchId]);
            $this->finishCounters($batchId);
            throw $e;
        }
    }

    /** @param array<string,?string> $data */
    private function processRow(int $batchId, int $rowNumber, array $data, int $userId): void
    {
        $amount = self::parseAmount($data['amount']);
        $paidOn = self::parseDate($data['paid_on']);
        $uniref = $data['uniref'] !== null ? strtoupper(preg_replace('/\s+/', '', $data['uniref']) ?? '') : null;
        if ($uniref === '' ) $uniref = null;
        if ($uniref === null) $uniref = self::findUniref((string)$data['reference'], (string)$data['description'], (string)$data['invoice_number']);
        $number = $data['invoice_number'] !== null ? mb_substr(trim($data['invoice_number']), 0, 80) : null;
        $reference = mb_substr(trim((string)$data['reference']), 0, 100);
        $row = ['batch' => $batchId, 'row' => $rowNumber, 'uniref' => $uniref === null ? null : mb_substr($uniref, 0, 40), 'number' => $number === '' ? null : $number, 'amount' => $amount, 'paid_on' => $paidOn, 'reference' => $reference === '' ? null : $reference, 'payer' => $data['payer'] === null ? null : mb_substr(trim($data['payer']), 0, 255)];

        $problem = null;
        if ($amount === null) $problem = 'Shuma mungon ose nuk është valide.';
        elseif ($paidOn === null) $problem = 'Data e pagesës mungon ose nuk është valide.';
        elseif ($uniref === null && $row['number'] === null) $problem = 'Mungon UNIREF-i ose numri i faturës.';
        elseif ($uniref !== null && !$this->unirefs->isValid($uniref)) $problem = 'UNIREF-i nuk është valid (shifra kontrolluese nuk përputhet).';
        if ($problem !== null) { $this->insertRow($row + ['status' => 'INVALID', 'message' => $problem, 'invoice' => null]); return; }

        $invoiceId = $this->findInvoice($uniref, $row['number']);
        if ($invoiceId === null) { $this->insertRow($row + ['status' => 'UNMATCHED', 'message' => 'Nuk u gjet faturë me këtë UNIREF / numër.', 'invoice' => null]); return; }

        $this->pdo->beginTransaction();
        try {
            $rowId = $this->insertRow($row + ['status' => 'APPLIED', 'message' => null, 'invoice' => $invoiceId]);
            try {
                $paymentId = $this->ledger->applyPayment($invoiceId, $amount, $paidOn, $reference, 'Importuar nga raporti i Drejtorisë së Financave.', $userId, $rowId, true);
                $this->pdo->prepare('UPDATE dbo.payment_import_rows SET payment_id=:payment WHERE id=:id')->execute(['payment' => $paymentId, 'id' => $rowId]);
            } catch (PaymentRejectedException $rejected) {
                $this->pdo->prepare('UPDATE dbo.payment_import_rows SET status_code=:status,message=:message WHERE id=:id')->execute(['status' => $rejected->reason, 'message' => mb_substr($rejected->getMessage(), 0, 500), 'id' => $rowId]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    private function findInvoice(?string $uniref, ?string $number): ?int
    {
        if ($uniref !== null) {
            $statement = $this->pdo->prepare('SELECT id FROM dbo.business_invoices WHERE uniref=:uniref');
            $statement->execute(['uniref' => $uniref]);
            $id = $statement->fetchColumn();
            $statement->closeCursor();
            if ($id !== false) return (int)$id;
        }
        if ($number !== null) {
            $statement = $this->pdo->prepare('SELECT id FROM dbo.business_invoices WHERE invoice_number=:number');
            $statement->execute(['number' => $number]);
            $id = $statement->fetchColumn();
            $statement->closeCursor();
            if ($id !== false) return (int)$id;
        }
        return null;
    }

    /** @param array<string,mixed> $row */
    private function insertRow(array $row): int
    {
        $statement = $this->pdo->prepare('INSERT dbo.payment_import_rows(batch_id,source_row_number,uniref,invoice_number,amount,paid_on,reference,payer_name,status_code,message,invoice_id) OUTPUT inserted.id VALUES(:batch,:row,:uniref,:number,:amount,:paid_on,:reference,:payer,:status,:message,:invoice)');
        $statement->execute($row);
        $id = (int)$statement->fetchColumn();
        $statement->closeCursor();
        return $id;
    }

    /** @return array<string,int|float> */
    private function finish(int $batchId): array
    {
        $summary = $this->finishCounters($batchId);
        $this->pdo->prepare("UPDATE dbo.payment_import_batches SET status_code='COMPLETED',completed_at=SYSUTCDATETIME() WHERE id=:id")->execute(['id' => $batchId]);
        return $summary;
    }

    /** @return array<string,int|float> */
    private function finishCounters(int $batchId): array
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) total,SUM(CASE WHEN status_code='APPLIED' THEN 1 ELSE 0 END) applied,SUM(CASE WHEN status_code='DUPLICATE' THEN 1 ELSE 0 END) duplicate,SUM(CASE WHEN status_code NOT IN('APPLIED','DUPLICATE') THEN 1 ELSE 0 END) review,COALESCE(SUM(CASE WHEN status_code='APPLIED' THEN amount ELSE 0 END),0) amount FROM dbo.payment_import_rows WHERE batch_id=:id");
        $statement->execute(['id' => $batchId]);
        $s = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $statement->closeCursor();
        $summary = ['total' => (int)($s['total'] ?? 0), 'applied' => (int)($s['applied'] ?? 0), 'duplicate' => (int)($s['duplicate'] ?? 0), 'review' => (int)($s['review'] ?? 0), 'amount' => (float)($s['amount'] ?? 0)];
        $this->pdo->prepare('UPDATE dbo.payment_import_batches SET total_rows=:total,applied_rows=:applied,duplicate_rows=:duplicate,review_rows=:review,applied_amount=:amount WHERE id=:id')->execute($summary + ['id' => $batchId]);
        return $summary;
    }

    /** @return Generator<int,list<?string>> keyed by the 1-based source row number */
    private function rows(string $path, string $extension): Generator
    {
        if ($extension === 'xlsx') {
            $number = 0;
            foreach ($this->reader->rows($path) as $row) { $number++; yield $number => array_values($row); }
            return;
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('Raporti nuk mund të lexohej.');
        try {
            $first = (string)fgets($handle);
            rewind($handle);
            $delimiter = ';';
            $best = -1;
            foreach ([';', ',', "\t"] as $candidate) { $found = substr_count($first, $candidate); if ($found > $best) { $best = $found; $delimiter = $candidate; } }
            $number = 0;
            while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                $number++;
                yield $number => array_map(static function ($value) use ($number): ?string {
                    if ($value === null) return null;
                    $value = (string)$value;
                    if ($number === 1) $value = preg_replace('/\A\xEF\xBB\xBF/', '', $value) ?? $value;
                    if (!mb_check_encoding($value, 'UTF-8')) $value = (string)iconv('Windows-1250', 'UTF-8//IGNORE', $value);
                    return $value;
                }, $row);
            }
        } finally { fclose($handle); }
    }

    /** @param list<?string> $header @return array<string,int> */
    private function columns(array $header): array
    {
        $columns = [];
        foreach ($header as $index => $title) {
            $key = self::normalizeHeader((string)$title);
            foreach (self::COLUMNS as $field => $aliases) if (!isset($columns[$field]) && in_array($key, $aliases, true)) $columns[$field] = $index;
        }
        if (!isset($columns['amount']) || !isset($columns['paid_on'])) throw new DomainException('Raporti duhet të ketë kolonat e shumës (Shuma) dhe të datës (Data e pagesës).');
        if (!isset($columns['uniref']) && !isset($columns['invoice_number']) && !isset($columns['description']) && !isset($columns['reference'])) throw new DomainException('Raporti duhet të ketë kolonën UNIREF, numrin e faturës ose përshkrimin e pagesës.');
        return $columns;
    }

    /** @param list<?string> $row @param array<string,int> $columns @return array<string,?string> */
    private function values(array $row, array $columns): array
    {
        $values = [];
        foreach (array_keys(self::COLUMNS) as $field) {
            $value = isset($columns[$field]) ? ($row[$columns[$field]] ?? null) : null;
            $value = $value === null ? null : trim((string)$value);
            $values[$field] = $value === '' ? null : $value;
        }
        return $values;
    }

    /** @param list<?string> $row */
    private function blank(array $row): bool
    {
        foreach ($row as $value) if ($value !== null && trim((string)$value) !== '') return false;
        return true;
    }

    public static function normalizeHeader(string $title): string
    {
        $title = mb_strtolower(trim($title), 'UTF-8');
        $title = strtr($title, ['ë' => 'e', 'ç' => 'c', 'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd']);
        return preg_replace('/[^a-z0-9]/', '', $title) ?? '';
    }

    /** Parses 1.234,56 / 1,234.56 / 1234.56 / 1234,56 / Excel numbers. @return ?string amount with two decimals */
    public static function parseAmount(?string $value): ?string
    {
        if ($value === null) return null;
        $value = str_replace(["\u{00A0}", ' ', '€', 'EUR', 'eur'], '', trim($value));
        if ($value === '' || preg_match('/\A[0-9.,]+\z/', $value) !== 1) return null;
        $lastDot = strrpos($value, '.');
        $lastComma = strrpos($value, ',');
        if ($lastDot !== false && $lastComma !== false) {
            $decimal = $lastDot > $lastComma ? '.' : ',';
            $value = str_replace($decimal === '.' ? ',' : '.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($lastComma !== false) {
            $value = preg_match('/\A\d{1,3}(,\d{3})+\z/', $value) === 1 ? str_replace(',', '', $value) : str_replace(',', '.', $value);
        } elseif ($lastDot !== false && preg_match('/\A\d{1,3}(\.\d{3}){2,}\z/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        if (!is_numeric($value) || substr_count($value, '.') > 1) return null;
        $amount = round((float)$value, 2);
        if ($amount <= 0 || $amount >= 1e16) return null;
        return number_format($amount, 2, '.', '');
    }

    /** Parses d.m.Y, d/m/Y, d-m-Y, Y-m-d (optionally with a time) and Excel serial dates. */
    public static function parseDate(?string $value): ?string
    {
        if ($value === null) return null;
        $value = trim($value);
        if (preg_match('/\A\d{4,5}(?:\.\d+)?\z/', $value) === 1) {
            $serial = (float)$value;
            if ($serial < 30000 || $serial > 80000) return null;
            return gmdate('Y-m-d', (int)round(($serial - 25569) * 86400));
        }
        $value = preg_replace('/[ T]\d{1,2}:\d{2}(:\d{2})?(\.\d+)?\z/', '', $value) ?? $value;
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'd-m-Y', 'd.m.y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                $year = (int)$date->format('Y');
                return $year >= 2000 && $year <= 2100 ? $date->format('Y-m-d') : null;
            }
        }
        return null;
    }

    /** First UNIREF-looking value in free text (bank description or reference). */
    public static function findUniref(string ...$texts): ?string
    {
        foreach ($texts as $text) {
            if (preg_match('/PREAH[0-9A-Z]{11}/', strtoupper(preg_replace('/\s+/', '', $text) ?? ''), $match) === 1) return $match[0];
        }
        return null;
    }
}
