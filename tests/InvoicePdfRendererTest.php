<?php
declare(strict_types=1);
use App\Billing\InvoicePdfRenderer;
use App\Support\Config;
use PHPUnit\Framework\TestCase;
final class InvoicePdfRendererTest extends TestCase
{
    public function testRendersMunicipalInvoiceDesign():void
    {
        $config=Config::load(sys_get_temp_dir().'/arbk-pdf-'.bin2hex(random_bytes(4)),require dirname(__DIR__).'/config/defaults.php');
        $pdf=(new InvoicePdfRenderer($config))->render(['invoice_number'=>'FAT-PREAH1234000001X','business_name_snapshot'=>'Biznes Test','business_address_snapshot'=>'Prishtinë','business_email'=>'test@example.com','registration_number_snapshot'=>'810000001','issued_on'=>'2026-10-07','period_start'=>'2026-10-07','period_end'=>'2027-10-06','uniref'=>'PREAH1234000001X','registered_nace_code_snapshot'=>'1234','nace_description_snapshot'=>'Shërbim komunal','annual_tariff'=>'25.00','amount'=>'25.00','paid_amount'=>'0','due_on'=>'2026-11-06']);
        self::assertStringStartsWith('%PDF-',$pdf);self::assertGreaterThan(1500,strlen($pdf));
    }
}
