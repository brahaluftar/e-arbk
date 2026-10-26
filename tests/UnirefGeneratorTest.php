<?php
declare(strict_types=1);

use App\Billing\UnirefGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnirefGeneratorTest extends TestCase
{
    /** @return array<string,array{string,int,int,string,string}> base, sum, remainder, check, UNIREF */
    public static function vectors(): array
    {
        return [
            'worked example' => ['MFA2E8047616648', 1221, 33, 'X', 'MFA2E8047616648X'],
            'lowercase is normalized' => ['mfa2e8047616648', 1221, 33, 'X', 'MFA2E8047616648X'],
            'all zeros' => ['000000000000000', 0, 0, '0', '0000000000000000'],
            'all Z' => ['ZZZZZZZZZZZZZZZ', 4725, 9, '9', 'ZZZZZZZZZZZZZZZ9'],
            'digits' => ['123456789012345', 530, 26, 'Q', '123456789012345Q'],
            'mixed' => ['ABC2026XYZ00001', 1417, 13, 'D', 'ABC2026XYZ00001D'],
            'letter O is not zero' => ['MFA2E8O47616648', 1461, 21, 'L', 'MFA2E8O47616648L'],
            'surrounding whitespace is trimmed' => ['  MFA2E8047616648 ', 1221, 33, 'X', 'MFA2E8047616648X'],
        ];
    }

    #[DataProvider('vectors')]
    public function testMatchesSpecificationVectors(string $base, int $sum, int $remainder, string $check, string $uniref): void
    {
        $generator = new UnirefGenerator();
        self::assertSame($sum, $generator->weightedSum($base));
        self::assertSame($remainder, $sum % 36);
        self::assertSame($check, $generator->checkCharacter($base));
        self::assertSame($uniref, $generator->fromBase($base));
        self::assertTrue($generator->isValid($uniref));
        self::assertTrue($generator->isValid(strtolower($uniref)));
    }

    /** @return array<string,array{string}> */
    public static function rejectedBases(): array
    {
        return [
            '14 characters' => ['MFA2E804761664'],
            '16 characters' => ['MFA2E80476166481'],
            'hyphen' => ['MFA2E80476-6648'],
            'inner space' => ['MFA2E80476 6648'],
            'accented letter' => ['MFA2E8047616Ë48'],
        ];
    }

    #[DataProvider('rejectedBases')]
    public function testRejectsInvalidBases(string $base): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new UnirefGenerator())->fromBase($base);
    }

    public function testComposesInvoiceUniref(): void
    {
        $generator = new UnirefGenerator();
        $uniref = $generator->compose('5610', 1);
        self::assertSame(16, strlen($uniref));
        self::assertSame('PREAH5610000001', substr($uniref, 0, 15));
        self::assertSame($generator->fromBase('PREAH5610000001'), $uniref);
        self::assertTrue($generator->isValid($uniref));
    }

    public function testValidationComparesTheSixteenthCharacter(): void
    {
        $generator = new UnirefGenerator();
        self::assertFalse($generator->isValid('MFA2E8047616648Y'));
        self::assertFalse($generator->isValid('MFA2E8047616648'));
        self::assertFalse($generator->isValid('MFA2E8047616648XX'));
        self::assertFalse($generator->isValid('MFA2E80476-6648X'));
    }

    public function testRejectsInvalidNaceAndSequence(): void
    {
        $generator = new UnirefGenerator();
        $this->expectException(InvalidArgumentException::class);
        $generator->compose('56', 1);
    }
}
