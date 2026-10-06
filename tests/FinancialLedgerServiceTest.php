<?php
declare(strict_types=1);

use App\Service\AuditLogger;
use App\Service\FinancialLedgerService;
use PHPUnit\Framework\TestCase;

final class FinancialLedgerServiceTest extends TestCase
{
    private function service(PDO $pdo): FinancialLedgerService
    {
        return new FinancialLedgerService($pdo, new AuditLogger($pdo));
    }

    private function invoiceInput(): array
    {
        return ['business_id'=>'12','fiscal_year'=>'2026','invoice_number'=>'INV-2026-12','amount'=>'125.50','issued_on'=>'2026-10-06','due_on'=>'2026-11-06','notes'=>''];
    }

    public function testRejectsFiscalYearNotMatchingIssueDateBeforeWriting(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->createInvoice(array_replace($this->invoiceInput(),['fiscal_year'=>'2025']),1);
    }

    public function testRejectsDueDateBeforeIssueDateBeforeWriting(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->createInvoice(array_replace($this->invoiceInput(),['due_on'=>'2026-10-05']),1);
    }

    public function testRejectsZeroPaymentBeforeWriting(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->recordPayment(['invoice_id'=>'3','amount'=>'0','paid_on'=>'2026-10-06'],1);
    }

    public function testRejectsInvalidCalendarDateBeforeWriting(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->createInvoice(array_replace($this->invoiceInput(),['issued_on'=>'2026-02-30']),1);
    }

    public function testRejectsAmountBeyondSqlDecimalPrecisionBeforeWriting(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::never())->method('beginTransaction');
        $this->expectException(DomainException::class);
        $this->service($pdo)->createInvoice(array_replace($this->invoiceInput(),['amount'=>'10000000000000000.00']),1);
    }
}
