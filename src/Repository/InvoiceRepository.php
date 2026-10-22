<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;
use PDOStatement;

/** Read models for invoices, payments, debts and payment imports. Balances always come from dbo.v_invoice_balance. */
final class InvoiceRepository
{
    public const STATUS_FILTERS = ['ISSUED', 'PARTIALLY_PAID', 'PAID', 'CANCELLED', 'OVERDUE'];

    /**
     * Outstanding balance of the same billing subject (business, or the same unit) on earlier invoices.
     * Expects the invoice alias "i"; adds column previous_debt through alias "d".
     */
    private const PREVIOUS_DEBT = "OUTER APPLY(SELECT SUM(pb.outstanding) previous_debt FROM dbo.v_invoice_balance pb
        WHERE pb.business_id=i.business_id AND pb.invoice_id<>i.id
          AND ((i.business_unit_id IS NULL AND pb.business_unit_id IS NULL) OR pb.business_unit_id=i.business_unit_id)
          AND (pb.fiscal_year<i.fiscal_year OR (pb.fiscal_year=i.fiscal_year AND pb.invoice_id<i.id))) d";

    public function __construct(private PDO $pdo) {}

    /** SELECT for rendering invoice documents; append a WHERE/ORDER BY on alias i. */
    public static function documentSelect(): string
    {
        return 'SELECT i.*,a.EMAIL business_email,b.paid_amount,b.outstanding,b.status_code balance_status,COALESCE(d.previous_debt,0) previous_debt
            FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id '.self::PREVIOUS_DEBT;
    }

    /** @return array<string,mixed>|null */
    public function document(int $invoiceId): ?array
    {
        $statement = $this->pdo->prepare(self::documentSelect().' WHERE i.id=:id');
        $statement->execute(['id' => $invoiceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int,totals:array<string,mixed>} */
    public function page(int $year, int $page, int $perPage, string $query = '', ?int $jobId = null, string $status = ''): array
    {
        $page = max(1, $page);
        $perPage = in_array($perPage, [20, 30, 50, 100], true) ? $perPage : 30;
        $where = ['i.fiscal_year=:year'];
        $params = ['year' => $year];
        if ($query !== '') {
            $where[] = '(i.invoice_number LIKE :search_number OR i.uniref LIKE :search_uniref OR a.Emri LIKE :search_name OR a.NRBIZ LIKE :search_registration OR i.business_name_snapshot LIKE :search_snapshot)';
            $value = '%'.$query.'%';
            $params += ['search_number' => $value, 'search_uniref' => $value, 'search_name' => $value, 'search_registration' => $value, 'search_snapshot' => $value];
        }
        if ($jobId !== null && $jobId > 0) { $where[] = 'EXISTS(SELECT 1 FROM dbo.annual_invoice_job_items ji WHERE ji.job_id=:job AND ji.invoice_id=i.id)'; $params['job'] = $jobId; }
        if ($status === 'OVERDUE') $where[] = 'b.outstanding>0 AND i.due_on<CONVERT(date,SYSDATETIME())';
        elseif (in_array($status, self::STATUS_FILTERS, true)) { $where[] = 'b.status_code=:status'; $params['status'] = $status; }
        $from = 'FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id WHERE '.implode(' AND ', $where);
        $count = $this->pdo->prepare('SELECT COUNT_BIG(*) total,COALESCE(SUM(i.amount),0) amount,COALESCE(SUM(b.paid_amount),0) paid,COALESCE(SUM(b.outstanding),0) outstanding '.$from);
        $count->execute($params);
        $totals = $count->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'amount' => 0, 'paid' => 0, 'outstanding' => 0];
        $count->closeCursor();
        $total = (int)$totals['total'];
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $statement = $this->pdo->prepare("SELECT i.id,i.invoice_number,i.uniref,i.amount,i.currency,i.issued_on,i.due_on,i.emailed_at,i.business_id,i.business_unit_id,i.business_name_snapshot,a.NRBIZ registration_number,a.Emri legal_name,b.paid_amount,b.outstanding,b.status_code $from ORDER BY i.issued_on DESC,i.id DESC OFFSET $offset ROWS FETCH NEXT $perPage ROWS ONLY");
        $statement->execute($params);
        return ['rows' => $statement->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage, 'totals' => $totals];
    }

    /** Invoice detail with balance, previous debt and unit. @return array<string,mixed>|null */
    public function find(int $invoiceId): ?array
    {
        $statement = $this->pdo->prepare('SELECT i.*,a.EMAIL business_email,a.Emri legal_name,a.NRBIZ registration_number,b.paid_amount,b.outstanding,b.status_code balance_status,COALESCE(d.previous_debt,0) previous_debt,u.unit_name,creator.full_name created_by_name
            FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id '.self::PREVIOUS_DEBT.'
            LEFT JOIN dbo.business_units u ON u.id=i.business_unit_id LEFT JOIN dbo.app_users creator ON creator.id=i.created_by_user_id WHERE i.id=:id');
        $statement->execute(['id' => $invoiceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function payments(int $invoiceId): array
    {
        $statement = $this->pdo->prepare('SELECT p.id,p.amount,p.paid_on,p.reference,p.notes,p.created_at,p.reversed_at,p.reversal_reason,p.import_row_id,recorder.full_name recorded_by,reverser.full_name reversed_by
            FROM dbo.business_invoice_payments p LEFT JOIN dbo.app_users recorder ON recorder.id=p.recorded_by_user_id LEFT JOIN dbo.app_users reverser ON reverser.id=p.reversed_by_user_id
            WHERE p.invoice_id=:invoice ORDER BY p.paid_on DESC,p.id DESC');
        $statement->execute(['invoice' => $invoiceId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** All invoices of a business (main and units). @return list<array<string,mixed>> */
    public function forBusiness(int $businessId): array
    {
        $statement = $this->pdo->prepare('SELECT i.id,i.fiscal_year,i.invoice_number,i.uniref,i.amount,i.issued_on,i.due_on,i.business_unit_id,u.unit_name,b.paid_amount,b.outstanding,b.status_code
            FROM dbo.business_invoices i JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id LEFT JOIN dbo.business_units u ON u.id=i.business_unit_id
            WHERE i.business_id=:business ORDER BY i.fiscal_year DESC,i.id DESC');
        $statement->execute(['business' => $businessId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Total unpaid balance of a business, its units included. */
    public function debtOfBusiness(int $businessId): float
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(outstanding),0) FROM dbo.v_invoice_balance WHERE business_id=:business');
        $statement->execute(['business' => $businessId]);
        $debt = (float)$statement->fetchColumn();
        $statement->closeCursor();
        return $debt;
    }

    /** Invoices with a balance for the payment form, searched by UNIREF, number, name or NRB. @return list<array<string,mixed>> */
    public function payable(string $query): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $value = '%'.$query.'%';
        $statement = $this->pdo->prepare('SELECT TOP (30) i.id,i.invoice_number,i.uniref,i.fiscal_year,i.business_name_snapshot,a.NRBIZ registration_number,a.Emri legal_name,b.outstanding,i.due_on
            FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id
            WHERE b.outstanding>0 AND (i.uniref LIKE :uniref OR i.invoice_number LIKE :number OR a.Emri LIKE :name OR a.NRBIZ LIKE :registration)
            ORDER BY i.due_on,i.issued_on,i.id');
        $statement->execute(['uniref' => $value, 'number' => $value, 'name' => $value, 'registration' => $value]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function recentPayments(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        return $this->pdo->query("SELECT TOP ($limit) p.id,p.invoice_id,p.amount,p.paid_on,p.reference,p.created_at,p.reversed_at,p.import_row_id,i.invoice_number,i.uniref,i.business_name_snapshot,u.full_name recorded_by
            FROM dbo.business_invoice_payments p JOIN dbo.business_invoices i ON i.id=p.invoice_id LEFT JOIN dbo.app_users u ON u.id=p.recorded_by_user_id
            ORDER BY p.created_at DESC,p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Unpaid obligations per client (business, units included), for the Finance Directorate report.
     * $upToYear limits the fiscal years taken into account; null takes all years.
     */
    public function obligations(?int $upToYear): PDOStatement
    {
        $yearSql = $upToYear === null ? '' : ' AND b.fiscal_year<=:year';
        $statement = $this->pdo->prepare("SELECT a.REGULATION_ID business_id,a.NRBIZ registration_number,a.Emri legal_name,a.NUMRI_FISKAL fiscal_number,a.ADRESA address,a.EMAIL email,a.NUMRI_TELEFONIT phone_number,a.MENAXHERI manager_name,
                COUNT(*) unpaid_invoices,MIN(b.fiscal_year) first_year,MAX(b.fiscal_year) last_year,
                SUM(b.amount) invoiced_amount,SUM(b.paid_amount) paid_amount,SUM(b.outstanding) outstanding,
                SUM(CASE WHEN b.due_on<CONVERT(date,SYSDATETIME()) THEN b.outstanding ELSE 0 END) overdue_amount,MIN(b.due_on) oldest_due_on,
                (SELECT MAX(p.paid_on) FROM dbo.business_invoice_payments p JOIN dbo.business_invoices inv ON inv.id=p.invoice_id WHERE inv.business_id=a.REGULATION_ID AND p.reversed_at IS NULL) last_payment_on
            FROM dbo.v_invoice_balance b JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=b.business_id
            WHERE b.outstanding>0$yearSql
            GROUP BY a.REGULATION_ID,a.NRBIZ,a.Emri,a.NUMRI_FISKAL,a.ADRESA,a.EMAIL,a.NUMRI_TELEFONIT,a.MENAXHERI
            ORDER BY SUM(b.outstanding) DESC,a.Emri");
        $statement->execute($upToYear === null ? [] : ['year' => $upToYear]);
        return $statement;
    }

    /** @return array{clients:int,invoices:int,outstanding:float,overdue:float} */
    public function obligationsSummary(?int $upToYear): array
    {
        $yearSql = $upToYear === null ? '' : ' AND fiscal_year<=:year';
        $statement = $this->pdo->prepare("SELECT COUNT(DISTINCT business_id) clients,COUNT(*) invoices,COALESCE(SUM(outstanding),0) outstanding,COALESCE(SUM(CASE WHEN due_on<CONVERT(date,SYSDATETIME()) THEN outstanding ELSE 0 END),0) overdue FROM dbo.v_invoice_balance WHERE outstanding>0$yearSql");
        $statement->execute($upToYear === null ? [] : ['year' => $upToYear]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['clients' => (int)($row['clients'] ?? 0), 'invoices' => (int)($row['invoices'] ?? 0), 'outstanding' => (float)($row['outstanding'] ?? 0), 'overdue' => (float)($row['overdue'] ?? 0)];
    }

    /** @return list<array<string,mixed>> */
    public function annualJobs(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->pdo->query("SELECT TOP ($limit) j.*,u.full_name requested_by FROM dbo.annual_invoice_jobs j LEFT JOIN dbo.app_users u ON u.id=j.requested_by_user_id ORDER BY j.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function paymentImports(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        return $this->pdo->query("SELECT TOP ($limit) bt.*,u.full_name uploaded_by FROM dbo.payment_import_batches bt LEFT JOIN dbo.app_users u ON u.id=bt.uploaded_by_user_id ORDER BY bt.id DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function paymentImport(int $batchId): ?array
    {
        $statement = $this->pdo->prepare('SELECT bt.*,u.full_name uploaded_by FROM dbo.payment_import_batches bt LEFT JOIN dbo.app_users u ON u.id=bt.uploaded_by_user_id WHERE bt.id=:id');
        $statement->execute(['id' => $batchId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function paymentImportRows(int $batchId, bool $onlyReview): array
    {
        $statement = $this->pdo->prepare('SELECT r.*,i.invoice_number matched_invoice_number,i.business_name_snapshot FROM dbo.payment_import_rows r LEFT JOIN dbo.business_invoices i ON i.id=r.invoice_id WHERE r.batch_id=:batch'.($onlyReview ? " AND r.status_code<>'APPLIED'" : '').' ORDER BY r.source_row_number');
        $statement->execute(['batch' => $batchId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
