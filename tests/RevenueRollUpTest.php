<?php
declare(strict_types=1);

use App\Repository\RevenueRepository;
use App\Support\NaceSections;
use PHPUnit\Framework\TestCase;

final class RevenueRollUpTest extends TestCase
{
    public function testMapsCodesToNaceSections(): void
    {
        self::assertSame(['I', '56'], [NaceSections::section('5610'), NaceSections::division('5610')]);
        self::assertSame(['I', '56'], [NaceSections::section('56.10'), NaceSections::division('56.10')]);
        self::assertSame('A', NaceSections::section('0111'));
        self::assertSame('G', NaceSections::section('4711'));
        self::assertSame('C', NaceSections::section('3320'));
        self::assertSame('U', NaceSections::section('9900'));
        self::assertSame(NaceSections::UNKNOWN, NaceSections::section('0400'));   // division 04 is not used in NACE Rev. 2
        self::assertSame(NaceSections::UNKNOWN, NaceSections::section(''));
        self::assertSame('Akomodimi dhe shërbimi i ushqimit', NaceSections::name('I'));
    }

    /** @return list<array<string,mixed>> */
    private function rows(): array
    {
        $row = static fn(?string $code, float $estimated, float $invoiced, float $paid, int $count = 1) => ['nace_code' => $code, 'description' => $code === null ? null : 'Përshkrim '.$code,
            'estimated' => $estimated, 'to_be_invoiced' => max(0, $estimated - $invoiced), 'estimated_count' => $count, 'invoiced' => $invoiced, 'paid' => $paid, 'outstanding' => $invoiced - $paid, 'invoice_count' => $invoiced > 0 ? 1 : 0];
        return [$row('5610', 300, 200, 50, 2), $row('5630', 100, 100, 100), $row('5510', 420, 420, 0), $row('4711', 80, 0, 0), $row(null, 0, 60, 10, 0)];
    }

    public function testRollsUpSectionsDivisionsAndCodesWithoutLosingAmounts(): void
    {
        $rows = $this->rows();
        $sections = RevenueRepository::rollUp($rows);
        self::assertSame(['I', 'G', '?'], array_column($sections, 'key'));
        self::assertSame(820.0, $sections[0]['estimated']);
        self::assertSame(720.0, $sections[0]['invoiced']);
        self::assertSame(3, $sections[0]['invoice_count']);
        self::assertEquals(RevenueRepository::totals($rows), RevenueRepository::totals($sections));

        $divisions = RevenueRepository::rollUp($rows, 'I');
        self::assertSame(['55', '56'], array_column($divisions, 'key'));
        self::assertSame('Divizioni 56', $divisions[1]['label']);
        self::assertSame(570.0, RevenueRepository::totals($divisions)['outstanding']); // 150 + 0 + 420

        $codes = RevenueRepository::rollUp($rows, 'I', '56');
        self::assertSame(['5610', '5630'], array_column($codes, 'key'));
        self::assertSame('Përshkrim 5610', $codes[0]['label']);
        self::assertSame(100.0, $codes[0]['to_be_invoiced']);

        $unknown = RevenueRepository::rollUp($rows, NaceSections::UNKNOWN, NaceSections::UNKNOWN);
        self::assertSame([RevenueRepository::NO_CODE], array_column($unknown, 'key'));
        self::assertSame('Pa kod NACE', $unknown[0]['label']);
    }
}
