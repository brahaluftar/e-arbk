<?php
declare(strict_types=1);

namespace App\Billing;

use DomainException;
use PDO;
use RuntimeException;

/** Invoice numbers per fiscal year, separate from the UNIREF: 2026-000001, 2026-000002, … */
final class InvoiceNumberSequence
{
    public function __construct(private PDO $pdo) {}

    public static function format(int $year, int $sequence): string
    {
        if ($year < 2000 || $year > 2100 || $sequence < 1 || $sequence > 999999) throw new DomainException('Numri i faturës është jashtë kufijve.');
        return sprintf('%04d-%06d', $year, $sequence);
    }

    /** Reserves the next number of the year inside the caller's transaction. */
    public function next(int $year): string
    {
        $statement = $this->pdo->prepare("MERGE dbo.invoice_number_sequences WITH(HOLDLOCK) AS target
            USING(SELECT CONVERT(smallint,:year) AS fiscal_year) AS source ON source.fiscal_year=target.fiscal_year
            WHEN MATCHED AND target.last_value<999999 THEN UPDATE SET last_value=target.last_value+1,updated_at=SYSUTCDATETIME()
            WHEN NOT MATCHED THEN INSERT(fiscal_year,last_value) VALUES(source.fiscal_year,1)
            OUTPUT inserted.last_value;");
        $statement->execute(['year' => $year]);
        $sequence = $statement->fetchColumn();
        $statement->closeCursor();
        if ($sequence === false) throw new RuntimeException('Numrat e faturave për vitin '.$year.' janë shteruar.');
        return self::format($year, (int)$sequence);
    }
}
