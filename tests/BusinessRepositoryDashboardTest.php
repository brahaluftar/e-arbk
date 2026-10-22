<?php
declare(strict_types=1);

use App\Billing\BillingPolicy;
use App\Repository\BusinessRepository;
use PHPUnit\Framework\TestCase;

final class BusinessRepositoryDashboardTest extends TestCase
{
    public function testNoRelationFilterSelectsBusinessesMissingFromNaceList(): void
    {
        $count = $this->createMock(PDOStatement::class);
        $count->method('fetchColumn')->willReturn(0);
        $rows = $this->createMock(PDOStatement::class);
        $rows->method('fetchAll')->willReturn([]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::exactly(2))->method('prepare')->with(self::callback(
            static fn(string $sql): bool => str_contains($sql,'COALESCE(c.candidate_count,0)=0')
        ))->willReturnOnConsecutiveCalls($count,$rows);

        $result=(new BusinessRepository($pdo))->search(['classification'=>'NO_RELATION'],1,25);
        self::assertSame(0,$result['total']);
        self::assertSame([],$result['rows']);
    }

    public function testFinancialEstimateUsesTheBillingViewAndPolicy(): void
    {
        $totalsStatement = $this->createMock(PDOStatement::class);
        $totalsStatement->expects(self::once())->method('execute')->with(['year'=>2026,'main_year'=>2026,'unit_year'=>2026,'invoice_year'=>2026,'paid_from'=>'2026-01-01','paid_to'=>'2027-01-01','outstanding_year'=>2026]);
        $totalsStatement->method('fetch')->willReturn(['estimated_income'=>'12750.00','priced_businesses'=>42,'unpriced_businesses'=>8,'priced_units'=>3,'to_be_invoiced'=>'5000','invoiced'=>'7750','paid'=>'3000','to_be_paid'=>'4750']);
        $monthlyStatement = $this->createMock(PDOStatement::class);
        $monthlyStatement->method('fetchAll')->willReturn([]);
        $yearsStatement = $this->createMock(PDOStatement::class);
        $yearsStatement->method('fetchAll')->willReturn([2025]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::exactly(2))->method('prepare')->with(self::callback(static function (string $sql): bool {
            if (!str_contains($sql, 'active_businesses')) return str_contains($sql, 'p.reversed_at IS NULL') && str_contains($sql, "status_code<>'CANCELLED'");
            return str_contains($sql, 'dbo.v_business_billing b')
                && str_contains($sql, "b.tariff_source IN('DISCOUNT','REGISTERED')")
                && str_contains($sql, 'business_unit_id IS NULL')
                && str_contains($sql, 'unit_estimate')
                && str_contains($sql, 'p.reversed_at IS NULL')
                && str_contains($sql, 'e.estimated_tariff-COALESCE(i.invoiced_amount,0)');
        }))->willReturnOnConsecutiveCalls($totalsStatement,$monthlyStatement);
        $pdo->expects(self::once())->method('query')->with('SELECT DISTINCT fiscal_year FROM dbo.business_invoices ORDER BY fiscal_year DESC')->willReturn($yearsStatement);

        $stats=(new BusinessRepository($pdo))->financialDashboard(2026,new BillingPolicy());
        self::assertSame(12750.0,$stats['estimated_income']);
        self::assertSame(5000.0,$stats['to_be_invoiced']);
        self::assertSame(7750.0,$stats['invoiced']);
        self::assertSame(3000.0,$stats['paid']);
        self::assertSame(4750.0,$stats['to_be_paid']);
        self::assertSame(3,$stats['priced_units']);
        self::assertContains(2025,$stats['years']);
        self::assertContains(2026,$stats['years']);
    }

    public function testManualInvoiceSearchIgnoresUnitInvoices(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn([]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('prepare')->with(self::callback(static fn(string $sql): bool => str_contains($sql,'i.business_unit_id IS NULL AND i.fiscal_year=:invoice_year')))->willReturn($statement);
        self::assertSame([],(new BusinessRepository($pdo))->financeBusinesses(2026,'abc'));
    }
}
