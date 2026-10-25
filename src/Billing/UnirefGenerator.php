<?php
declare(strict_types=1);

namespace App\Billing;

use InvalidArgumentException;

/**
 * UNIREF = BaseReference (exactly 15 characters: FixedCode + YearCode + InvoiceCode) + one MOD-36 check character.
 * Check character: Σ value(char) × weight, weight = L + 1 − index (0-based; 16..2 for 15 characters),
 * remainder mod 36 mapped directly to 0-9A-Z (no complement, not ISO 7064 MOD 37,36).
 * Invoices use FixedCode "PREAH", no YearCode and InvoiceCode = 4-character NACE + 6-digit sequence.
 */
final class UnirefGenerator
{
    public const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    public const BASE_LENGTH = 15;

    public function compose(string $naceCode, int $sequence): string
    {
        $naceCode = strtoupper(trim($naceCode));
        if (preg_match('/\A[0-9A-Z]{4}\z/', $naceCode) !== 1) {
            throw new InvalidArgumentException('Kodi NACE për UNIREF duhet të ketë saktësisht 4 karaktere alfanumerike.');
        }
        if ($sequence < 1 || $sequence > 999999) {
            throw new InvalidArgumentException('Sekuenca UNIREF duhet të jetë ndërmjet 1 dhe 999999.');
        }
        return $this->fromBase('PREAH'.$naceCode.sprintf('%06d', $sequence));
    }

    /** Appends the check character to a 15-character base reference (trimmed and uppercased first). */
    public function fromBase(string $base): string
    {
        $base = strtoupper(trim($base));
        if (strlen($base) !== self::BASE_LENGTH) {
            throw new InvalidArgumentException('Referenca bazë e UNIREF-it duhet të ketë saktësisht 15 karaktere.');
        }
        return $base.$this->checkCharacter($base);
    }

    /** A 16-character code is valid when its last character equals the check character of the first 15. */
    public function isValid(string $uniref): bool
    {
        $uniref = strtoupper(trim($uniref));
        if (preg_match('/\A[0-9A-Z]{16}\z/', $uniref) !== 1) return false;
        return hash_equals($this->checkCharacter(substr($uniref, 0, self::BASE_LENGTH)), $uniref[self::BASE_LENGTH]);
    }

    public function checkCharacter(string $input): string
    {
        return self::ALPHABET[$this->weightedSum($input) % 36];
    }

    public function weightedSum(string $input): int
    {
        $input = strtoupper(trim($input));
        if ($input === '') throw new InvalidArgumentException('Baza e UNIREF-it nuk mund të jetë e zbrazët.');
        $sum = 0;
        $length = strlen($input);
        for ($index = 0; $index < $length; $index++) {
            $value = strpos(self::ALPHABET, $input[$index]);
            if ($value === false) throw new InvalidArgumentException('UNIREF-i përmban karakter të palejuar.');
            $sum += $value * ($length + 1 - $index);
        }
        return $sum;
    }
}
