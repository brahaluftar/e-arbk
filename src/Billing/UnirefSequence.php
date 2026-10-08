<?php
declare(strict_types=1);

namespace App\Billing;

use PDO;
use RuntimeException;

final class UnirefSequence
{
    public function __construct(private PDO $pdo, private UnirefGenerator $generator = new UnirefGenerator()) {}

    public function next(string $naceCode): string
    {
        $naceCode = strtoupper(trim($naceCode));
        // Validate before acquiring a database lock.
        $this->generator->compose($naceCode, 1);
        $statement = $this->pdo->prepare("MERGE dbo.invoice_uniref_sequences WITH(HOLDLOCK) AS target
            USING(SELECT :nace AS nace_code) AS source ON source.nace_code=target.nace_code
            WHEN MATCHED AND target.last_value<999999 THEN UPDATE SET last_value=target.last_value+1,updated_at=SYSUTCDATETIME()
            WHEN NOT MATCHED THEN INSERT(nace_code,last_value) VALUES(source.nace_code,1)
            OUTPUT inserted.last_value;");
        $statement->execute(['nace'=>$naceCode]);
        $sequence = $statement->fetchColumn();
        $statement->closeCursor();
        if ($sequence === false) throw new RuntimeException('Sekuenca UNIREF për këtë kod NACE është shteruar.');
        return $this->generator->compose($naceCode, (int)$sequence);
    }
}
