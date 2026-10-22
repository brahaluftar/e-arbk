<?php
declare(strict_types=1);
use App\Billing\PaymentImportService;
use PHPUnit\Framework\TestCase;

final class PaymentImportServiceTest extends TestCase
{
    public function testParsesAmountsInLocalAndExcelFormats(): void
    {
        foreach(['125,50'=>'125.50','125.5'=>'125.50','1.234,56'=>'1234.56','1,234.56'=>'1234.56','1.234.567'=>'1234567.00','€ 30'=>'30.00','2500'=>'2500.00','0.999'=>'1.00'] as $raw=>$expected){
            self::assertSame($expected,PaymentImportService::parseAmount((string)$raw),"Amount $raw");
        }
        foreach(['','0','-5','abc','1,2,3.4.5'] as $invalid)self::assertNull(PaymentImportService::parseAmount($invalid),"Amount $invalid");
    }

    public function testParsesDatesStrictly(): void
    {
        self::assertSame('2026-10-22',PaymentImportService::parseDate('22.10.2026'));
        self::assertSame('2026-10-22',PaymentImportService::parseDate('22/10/2026'));
        self::assertSame('2026-10-22',PaymentImportService::parseDate('2026-10-22 00:00:00'));
        self::assertSame('2026-10-22',PaymentImportService::parseDate('46317'));
        self::assertNull(PaymentImportService::parseDate('31.02.2026'));
        self::assertNull(PaymentImportService::parseDate('22.10.1990'));
        self::assertNull(PaymentImportService::parseDate('dje'));
    }

    public function testFindsUnirefInFreeText(): void
    {
        self::assertSame('PREAH4711000001X',PaymentImportService::findUniref('','Pagesa per taksen PREAH 4711 000001X faleminderit'));
        self::assertNull(PaymentImportService::findUniref('Pagesa pa referencë'));
    }

    public function testNormalizesHeaders(): void
    {
        self::assertSame('dataepageses',PaymentImportService::normalizeHeader(' Data e pagesës '));
        self::assertSame('shuma',PaymentImportService::normalizeHeader('SHUMA'));
        self::assertSame('pershkrimi',PaymentImportService::normalizeHeader('Përshkrimi'));
    }
}
