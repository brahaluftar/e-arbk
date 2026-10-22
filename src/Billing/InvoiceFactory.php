<?php
declare(strict_types=1);
namespace App\Billing;

use App\Service\AuditLogger;
use DomainException;
use PDO;

/**
 * The only place that inserts invoices. Every invoice gets a UNIREF (also used as its number unless
 * an external number is given), a CREATED history event and an audit record.
 * Callers own the surrounding transaction.
 */
final class InvoiceFactory
{
    private UnirefSequence $sequences;
    private InvoiceHistory $history;

    public function __construct(private PDO $pdo, private AuditLogger $audit, ?UnirefSequence $sequences = null, ?InvoiceHistory $history = null)
    {
        $this->sequences = $sequences ?? new UnirefSequence($pdo);
        $this->history = $history ?? new InvoiceHistory($pdo);
    }

    /**
     * @param array{business_id:int,business_unit_id?:?int,fiscal_year:int,nace:string,amount:float|string,issued_on:string,due_on:?string,
     *   business_name:string,invoice_number?:?string,notes?:?string,period_start?:?string,period_end?:?string,billed_months?:?int,annual_tariff?:float|string|null,
     *   business_address?:?string,fiscal_number?:?string,registration_number?:?string,registered_nace?:?string,tariff_nace?:?string,nace_description?:?string} $draft
     * @param array<string,mixed> $auditExtra
     * @return array{id:int,uniref:string,invoice_number:string}
     */
    public function issue(array $draft, int $actorId, string $auditAction, array $auditExtra = []): array
    {
        $nace = strtoupper(trim($draft['nace']));
        if (preg_match('/\A[0-9A-Z]{4}\z/', $nace) !== 1) throw new DomainException('Kodi NACE duhet të ketë saktësisht 4 karaktere që të krijohet UNIREF-i.');
        $year = (int) $draft['fiscal_year'];
        if ($year < 2000 || $year > 2100) throw new DomainException('Viti fiskal nuk është valid.');
        $amount = round((float) $draft['amount'], 2);
        if ($amount <= 0) throw new DomainException('Shuma e faturës duhet të jetë pozitive.');
        $uniref = $this->sequences->next($nace);
        $number = trim((string) ($draft['invoice_number'] ?? ''));
        if ($number === '') $number = $uniref;
        $statement = $this->pdo->prepare("INSERT dbo.business_invoices(business_id,business_unit_id,fiscal_year,invoice_number,uniref,amount,issued_on,due_on,notes,created_by_user_id,status_code,period_start,period_end,billed_months,annual_tariff,currency,business_name_snapshot,business_address_snapshot,fiscal_number_snapshot,registration_number_snapshot,registered_nace_code_snapshot,tariff_nace_code_snapshot,nace_description_snapshot)
            OUTPUT inserted.id VALUES(:business,:unit,:year,:number,:uniref,:amount,CONVERT(date,:issued),CONVERT(date,:due),:notes,:actor,'ISSUED',CONVERT(date,:period_start),CONVERT(date,:period_end),:months,:annual,'EUR',:name,:address,:fiscal,:registration,:registered_nace,:tariff_nace,:description)");
        $statement->execute([
            'business' => (int) $draft['business_id'],
            'unit' => isset($draft['business_unit_id']) ? (int) $draft['business_unit_id'] : null,
            'year' => $year,
            'number' => mb_substr($number, 0, 80),
            'uniref' => $uniref,
            'amount' => $amount,
            'issued' => $draft['issued_on'],
            'due' => $draft['due_on'],
            'notes' => $this->text($draft['notes'] ?? null, 500),
            'actor' => $actorId,
            'period_start' => $draft['period_start'] ?? null,
            'period_end' => $draft['period_end'] ?? null,
            'months' => $draft['billed_months'] ?? null,
            'annual' => isset($draft['annual_tariff']) ? round((float) $draft['annual_tariff'], 2) : $amount,
            'name' => mb_substr(trim($draft['business_name']), 0, 255),
            'address' => $this->text($draft['business_address'] ?? null, 500),
            'fiscal' => $this->text($draft['fiscal_number'] ?? null, 30),
            'registration' => $this->text($draft['registration_number'] ?? null, 20),
            'registered_nace' => $this->text($draft['registered_nace'] ?? $nace, 20),
            'tariff_nace' => $this->text($draft['tariff_nace'] ?? null, 20),
            'description' => $this->text($draft['nace_description'] ?? null, 510),
        ]);
        $id = (int) $statement->fetchColumn();
        $statement->closeCursor();
        $this->history->record($id, 'CREATED', ['amount' => $amount, 'uniref' => $uniref], $this->text($draft['notes'] ?? null, 1000), $actorId);
        $this->audit->record($actorId, $auditAction, 'BUSINESS_INVOICE', (string) $id, null, ['business_id' => (int) $draft['business_id'], 'business_unit_id' => $draft['business_unit_id'] ?? null, 'fiscal_year' => $year, 'uniref' => $uniref, 'amount' => $amount] + $auditExtra);
        return ['id' => $id, 'uniref' => $uniref, 'invoice_number' => $number];
    }

    private function text(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
