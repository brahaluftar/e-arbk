<?php
declare(strict_types=1);
namespace App\Support;

/** NACE Rev. 2 hierarchy used for revenue drill-down: section (letter) → division (first two digits) → code. */
final class NaceSections
{
    public const UNKNOWN = '?';

    /** @var array<string,array{0:int,1:int,2:string}> section => [first division, last division, Albanian name] */
    private const SECTIONS = [
        'A' => [1, 3, 'Bujqësia, pylltaria dhe peshkimi'],
        'B' => [5, 9, 'Industria nxjerrëse'],
        'C' => [10, 33, 'Industria përpunuese'],
        'D' => [35, 35, 'Furnizimi me energji elektrike, gaz dhe avull'],
        'E' => [36, 39, 'Furnizimi me ujë, kanalizimi dhe mbeturinat'],
        'F' => [41, 43, 'Ndërtimtaria'],
        'G' => [45, 47, 'Tregtia me shumicë dhe pakicë; riparimi i automjeteve'],
        'H' => [49, 53, 'Transporti dhe magazinimi'],
        'I' => [55, 56, 'Akomodimi dhe shërbimi i ushqimit'],
        'J' => [58, 63, 'Informacioni dhe komunikimi'],
        'K' => [64, 66, 'Aktivitetet financiare dhe të sigurimit'],
        'L' => [68, 68, 'Aktivitetet e patundshmërive'],
        'M' => [69, 75, 'Aktivitetet profesionale, shkencore dhe teknike'],
        'N' => [77, 82, 'Aktivitetet administrative dhe mbështetëse'],
        'O' => [84, 84, 'Administrata publike dhe mbrojtja'],
        'P' => [85, 85, 'Arsimi'],
        'Q' => [86, 88, 'Shëndetësia dhe puna sociale'],
        'R' => [90, 93, 'Arti, argëtimi dhe rekreacioni'],
        'S' => [94, 96, 'Aktivitete të tjera shërbyese'],
        'T' => [97, 98, 'Amvisëritë si punëdhënës'],
        'U' => [99, 99, 'Organizatat ekstraterritoriale'],
    ];

    /** Two-digit division of a NACE code ("5610" and "56.10" → "56"), or UNKNOWN. */
    public static function division(string $code): string
    {
        $digits = preg_replace('/[^0-9]/', '', $code) ?? '';
        return strlen($digits) >= 2 ? substr($digits, 0, 2) : self::UNKNOWN;
    }

    public static function section(string $code): string
    {
        $division = self::division($code);
        if ($division === self::UNKNOWN) return self::UNKNOWN;
        foreach (self::SECTIONS as $letter => [$from, $to]) if ((int)$division >= $from && (int)$division <= $to) return $letter;
        return self::UNKNOWN;
    }

    public static function name(string $section): string
    {
        return self::SECTIONS[$section][2] ?? 'Pa kod NACE të vlefshëm';
    }
}
