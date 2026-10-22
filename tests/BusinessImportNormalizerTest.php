<?php
declare(strict_types=1);
use App\Import\BusinessImportNormalizer;
use PHPUnit\Framework\TestCase;

final class BusinessImportNormalizerTest extends TestCase
{
    private function registerRow(array $values): array
    {
        $headers=['Nr. i biznesit','Emri i biznesit','Aktiv. Nace2 - Përshkrimi','Sektori','Qyteti','Statusi i biznesit','Data Shuarjes'];
        $row=array_map(static fn(string $h): ?string => $values[$h]??null,$headers);
        return (new BusinessImportNormalizer())->normalize($row,array_flip($headers),'ALL');
    }

    public function testImpossibleDatesAreReportedInsteadOfShifted(): void
    {
        $passive=$this->registerRow(['Nr. i biznesit'=>'810000001','Emri i biznesit'=>'Test','Qyteti'=>'Prishtinë','Statusi i biznesit'=>'Pasiv-31/02/2026']);
        self::assertNull($passive['passive_date']);
        self::assertNotNull($passive['validation_error']);
        $closed=$this->registerRow(['Nr. i biznesit'=>'810000001','Emri i biznesit'=>'Test','Qyteti'=>'Prishtinë','Data Shuarjes'=>'31/02/2026']);
        self::assertNotNull($closed['validation_error']);
        $valid=$this->registerRow(['Nr. i biznesit'=>'810000001','Emri i biznesit'=>'Test','Qyteti'=>'Prishtinë','Statusi i biznesit'=>'Pasiv-28/02/2026','Data Shuarjes'=>'01/03/2026']);
        self::assertNull($valid['validation_error']);
        self::assertSame('2026-02-28',$valid['passive_date']);
        self::assertSame('2026-03-01',$valid['closed_date']);
    }

    public function testUnitRowsAreValidatedBeforeTheDatabaseConstraintFails(): void
    {
        $headers=['NRB','EMRI_NJESISE','ADRESA','NUMRI_TELEFONIT','EMAILI','MENAXHERI','NACE','VEPRIMTARIA_NACE','TARIFA_VJETORE','FATURIM_AUTOMATIK'];
        $unit=static function(array $values)use($headers): array {
            $base=['NRB'=>'810000001','EMRI_NJESISE'=>'Dega 1','ADRESA'=>'Rr. A','NACE'=>'4711','TARIFA_VJETORE'=>'50','FATURIM_AUTOMATIK'=>'Po'];
            $data=array_replace($base,$values);
            return (new BusinessImportNormalizer())->normalize(array_map(static fn(string $h): ?string => $data[$h]??null,$headers),array_flip($headers),'UNITS');
        };
        self::assertNull($unit([])['validation_error']);
        self::assertSame(1,$unit([])['unit_auto_invoice']);
        self::assertNotNull($unit(['TARIFA_VJETORE'=>'0'])['validation_error']);
        self::assertNotNull($unit(['TARIFA_VJETORE'=>null])['validation_error'],'Automatic invoicing without tariff');
        self::assertNotNull($unit(['EMAILI'=>'not-an-email'])['validation_error']);
        self::assertNotNull($unit(['NACE'=>'47111'])['validation_error']);
    }
}
