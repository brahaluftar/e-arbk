<?php
declare(strict_types=1);

use App\Billing\UnirefGenerator;
use PHPUnit\Framework\TestCase;

final class UnirefGeneratorTest extends TestCase
{
    public function testComposesFixedLengthMod36Uniref(): void
    {
        $generator = new UnirefGenerator();
        $uniref = $generator->compose('5610', 1);
        self::assertSame(16, strlen($uniref));
        self::assertSame('PREAH5610000001', substr($uniref, 0, 15));
        self::assertTrue($generator->isValid($uniref));
        self::assertFalse($generator->isValid(substr($uniref, 0, 15).'Z'));
    }

    public function testRejectsInvalidNaceAndSequence(): void
    {
        $generator = new UnirefGenerator();
        $this->expectException(InvalidArgumentException::class);
        $generator->compose('56', 1);
    }
}
