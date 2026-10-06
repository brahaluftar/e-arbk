<?php
declare(strict_types=1);

namespace App\Service;

use DomainException;
use PDO;
use Throwable;

final class FinancialLedgerService
{
    public function __construct(private PDO $pdo, private AuditLogger $audit) {}

    /** @param array<string,mixed> $input */
    public function createInvoice(array $input, int $userId): int
    {
        $businessId = filter_var($input['business_id'] ?? null, FILTER_VALIDATE_INT);
        $year = filter_var($input['fiscal_year'] ?? null, FILTER_VALIDATE_INT);
        $invoiceNumber = trim(is_string($input['invoice_number'] ?? null) ? $input['invoice_number'] : '');
        $amount = $this->amount($input['amount'] ?? null);
        $issuedOn = $this->date($input['issued_on'] ?? null, 'data e faturës');
        $dueOn = trim(is_string($input['due_on'] ?? null) ? $input['due_on'] : '');
        $dueOn = $dueOn === '' ? null : $this->date($dueOn, 'afati i pagesës');
        $notes = trim(is_string($input['notes'] ?? null) ? $input['notes'] : '');
        if ($businessId === false || $businessId < 1) throw new DomainException('Zgjidhni një biznes të vlefshëm.');
        if ($year === false || $year < 2000 || $year > 2100 || (int)substr($issuedOn, 0, 4) !== $year) throw new DomainException('Viti fiskal duhet të përputhet me datën e faturës.');
        if ($invoiceNumber === '' || mb_strlen($invoiceNumber) > 80) throw new DomainException('Numri i faturës është i detyrueshëm (deri në 80 karaktere).');
        if ($dueOn !== null && $dueOn < $issuedOn) throw new DomainException('Afati i pagesës nuk mund të jetë para datës së faturës.');
        if (mb_strlen($notes) > 500) throw new DomainException('Shënimi mund të ketë deri në 500 karaktere.');

        $this->pdo->beginTransaction();
        try {
            $business = $this->pdo->prepare("SELECT a.REGULATION_ID,COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) status FROM dbo.ARBK_LIST a WITH(UPDLOCK,HOLDLOCK) LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID WHERE a.REGULATION_ID=:id");
            $business->execute(['id'=>$businessId]);
            $record = $business->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record) || $record['status'] !== 'ACTIVE') throw new DomainException('Faturë mund të krijohet vetëm për biznes aktiv.');
            $statement = $this->pdo->prepare('INSERT dbo.business_invoices(business_id,fiscal_year,invoice_number,amount,issued_on,due_on,notes,created_by_user_id) OUTPUT inserted.id VALUES(:business,:year,:number,:amount,:issued,:due,:notes,:user)');
            $statement->execute(['business'=>$businessId,'year'=>$year,'number'=>$invoiceNumber,'amount'=>$amount,'issued'=>$issuedOn,'due'=>$dueOn,'notes'=>$notes===''?null:$notes,'user'=>$userId]);
            $invoiceId = (int)$statement->fetchColumn();
            $this->audit->record($userId,'INVOICE_CREATED','BUSINESS_INVOICE',(string)$invoiceId,null,['business_id'=>$businessId,'fiscal_year'=>$year,'invoice_number'=>$invoiceNumber,'amount'=>$amount]);
            $this->pdo->commit();
            return $invoiceId;
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
        $reference = trim(is_string($input['reference'] ?? null) ? $input['reference'] : '');
        $notes = trim(is_string($input['notes'] ?? null) ? $input['notes'] : '');
        if ($invoiceId === false || $invoiceId < 1) throw new DomainException('Zgjidhni një faturë të vlefshme.');
        if (mb_strlen($reference) > 100 || mb_strlen($notes) > 500) throw new DomainException('Referenca ose shënimi është tepër i gjatë.');

        $this->pdo->beginTransaction();
        try {
            $invoice = $this->pdo->prepare('SELECT id,amount,issued_on FROM dbo.business_invoices WITH(UPDLOCK,HOLDLOCK) WHERE id=:id');
            $invoice->execute(['id'=>$invoiceId]);
            $record = $invoice->fetch(PDO::FETCH_ASSOC);
            if (!is_array($record)) throw new DomainException('Fatura nuk u gjet.');
            if ($paidOn < (string)$record['issued_on']) throw new DomainException('Data e pagesës nuk mund të jetë para datës së faturës.');
            $paid = $this->pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM dbo.business_invoice_payments WITH(UPDLOCK,HOLDLOCK) WHERE invoice_id=:id');
            $paid->execute(['id'=>$invoiceId]);
            $remaining = (float)$record['amount'] - (float)$paid->fetchColumn();
            if ($amount > $remaining + 0.001) throw new DomainException('Pagesa tejkalon shumën e mbetur të faturës.');
            $statement = $this->pdo->prepare('INSERT dbo.business_invoice_payments(invoice_id,amount,paid_on,reference,notes,recorded_by_user_id) OUTPUT inserted.id VALUES(:invoice,:amount,:date,:reference,:notes,:user)');
            $statement->execute(['invoice'=>$invoiceId,'amount'=>$amount,'date'=>$paidOn,'reference'=>$reference===''?null:$reference,'notes'=>$notes===''?null:$notes,'user'=>$userId]);
            $paymentId = (int)$statement->fetchColumn();
            $this->audit->record($userId,'INVOICE_PAYMENT_RECORDED','INVOICE_PAYMENT',(string)$paymentId,null,['invoice_id'=>$invoiceId,'amount'=>$amount,'paid_on'=>$paidOn]);
            $this->pdo->commit();
            return $paymentId;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

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