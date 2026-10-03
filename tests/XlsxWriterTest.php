<?php
declare(strict_types=1);

use App\Export\XlsxWriter;
use PHPUnit\Framework\TestCase;

final class XlsxWriterTest extends TestCase
{
    public function testWritesValidWorkbookWithUnicodeAndLeadingZeroText():void
    {
        $file=tempnam(sys_get_temp_dir(),'xlsx_test_');self::assertNotFalse($file);
        try{
            $count=(new XlsxWriter())->write($file,['code'=>'Kodi','name'=>'Emri'],[['code'=>'00123','name'=>'Prishtinë & Test']]);self::assertSame(1,$count);
            $zip=new \ZipArchive();self::assertTrue($zip->open($file)===true);self::assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));$sheet=$zip->getFromName('xl/worksheets/sheet1.xml');$zip->close();
            self::assertIsString($sheet);self::assertStringContainsString('00123',$sheet);self::assertStringContainsString('Prishtinë &amp; Test',$sheet);self::assertNotFalse(simplexml_load_string($sheet));
        }finally{if(is_string($file)&&is_file($file))unlink($file);}
    }
}
