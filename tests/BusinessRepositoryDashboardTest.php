<?php
declare(strict_types=1);

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

    public function testFinancialEstimateUsesActiveBusinessesAndTariffFallbackOrder(): void
    {
        $totalsStatement = $this->createMock(PDOStatement::class);
        $totalsStatement->expects(self::once())->method('execute')->with(['year'=>2026,'estimate_invoice_year'=>2026,'invoice_year_2'=>2026,'paid_year'=>2026,'invoice_year_3'=>2026]);
        $totalsStatement->method('fetch')->willReturn(['estimated_income'=>'12750.00','priced_businesses'=>42,'unpriced_businesses'=>8,'to_be_invoiced'=>'5000','invoiced'=>'7750','paid'=>'3000','to_be_paid'=>'4750']);
        $monthlyStatement = $this->createMock(PDOStatement::class);
        $monthlyStatement->method('fetchAll')->willReturn([]);
        $yearsStatement = $this->createMock(PDOStatement::class);
        $yearsStatement->method('fetchAll')->willReturn([2025]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::exactly(2))->method('prepare')->with(self::callback(static function (string $sql): bool {
            if (!str_contains($sql, 'annual_estimate')) return true;
            return str_contains($sql, "END)='ACTIVE'")
                && str_contains($sql, 'TRY_CONVERT(decimal(19,2), a.tarifa_me_lirim)')
                && str_contains($sql, 'invoice_by_business')
                && str_contains($sql, 'e.estimated_tariff-COALESCE(i.invoiced_amount,0)')
                && strpos($sql, 'a.tarifa_me_lirim') < strpos($sql, 'a.NACE_REG_TARIFF')
                && strpos($sql, 'a.NACE_REG_TARIFF') < strpos($sql, 'x.tariff_snapshot');
        }))->willReturnOnConsecutiveCalls($totalsStatement,$monthlyStatement);
        $pdo->expects(self::once())->method('query')->with('SELECT DISTINCT fiscal_year FROM dbo.business_invoices ORDER BY fiscal_year DESC')->willReturn($yearsStatement);

        $stats=(new BusinessRepository($pdo))->financialDashboard(2026);
        self::assertSame(12750.0,$stats['estimated_income']);
        self::assertSame(5000.0,$stats['to_be_invoiced']);
        self::assertSame(7750.0,$stats['invoiced']);
        self::assertSame(3000.0,$stats['paid']);
        self::assertSame(4750.0,$stats['to_be_paid']);
        self::assertContains(2025,$stats['years']);
        self::assertContains(2026,$stats['years']);
    }

    public function testPayableInvoiceQueryGroupsItsOrderingColumns(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchAll')->willReturn([]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('query')->with(self::callback(static function (string $sql): bool {
            return str_contains($sql,'GROUP BY i.id,i.invoice_number,i.amount,i.due_on,i.issued_on,a.NRBIZ,a.Emri')
                && str_contains($sql,'ORDER BY i.due_on,i.issued_on,i.id');
        }))->willReturn($statement);

        self::assertSame([],(new BusinessRepository($pdo))->payableInvoices());
    }
}
