<?php
declare(strict_types=1);
use App\Billing\InvoicePdfRenderer;
use App\Billing\InvoiceTranslations;
use App\Support\Config;
use PHPUnit\Framework\TestCase;
final class InvoicePdfRendererTest extends TestCase
{
    /** @return array<string,mixed> */
    public static function invoice(): array
    {
        return ['invoice_number'=>'PREAH1234000001X','business_name_snapshot'=>'Biznes Test Çelësi','business_address_snapshot'=>'Rr. Nënë Tereza, Prishtinë','business_email'=>'test@example.com','registration_number_snapshot'=>'810000001','issued_on'=>'2026-10-07','period_start'=>'2026-10-07','period_end'=>'2027-10-06','uniref'=>'PREAH1234000001X','registered_nace_code_snapshot'=>'1234','nace_description_snapshot'=>'Shërbim komunal','annual_tariff'=>'125.00','amount'=>'125.00','paid_amount'=>'25.00','previous_debt'=>'40.50','balance_status'=>'PARTIALLY_PAID','due_on'=>'2026-11-06'];
    }

    private function config(): Config
    {
        return Config::load(sys_get_temp_dir().'/arbk-pdf-'.bin2hex(random_bytes(4)),require dirname(__DIR__).'/config/defaults.php');
    }

    public function testRendersInvoiceInEveryLanguage():void
    {
        foreach(array_keys(InvoiceTranslations::LANGUAGES) as $language){
            $pdf=(new InvoicePdfRenderer($this->config()))->render(self::invoice(),$language);
            self::assertStringStartsWith('%PDF-',$pdf);
            self::assertStringContainsString('/FontFile2',$pdf);self::assertStringNotContainsString('/Helvetica',$pdf);
        }
    }

    public function testTranslationsFallBackToAlbanian():void
    {
        $translations=new InvoiceTranslations('de',$this->config());
        self::assertSame('sq',$translations->language());
        self::assertSame('Borxhi paraprak',$translations->t('previous_debt'));
        self::assertSame('Prethodni dug',(new InvoiceTranslations('sr',$this->config()))->t('previous_debt'));
        self::assertSame('Total obligations',(new InvoiceTranslations('EN',$this->config()))->t('total_obligations'));
    }
}
