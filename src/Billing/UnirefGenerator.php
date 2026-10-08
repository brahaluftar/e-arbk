<?php
declare(strict_types=1);

namespace App\Billing;

use InvalidArgumentException;

final class UnirefGenerator
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function compose(string $naceCode, int $sequence): string
    {
        $naceCode = strtoupper(trim($naceCode));
        if (preg_match('/\A[0-9A-Z]{4}\z/', $naceCode) !== 1) {
            throw new InvalidArgumentException('Kodi NACE për UNIREF duhet të ketë saktësisht 4 karaktere alfanumerike.');
        }
        if ($sequence < 1 || $sequence > 999999) {
            throw new InvalidArgumentException('Sekuenca UNIREF duhet të jetë ndërmjet 1 dhe 999999.');
        }
        $base = 'PREAH'.$naceCode.sprintf('%06d', $sequence);
        return $base.$this->checkCharacter($base);
    }

    public function isValid(string $uniref): bool
    {
        $uniref = strtoupper(trim($uniref));
        if (preg_match('/\APREAH[0-9A-Z]{4}[0-9]{6}[0-9A-Z]\z/', $uniref) !== 1) return false;
        return hash_equals($this->checkCharacter(substr($uniref, 0, 15)), $uniref[15]);
    }

    public function checkCharacter(string $input): string
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
        return self::ALPHABET[$sum % 36];
    }
}
