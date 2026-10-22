<?php
declare(strict_types=1);
namespace App\Billing;

use PDO;

/** Who changed an invoice, when, what and why (business_invoice_events). */
final class InvoiceHistory
{
    public const EVENTS = ['CREATED', 'EDITED', 'CANCELLED', 'RESTORED', 'PAYMENT_RECORDED', 'PAYMENT_REVERSED', 'EMAILED'];

    public function __construct(private PDO $pdo) {}

    /** @param array<string,mixed>|null $changes */
    public function record(int $invoiceId, string $event, ?array $changes, ?string $comment, ?int $actorId): void
    {
        if (!in_array($event, self::EVENTS, true)) throw new \InvalidArgumentException('Unknown invoice event.');
        $comment = $comment === null ? null : mb_substr(trim($comment), 0, 1000);
        $this->pdo->prepare('INSERT dbo.business_invoice_events(invoice_id,event_code,changes_json,comment,actor_user_id) VALUES(:invoice,:event,:changes,:comment,:actor)')->execute([
            'invoice' => $invoiceId,
            'event' => $event,
            'changes' => $changes === null || $changes === [] ? null : json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'comment' => $comment === '' ? null : $comment,
            'actor' => $actorId,
        ]);
    }

    /** @return list<array<string,mixed>> newest first */
    public function forInvoice(int $invoiceId): array
    {
        $statement = $this->pdo->prepare('SELECT e.id,e.event_code,e.changes_json,e.comment,e.created_at,u.full_name actor_name FROM dbo.business_invoice_events e LEFT JOIN dbo.app_users u ON u.id=e.actor_user_id WHERE e.invoice_id=:invoice ORDER BY e.created_at DESC,e.id DESC');
        $statement->execute(['invoice' => $invoiceId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['changes'] = is_string($row['changes_json']) ? (json_decode($row['changes_json'], true) ?: []) : [];
        return $rows;
    }
}
