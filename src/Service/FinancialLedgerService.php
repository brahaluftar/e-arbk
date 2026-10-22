<?php
declare(strict_types=1);

namespace App\Service;

use App\Billing\InvoiceFactory;
use App\Billing\InvoiceHistory;
use App\Billing\PaymentRejectedException;
use DomainException;
use PDO;
use Throwable;

/**
 * Invoices and payments entered by municipal staff: manual invoices, payments, payment reversal (storno),
 * invoice edits and cancellation. Every change is written to the invoice history with its comment.
 */
final class FinancialLedgerService
{
    private InvoiceFactory $factory;
    private InvoiceHistory $history;

    public function __construct(private PDO $pdo, private AuditLogger $audit, ?InvoiceFactory $factory = null, ?InvoiceHistory $history = null)
    {
        $this->history = $history ?? new InvoiceHistory($pdo);
        $this->factory = $factory ?? new InvoiceFactory($pdo, $audit, null, $this->history);
    }

    /** @param array<string,mixed> $input */
    public function createInvoice(array $input, int $userId): int
    {
        $businessId = filter_var($input['business_id'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($input['fiscal_year'] ?? null, FILTER_VALIDATE_INT);
        $invoiceNumber = $this->text($input['invoice_number'] ?? null);
        $amount = $this->amount($input['amount'] ?? null);
        $issuedOn = $this->date($input['issued_on'] ?? null, 'data e faturës');
        $dueOn = $this->text($input['due_on'] ?? null);
        $dueOn = $dueOn === '' ? null : $this->date($dueOn, 'afati i pagesës');
        $notes = $this->text($input['notes'] ?? null);
        if ($businessId === false || $businessId < 1) throw new DomainException('Zgjidhni një biznes të vlefshëm.');
        if ($year === false || $year < 2000 || $year > 2100 || (int)substr($issuedOn, 0, 4) !== $year) throw new DomainException('Viti fiskal duhet të përputhet me datën e faturës.');
        if (mb_strlen($invoiceNumber) > 80) throw new DomainException('Numri i faturës mund të ketë deri në 80 karaktere.');
        if ($dueOn !== null && $dueOn < $issuedOn) throw new DomainException('Afati i pagesës nuk mund të jetë para datës së faturës.');
        if (mb_strlen($notes) > 500) throw new DomainException('Shënimi mund të ketë deri në 500 karaktere.');

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare("SELECT a.REGULATION_ID,a.Emri,a.ADRESA,a.NUMRI_FISKAL,a.NRBIZ,LTRIM(RTRIM(a.NACE_CODE_REG)) registered_nace,COALESCE(LTRIM(RTRIM(a.NACE_CODE_TARIFF)),LTRIM(RTRIM(a.NACE_CODE_REG))) tariff_nace,COALESCE(NULLIF(LTRIM(RTRIM(a.nace_veprimtaria_tariff)),''),a.NACEPERSHKRIMI) description,b.status_code FROM dbo.ARBK_LIST a WITH(UPDLOCK,HOLDLOCK) JOIN dbo.v_business_billing b ON b.business_id=a.REGULATION_ID WHERE a.REGULATION_ID=:id");
            $statement->execute(['id'=>$businessId]);
            $business = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            if (!is_array($business) || $business['status_code'] !== 'ACTIVE') throw new DomainException('Faturë mund të krijohet vetëm për biznes aktiv.');
            $exists = $this->pdo->prepare('SELECT 1 FROM dbo.business_invoices WITH(UPDLOCK,HOLDLOCK) WHERE business_id=:business AND business_unit_id IS NULL AND fiscal_year=:year');
            $exists->execute(['business'=>$businessId,'year'=>$year]);
            if ($exists->fetchColumn() !== false) throw new DomainException('Ky biznes ka tashmë faturë për vitin '.$year.'.');
            $exists->closeCursor();
            $invoice = $this->factory->issue([
                'business_id'=>$businessId,'fiscal_year'=>$year,'nace'=>(string)$business['registered_nace'],'invoice_number'=>$invoiceNumber,'amount'=>$amount,'annual_tariff'=>$amount,
                'issued_on'=>$issuedOn,'due_on'=>$dueOn,'notes'=>$notes,'period_start'=>$year.'-01-01','period_end'=>$year.'-12-31','billed_months'=>12,
                'business_name'=>(string)$business['Emri'],'business_address'=>$business['ADRESA'],'fiscal_number'=>$business['NUMRI_FISKAL'],'registration_number'=>$business['NRBIZ'],
                'registered_nace'=>$business['registered_nace'],'tariff_nace'=>$business['tariff_nace'],'nace_description'=>$business['description'],
            ], $userId, 'INVOICE_CREATED');
            $this->pdo->commit();
            return $invoice['id'];
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @param array<string,mixed> $input */
    public function recordPayment(array $input, int $userId): int
    {
        $invoiceId = filter_var($input['invoice_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = $this->amount($input['amount'] ?? null);
        $paidOn = $this->date($input['paid_on'] ?? null, 'data e pagesës');
        $reference = $this->text($input['reference'] ?? null);
        $notes = $this->text($input['notes'] ?? null);
        if ($invoiceId === false || $invoiceId < 1) throw new DomainException('Zgjidhni një faturë të vlefshme.');
        if (mb_strlen($reference) > 100 || mb_strlen($notes) > 500) throw new DomainException('Referenca ose shënimi është tepër i gjatë.');

        $this->pdo->beginTransaction();
        try {
            $paymentId = $this->applyPayment($invoiceId, $amount, $paidOn, $reference, $notes, $userId, null, false);
            $this->pdo->commit();
            return $paymentId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * Inserts a payment inside the caller's transaction after locking the invoice.
     * @throws PaymentRejectedException when the invoice is cancelled, the amount exceeds the balance, or (with $rejectDuplicates) the same payment exists
     */
    public function applyPayment(int $invoiceId, string $amount, string $paidOn, string $reference, string $notes, int $userId, ?int $importRowId, bool $rejectDuplicates): int
    {
        $state = $this->lockInvoice($invoiceId);
        if ($state['status_code'] === 'CANCELLED') throw new PaymentRejectedException('CANCELLED_INVOICE', 'Fatura është anuluar; pagesa nuk mund të regjistrohet.');
        if ($paidOn < (string)$state['issued_on']) throw new PaymentRejectedException('INVALID', 'Data e pagesës nuk mund të jetë para datës së faturës.');
        if ($rejectDuplicates) {
            $duplicate = $this->pdo->prepare('SELECT 1 FROM dbo.business_invoice_payments WHERE invoice_id=:invoice AND amount=:amount AND paid_on=:paid AND reversed_at IS NULL AND ISNULL(reference,N\'\')=:reference');
            $duplicate->execute(['invoice'=>$invoiceId,'amount'=>$amount,'paid'=>$paidOn,'reference'=>$reference]);
            if ($duplicate->fetchColumn() !== false) throw new PaymentRejectedException('DUPLICATE', 'E njëjta pagesë është regjistruar më parë.');
            $duplicate->closeCursor();
        }
        $remaining = (float)$state['amount'] - (float)$state['paid'];
        if ((float)$amount > $remaining + 0.001) throw new PaymentRejectedException('OVERPAYMENT', 'Pagesa ('.number_format((float)$amount, 2, ',', '.').' €) tejkalon shumën e mbetur të faturës ('.number_format(max(0, $remaining), 2, ',', '.').' €).');
        $statement = $this->pdo->prepare('INSERT dbo.business_invoice_payments(invoice_id,amount,paid_on,reference,notes,recorded_by_user_id,import_row_id) OUTPUT inserted.id VALUES(:invoice,:amount,:date,:reference,:notes,:user,:import_row)');
        $statement->execute(['invoice'=>$invoiceId,'amount'=>$amount,'date'=>$paidOn,'reference'=>$reference===''?null:$reference,'notes'=>$notes===''?null:$notes,'user'=>$userId,'import_row'=>$importRowId]);
        $paymentId = (int)$statement->fetchColumn();
        $statement->closeCursor();
        $this->refreshStatus($invoiceId);
        $this->history->record($invoiceId, 'PAYMENT_RECORDED', ['payment_id'=>$paymentId,'amount'=>$amount,'paid_on'=>$paidOn,'reference'=>$reference], $notes === '' ? null : $notes, $userId);
        $this->audit->record($userId,'INVOICE_PAYMENT_RECORDED','INVOICE_PAYMENT',(string)$paymentId,null,['invoice_id'=>$invoiceId,'amount'=>$amount,'paid_on'=>$paidOn,'import_row_id'=>$importRowId]);
        return $paymentId;
    }

    /** Reverses (storno) a payment entered by mistake; the row stays in the ledger but no longer counts. */
    public function reversePayment(int $paymentId, string $reason, int $userId): int
    {
        $reason = trim($reason);
        if ($paymentId < 1) throw new DomainException('Pagesa nuk është valide.');
        if ($reason === '' || mb_strlen($reason) > 500) throw new DomainException('Shënoni arsyen e stornimit (deri në 500 karaktere).');
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT id,invoice_id,amount,paid_on,reference,reversed_at FROM dbo.business_invoice_payments WITH(UPDLOCK,HOLDLOCK) WHERE id=:id');
            $statement->execute(['id'=>$paymentId]);
            $payment = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            if (!is_array($payment)) throw new DomainException('Pagesa nuk u gjet.');
            if ($payment['reversed_at'] !== null) throw new DomainException('Kjo pagesë është stornuar më parë.');
            $invoiceId = (int)$payment['invoice_id'];
            $this->lockInvoice($invoiceId);
            $this->pdo->prepare('UPDATE dbo.business_invoice_payments SET reversed_at=SYSUTCDATETIME(),reversed_by_user_id=:user,reversal_reason=:reason WHERE id=:id')->execute(['user'=>$userId,'reason'=>$reason,'id'=>$paymentId]);
            $this->refreshStatus($invoiceId);
            $this->history->record($invoiceId, 'PAYMENT_REVERSED', ['payment_id'=>$paymentId,'amount'=>$payment['amount'],'paid_on'=>$payment['paid_on'],'reference'=>$payment['reference']], $reason, $userId);
            $this->audit->record($userId,'INVOICE_PAYMENT_REVERSED','INVOICE_PAYMENT',(string)$paymentId,['amount'=>$payment['amount'],'paid_on'=>$payment['paid_on']],['reversed'=>true],['invoice_id'=>$invoiceId,'reason'=>$reason]);
            $this->pdo->commit();
            return $invoiceId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /**
     * Edits amount, due date and notes. The comment is mandatory and stored with the list of changes.
     * @param array<string,mixed> $input
     */
    public function updateInvoice(int $invoiceId, array $input, int $userId): void
    {
        $comment = $this->comment($input['comment'] ?? null);
        $amount = $this->amount($input['amount'] ?? null);
        $dueOn = $this->text($input['due_on'] ?? null);
        $dueOn = $dueOn === '' ? null : $this->date($dueOn, 'afati i pagesës');
        $notes = $this->text($input['notes'] ?? null);
        if (mb_strlen($notes) > 500) throw new DomainException('Shënimi mund të ketë deri në 500 karaktere.');
        $this->pdo->beginTransaction();
        try {
            $state = $this->lockInvoice($invoiceId);
            if ($state['status_code'] === 'CANCELLED') throw new DomainException('Fatura e anuluar nuk mund të editohet. Rikthejeni së pari.');
            if ($dueOn !== null && $dueOn < (string)$state['issued_on']) throw new DomainException('Afati i pagesës nuk mund të jetë para datës së faturës.');
            if ((float)$amount + 0.001 < (float)$state['paid']) throw new DomainException('Shuma e faturës nuk mund të jetë më e vogël se pagesat e regjistruara ('.number_format((float)$state['paid'], 2, ',', '.').' €).');
            $before = ['amount'=>number_format((float)$state['amount'], 2, '.', ''),'due_on'=>$state['due_on'] === null ? null : substr((string)$state['due_on'], 0, 10),'notes'=>$state['notes'] === null ? '' : (string)$state['notes']];
            $after = ['amount'=>$amount,'due_on'=>$dueOn,'notes'=>$notes];
            $changes = [];
            foreach ($after as $field => $value) if ($before[$field] !== $value) $changes[$field] = ['from'=>$before[$field],'to'=>$value];
            if ($changes === []) throw new DomainException('Nuk ka ndryshime për t’u ruajtur.');
            $this->pdo->prepare('UPDATE dbo.business_invoices SET amount=:amount,due_on=:due,notes=:notes WHERE id=:id')->execute(['amount'=>$amount,'due'=>$dueOn,'notes'=>$notes === '' ? null : $notes,'id'=>$invoiceId]);
            $this->refreshStatus($invoiceId);
            $this->history->record($invoiceId, 'EDITED', $changes, $comment, $userId);
            $this->audit->record($userId,'INVOICE_UPDATED','BUSINESS_INVOICE',(string)$invoiceId,$before,$after,['comment'=>$comment]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Cancels an invoice without valid payments, or restores a cancelled one. */
    public function setCancelled(int $invoiceId, bool $cancel, mixed $comment, int $userId): void
    {
        $comment = $this->comment($comment);
        $this->pdo->beginTransaction();
        try {
            $state = $this->lockInvoice($invoiceId);
            if ($cancel && $state['status_code'] === 'CANCELLED') throw new DomainException('Fatura është anuluar më parë.');
            if (!$cancel && $state['status_code'] !== 'CANCELLED') throw new DomainException('Fatura nuk është e anuluar.');
            if ($cancel && (float)$state['paid'] > 0) throw new DomainException('Fatura ka pagesa. Stornoni pagesat para anulimit.');
            $this->pdo->prepare("UPDATE dbo.business_invoices SET status_code=:status WHERE id=:id")->execute(['status'=>$cancel ? 'CANCELLED' : 'ISSUED','id'=>$invoiceId]);
            if (!$cancel) $this->refreshStatus($invoiceId);
            $this->history->record($invoiceId, $cancel ? 'CANCELLED' : 'RESTORED', null, $comment, $userId);
            $this->audit->record($userId, $cancel ? 'INVOICE_CANCELLED' : 'INVOICE_RESTORED', 'BUSINESS_INVOICE', (string)$invoiceId, ['status'=>$state['status_code']], ['status'=>$cancel ? 'CANCELLED' : 'ISSUED'], ['comment'=>$comment]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** @return array{amount:string,paid:string,issued_on:string,due_on:?string,notes:?string,status_code:string} */
    private function lockInvoice(int $invoiceId): array
    {
        $invoice = $this->pdo->prepare('SELECT id,amount,issued_on,due_on,notes,status_code FROM dbo.business_invoices WITH(UPDLOCK,HOLDLOCK) WHERE id=:id');
        $invoice->execute(['id'=>$invoiceId]);
        $record = $invoice->fetch(PDO::FETCH_ASSOC);
        $invoice->closeCursor();
        if (!is_array($record)) throw new PaymentRejectedException('UNMATCHED', 'Fatura nuk u gjet.');
        $paid = $this->pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM dbo.business_invoice_payments WITH(UPDLOCK,HOLDLOCK) WHERE invoice_id=:id AND reversed_at IS NULL');
        $paid->execute(['id'=>$invoiceId]);
        $record['paid'] = (string)$paid->fetchColumn();
        $paid->closeCursor();
        return $record;
    }

    /** Stored status follows the payments; cancelled invoices keep their status. */
    private function refreshStatus(int $invoiceId): void
    {
        $this->pdo->prepare("UPDATE i SET status_code=b.status_code FROM dbo.business_invoices i JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id WHERE i.id=:id AND i.status_code<>'CANCELLED'")->execute(['id'=>$invoiceId]);
    }

    private function comment(mixed $raw): string
    {
        $comment = $this->text($raw);
        if ($comment === '' || mb_strlen($comment) > 1000) throw new DomainException('Koment është i detyrueshëm: shkruani çfarë ndryshoi dhe pse (deri në 1000 karaktere).');
        return $comment;
    }

    private function text(mixed $raw): string { return is_string($raw) ? trim($raw) : ''; }

    private function amount(mixed $raw): string
    {
        $value = is_scalar($raw) ? trim((string)$raw) : '';
        if (preg_match('/\A([0-9]{1,16})(?:\.([0-9]{1,2}))?\z/D', $value, $parts) !== 1) throw new DomainException('Shuma duhet të jetë numerike dhe të mos kalojë kufirin e lejuar.');
        $whole = ltrim($parts[1], '0');
        $fraction = str_pad($parts[2] ?? '', 2, '0');
        if (($whole === '' || $whole === '0') && $fraction === '00') throw new DomainException('Shuma duhet të jetë pozitive.');
        return ($whole === '' ? '0' : $whole) . '.' . $fraction;
    }

    private function date(mixed $raw, string $label): string
    {
        if (!is_string($raw) || preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $raw) !== 1) throw new DomainException('Vendosni '.$label.' të vlefshme.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) throw new DomainException('Vendosni '.$label.' të vlefshme.');
        return $raw;
    }
}
