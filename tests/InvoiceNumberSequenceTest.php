<?php
declare(strict_types=1);

use App\Billing\InvoiceNumberSequence;
use PHPUnit\Framework\TestCase;

final class InvoiceNumberSequenceTest extends TestCase
{
    public function testFormatsYearAndSixDigitSequence(): void
    {
        self::assertSame('2026-000001', InvoiceNumberSequence::format(2026, 1));
        self::assertSame('2027-123456', InvoiceNumberSequence::format(2027, 123456));
    }

    public function testRejectsNumbersOutOfRange(): void
    {
        $this->expectException(DomainException::class);
        InvoiceNumberSequence::format(2026, 1000000);
    }
}
