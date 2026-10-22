<?php
declare(strict_types=1);
namespace App\Billing;

use App\Support\Config;

/** Invoice document texts in Albanian, English and Serbian (Latin). */
final class InvoiceTranslations
{
    public const LANGUAGES = ['sq' => 'Shqip', 'en' => 'English', 'sr' => 'Srpski'];

    private const TEXTS = [
        'sq' => [
            'system' => 'Sistemi i Faturimit Komunal', 'invoice' => 'Fatura', 'subject' => 'Subjekti', 'details' => 'Detajet e faturës',
            'date' => 'Data', 'period' => 'Periudha', 'uniref' => 'UNIREF', 'status' => 'Statusi', 'nace' => 'NACE', 'due' => 'Afati',
            'registration' => 'NRB', 'description' => 'Përshkrimi', 'tariff' => 'Tarifa', 'total' => 'Totali',
            'fee_prefix' => 'Taksa për ushtrimin e veprimtarisë: ', 'invoice_total' => 'Totali i faturës', 'paid' => 'Paguar',
            'previous_debt' => 'Borxhi paraprak', 'total_obligations' => 'Total detyrime', 'address' => 'Adresa', 'email' => 'Email', 'iban' => 'IBAN',
            'due_line' => 'AFATI I PAGESËS', 'digital' => 'Dokument i gjeneruar dhe nënshkruar në mënyrë digjitale.',
            'barcode_note' => 'Paguani duke përdorur UNIREF-in',
            'PAID' => 'PAGUAR', 'PARTIALLY_PAID' => 'PJESËRISHT E PAGUAR', 'ISSUED' => 'PAPAGUAR', 'CANCELLED' => 'ANULUAR',
            'legal' => 'Kjo faturë është lëshuar nga sistemi komunal për tarifën vjetore të ushtrimit të veprimtarisë. Pagesa realizohet duke përdorur UNIREF-in e paraqitur në faturë.',
            'municipality' => 'KOMUNA E PRISHTINËS',
        ],
        'en' => [
            'system' => 'Municipal Invoicing System', 'invoice' => 'Invoice', 'subject' => 'Taxpayer', 'details' => 'Invoice details',
            'date' => 'Date', 'period' => 'Period', 'uniref' => 'UNIREF', 'status' => 'Status', 'nace' => 'NACE', 'due' => 'Due date',
            'registration' => 'Reg. No.', 'description' => 'Description', 'tariff' => 'Tariff', 'total' => 'Total',
            'fee_prefix' => 'Fee for carrying out the business activity: ', 'invoice_total' => 'Invoice total', 'paid' => 'Paid',
            'previous_debt' => 'Previous debt', 'total_obligations' => 'Total obligations', 'address' => 'Address', 'email' => 'Email', 'iban' => 'IBAN',
            'due_line' => 'PAYMENT DUE', 'digital' => 'Document generated and digitally signed.',
            'barcode_note' => 'Pay using the UNIREF',
            'PAID' => 'PAID', 'PARTIALLY_PAID' => 'PARTIALLY PAID', 'ISSUED' => 'UNPAID', 'CANCELLED' => 'CANCELLED',
            'legal' => 'This invoice was issued by the municipal system for the annual business activity fee. Payment is made using the UNIREF shown on the invoice.',
            'municipality' => 'MUNICIPALITY OF PRISHTINA',
        ],
        'sr' => [
            'system' => 'Opštinski sistem fakturisanja', 'invoice' => 'Faktura', 'subject' => 'Subjekat', 'details' => 'Detalji fakture',
            'date' => 'Datum', 'period' => 'Period', 'uniref' => 'UNIREF', 'status' => 'Status', 'nace' => 'NACE', 'due' => 'Rok plaćanja',
            'registration' => 'Reg. br.', 'description' => 'Opis', 'tariff' => 'Tarifa', 'total' => 'Ukupno',
            'fee_prefix' => 'Taksa za obavljanje delatnosti: ', 'invoice_total' => 'Ukupno po fakturi', 'paid' => 'Plaćeno',
            'previous_debt' => 'Prethodni dug', 'total_obligations' => 'Ukupne obaveze', 'address' => 'Adresa', 'email' => 'E-pošta', 'iban' => 'IBAN',
            'due_line' => 'ROK PLAĆANJA', 'digital' => 'Dokument je generisan i digitalno potpisan.',
            'barcode_note' => 'Platite koristeći UNIREF',
            'PAID' => 'PLAĆENO', 'PARTIALLY_PAID' => 'DELIMIČNO PLAĆENO', 'ISSUED' => 'NEPLAĆENO', 'CANCELLED' => 'PONIŠTENO',
            'legal' => 'Ovu fakturu je izdao opštinski sistem za godišnju taksu za obavljanje delatnosti. Plaćanje se vrši korišćenjem UNIREF-a navedenog na fakturi.',
            'municipality' => 'OPŠTINA PRIŠTINA',
        ],
    ];

    private string $language;

    public function __construct(string $language, private Config $config)
    {
        $this->language = self::normalize($language);
    }

    public static function normalize(mixed $language): string
    {
        $language = strtolower(trim((string) $language));
        return array_key_exists($language, self::LANGUAGES) ? $language : 'sq';
    }

    public function language(): string { return $this->language; }

    public function t(string $key): string { return self::TEXTS[$this->language][$key] ?? self::TEXTS['sq'][$key] ?? $key; }

    /** Municipality name: MUNICIPALITY_NAME (Albanian) or MUNICIPALITY_NAME_EN / _SR, with built-in defaults. */
    public function municipality(): string { return $this->configured('MUNICIPALITY_NAME') ?? $this->t('municipality'); }

    /** Legal footer: INVOICE_LEGAL_TEXT (Albanian) or INVOICE_LEGAL_TEXT_EN / _SR, with built-in defaults. */
    public function legal(): string { return $this->configured('INVOICE_LEGAL_TEXT') ?? $this->t('legal'); }

    public function status(string $code): string { return $this->t(in_array($code, ['PAID', 'PARTIALLY_PAID', 'ISSUED', 'CANCELLED'], true) ? $code : 'ISSUED'); }

    private function configured(string $key): ?string
    {
        $value = trim($this->config->string($this->language === 'sq' ? $key : $key.'_'.strtoupper($this->language)));
        return $value === '' ? null : $value;
    }
}
