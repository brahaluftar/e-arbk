<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class PortalRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string,mixed>> */
    public function businesses(int $userId): array
    {
        $statement=$this->pdo->prepare("SELECT a.REGULATION_ID id,a.Emri legal_name,a.NRBIZ registration_number,a.NUMRI_FISKAL fiscal_number,a.ADRESA address,a.NACE_CODE_REG nace_code,
                (SELECT COALESCE(SUM(b.outstanding),0) FROM dbo.v_invoice_balance b WHERE b.business_id=a.REGULATION_ID) outstanding,
                (SELECT COUNT(*) FROM dbo.business_units u WHERE u.business_id=a.REGULATION_ID AND u.is_active=1) unit_count
            FROM dbo.business_user_links l JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=l.business_id
            WHERE l.user_id=:user AND l.ended_at IS NULL ORDER BY a.Emri,a.REGULATION_ID");
        $statement->execute(['user'=>$userId]);return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function accessRequests(int $userId): array
    {
        $statement=$this->pdo->prepare("SELECT r.id,r.status_code,r.requested_at,r.reviewed_at,r.review_note,a.Emri legal_name,a.NRBIZ registration_number
            FROM dbo.business_access_requests r JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=r.business_id
            WHERE r.user_id=:user ORDER BY r.requested_at DESC,r.id DESC");
        $statement->execute(['user'=>$userId]);return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function invoices(int $userId): array
    {
        $statement=$this->pdo->prepare("SELECT i.id,i.invoice_number,i.uniref,i.fiscal_year,i.amount,i.currency,i.issued_on,i.due_on,b.status_code,i.business_name_snapshot business_name,a.NRBIZ registration_number,b.paid_amount,b.outstanding
            FROM dbo.business_user_links l JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=l.business_id
            JOIN dbo.business_invoices i ON i.business_id=a.REGULATION_ID JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id
            WHERE l.user_id=:user AND l.ended_at IS NULL
            ORDER BY i.issued_on DESC,i.id DESC");
        $statement->execute(['user'=>$userId]);return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function permits(int $userId): array
    {
        $statement=$this->pdo->prepare("SELECT p.id,p.serial_number,p.issued_on,p.valid_from,p.valid_until,CASE WHEN p.status_code='ACTIVE' AND p.valid_until<CONVERT(date,SYSDATETIME()) THEN 'EXPIRED' ELSE p.status_code END status_code,p.pdf_storage_key,p.business_name_snapshot business_name,a.NRBIZ registration_number
            FROM dbo.business_user_links l JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=l.business_id
            JOIN dbo.business_permits p ON p.business_id=a.REGULATION_ID
            WHERE l.user_id=:user AND l.ended_at IS NULL ORDER BY p.issued_on DESC,p.id DESC");
        $statement->execute(['user'=>$userId]);return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function appeals(int $userId): array
    {
        $statement=$this->pdo->prepare("SELECT ap.id,ap.subject,ap.status_code,ap.submitted_at,ap.decision_code,ap.decision_text,i.invoice_number,a.Emri business_name
            FROM dbo.invoice_appeals ap JOIN dbo.business_invoices i ON i.id=ap.invoice_id
            JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id
            JOIN dbo.business_user_links l ON l.business_id=a.REGULATION_ID AND l.user_id=:user AND l.ended_at IS NULL
            ORDER BY ap.submitted_at DESC,ap.id DESC");
        $statement->execute(['user'=>$userId]);return $statement->fetchAll();
    }

    public function userCanAccessInvoice(int $userId,int $invoiceId): bool
    {
        $statement=$this->pdo->prepare('SELECT 1 FROM dbo.business_invoices i JOIN dbo.business_user_links l ON l.business_id=i.business_id AND l.ended_at IS NULL WHERE i.id=:invoice AND l.user_id=:user');
        $statement->execute(['invoice'=>$invoiceId,'user'=>$userId]);return $statement->fetchColumn()!==false;
    }
}
