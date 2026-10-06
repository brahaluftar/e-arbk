<?php
declare(strict_types=1);

use App\Repository\BusinessRepository;
use PHPUnit\Framework\TestCase;

final class BusinessRepositoryDashboardTest extends TestCase
{
    public function testFinancialEstimateUsesActiveBusinessesAndTariffFallbackOrder(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetch')->willReturn(['estimated_income'=>'12750.00','priced_businesses'=>42,'unpriced_businesses'=>8]);
        $pdo = $this->createMock(PDO::class);
        $pdo->expects(self::once())->method('query')->with(self::callback(static function (string $sql): bool {
            return str_contains($sql, "END)='ACTIVE'")
                && str_contains($sql, 'TRY_CONVERT(decimal(19,2), a.tarifa_me_lirim)')
                && strpos($sql, 'a.tarifa_me_lirim') < strpos($sql, 'a.NACE_REG_TARIFF')
                && strpos($sql, 'a.NACE_REG_TARIFF') < strpos($sql, 'x.tariff_snapshot');
        }))->willReturn($statement);

        self::assertSame(['estimated_income'=>'12750.00','priced_businesses'=>42,'unpriced_businesses'=>8], (new BusinessRepository($pdo))->financialDashboard());
    }
}
