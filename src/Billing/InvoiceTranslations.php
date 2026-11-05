<?php
declare(strict_types=1);
namespace App\Billing;

use App\Support\Config;

/**
 * Invoice document texts in Albanian, English and Serbian (Latin).
 * The texts marked "configurable" can be replaced from the server .env (key, key_EN, key_SR); see docs/invoice-texts.md.
 */
final class InvoiceTranslations
{
    public const LANGUAGES = ['sq' => 'Shqip', 'en' => 'English', 'sr' => 'Srpski'];

    /** Text key => .env key that replaces it. */
    private const CONFIGURABLE = [
        'municipality' => 'MUNICIPALITY_NAME', 'issuer' => 'INVOICE_ISSUER', 'tax_activity' => 'INVOICE_TAX_LABEL',
        'tax_professional' => 'INVOICE_TAX_LABEL_PROFESSIONAL', 'legal_basis' => 'INVOICE_LEGAL_BASIS',
        'payment_terms' => 'INVOICE_PAYMENT_TERMS', 'warning' => 'INVOICE_WARNING', 'legal' => 'INVOICE_LEGAL_TEXT',
    ];

    private const TEXTS = [
        'sq' => [
            'system' => 'Sistemi i Faturimit Komunal', 'invoice' => 'FATURA', 'fiscal_year' => 'Viti fiskal',
            'subject' => 'Subjekti', 'details' => 'Detajet e faturës', 'client_number' => 'Nr. i klientit', 'registration' => 'NRB',
            'invoice_number' => 'Nr. i faturës', 'date' => 'Data', 'period' => 'Periudha', 'status' => 'Statusi', 'nace' => 'NACE',
            'description' => 'Përshkrimi', 'tariff' => 'Tarifa', 'total' => 'Totali',
            'tax_for_year' => 'Taksa për vitin %d', 'previous_debt' => 'Borxhi paraprak', 'paid' => 'Paguar', 'grand_total' => 'TOTALI',
            'uniref_label' => 'UNIREF – referenca e pagesës', 'barcode_note' => 'Gjatë pagesës shënoni UNIREF-in si referencë.',
            'address' => 'Adresa', 'email' => 'Email', 'iban' => 'IBAN', 'legal_basis_title' => 'Baza ligjore',
            'digital' => 'Dokument i gjeneruar dhe nënshkruar në mënyrë digjitale.',
            'PAID' => 'PAGUAR', 'PARTIALLY_PAID' => 'PJESËRISHT E PAGUAR', 'ISSUED' => 'PAPAGUAR', 'CANCELLED' => 'ANULUAR',
            'municipality' => 'KOMUNA E PRISHTINËS',
            'issuer' => 'Drejtoria për Zhvillim Ekonomik dhe Inovacion',
            'tax_activity' => 'Taksa për ushtrimin e veprimtarisë',
            'tax_professional' => 'Taksa për leje për shërbime profesionale',
            'legal_basis' => 'Në pajtim me dispozitat e nenit 24, paragrafi 2 të Ligjit për Vetëqeverisje Lokale Nr. 03/L-040 të dt. 04.06.2008, nenet 10 dhe 11 të Ligjit për Financat e Pushtetit Lokal Nr. 03/L-049 të dt. 03.06.2008, Rregullorës për ndryshimin dhe plotësimin e rregullorës për tarifa, ngarkesa dhe gjoba komunale nr. 01-030/01-148721/26 të dt. 30.9.2026, Drejtoria për Zhvillim Ekonomik dhe Inovacion lëshon këtë faturë.',
            'payment_terms' => 'Afati i pagesës: 30 ditë nga momenti i pranimit të faturës.',
            'warning' => 'VËREJTJE: Në rast të mospagesës mjetet do të realizohen përmes urdhërit përmbarimor.',
            'legal' => 'Kjo faturë është lëshuar nga sistemi komunal i faturimit. Pagesa realizohet duke përdorur UNIREF-in e paraqitur në faturë.',
        ],
        'en' => [
            'system' => 'Municipal Invoicing System', 'invoice' => 'INVOICE', 'fiscal_year' => 'Fiscal year',
            'subject' => 'Taxpayer', 'details' => 'Invoice details', 'client_number' => 'Client No.', 'registration' => 'Reg. No.',
            'invoice_number' => 'Invoice No.', 'date' => 'Date', 'period' => 'Period', 'status' => 'Status', 'nace' => 'NACE',
            'description' => 'Description', 'tariff' => 'Tariff', 'total' => 'Total',
            'tax_for_year' => 'Tax for %d', 'previous_debt' => 'Previous debt', 'paid' => 'Paid', 'grand_total' => 'TOTAL',
            'uniref_label' => 'UNIREF – payment reference', 'barcode_note' => 'Quote the UNIREF as the payment reference.',
            'address' => 'Address', 'email' => 'Email', 'iban' => 'IBAN', 'legal_basis_title' => 'Legal basis',
            'digital' => 'Document generated and digitally signed.',
            'PAID' => 'PAID', 'PARTIALLY_PAID' => 'PARTIALLY PAID', 'ISSUED' => 'UNPAID', 'CANCELLED' => 'CANCELLED',
            'municipality' => 'MUNICIPALITY OF PRISHTINA',
            'issuer' => 'Directorate for Economic Development and Innovation',
            'tax_activity' => 'Business activity tax',
            'tax_professional' => 'Professional services permit tax',
            'legal_basis' => 'In accordance with Article 24, paragraph 2 of the Law on Local Self-Government No. 03/L-040 of 04.06.2008, Articles 10 and 11 of the Law on Local Government Finance No. 03/L-049 of 03.06.2008, and the Regulation amending and supplementing the Regulation on municipal tariffs, charges and fines No. 01-030/01-148721/26 of 30.09.2026, the Directorate for Economic Development and Innovation issues this invoice.',
            'payment_terms' => 'Payment due: within 30 days of receiving the invoice.',
            'warning' => 'NOTE: In case of non-payment, the amount will be collected through an enforcement order.',
            'legal' => 'This invoice was issued by the municipal invoicing system. Payment is made using the UNIREF shown on the invoice.',
        ],
        'sr' => [
            'system' => 'Opštinski sistem fakturisanja', 'invoice' => 'FAKTURA', 'fiscal_year' => 'Fiskalna godina',
            'subject' => 'Subjekat', 'details' => 'Detalji fakture', 'client_number' => 'Br. klijenta', 'registration' => 'Reg. br.',
            'invoice_number' => 'Br. fakture', 'date' => 'Datum', 'period' => 'Period', 'status' => 'Status', 'nace' => 'NACE',
            'description' => 'Opis', 'tariff' => 'Tarifa', 'total' => 'Ukupno',
            'tax_for_year' => 'Taksa za %d. godinu', 'previous_debt' => 'Prethodni dug', 'paid' => 'Plaćeno', 'grand_total' => 'UKUPNO',
            'uniref_label' => 'UNIREF – poziv na broj', 'barcode_note' => 'Prilikom plaćanja navedite UNIREF kao poziv na broj.',
            'address' => 'Adresa', 'email' => 'E-pošta', 'iban' => 'IBAN', 'legal_basis_title' => 'Pravni osnov',
            'digital' => 'Dokument je generisan i digitalno potpisan.',
            'PAID' => 'PLAĆENO', 'PARTIALLY_PAID' => 'DELIMIČNO PLAĆENO', 'ISSUED' => 'NEPLAĆENO', 'CANCELLED' => 'PONIŠTENO',
            'municipality' => 'OPŠTINA PRIŠTINA',
            'issuer' => 'Direkcija za ekonomski razvoj i inovacije',
            'tax_activity' => 'Taksa za obavljanje delatnosti',
            'tax_professional' => 'Taksa za dozvolu za profesionalne usluge',
            'legal_basis' => 'U skladu sa odredbama člana 24, stav 2 Zakona o lokalnoj samoupravi br. 03/L-040 od 04.06.2008, članova 10 i 11 Zakona o finansijama lokalne samouprave br. 03/L-049 od 03.06.2008. i Uredbe o izmeni i dopuni Uredbe o opštinskim tarifama, naknadama i kaznama br. 01-030/01-148721/26 od 30.09.2026, Direkcija za ekonomski razvoj i inovacije izdaje ovu fakturu.',
            'payment_terms' => 'Rok plaćanja: 30 dana od prijema fakture.',
            'warning' => 'NAPOMENA: U slučaju neplaćanja, sredstva će biti naplaćena putem rešenja o izvršenju.',
            'legal' => 'Ovu fakturu je izdao opštinski sistem fakturisanja. Plaćanje se vrši korišćenjem UNIREF-a navedenog na fakturi.',
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

    /** Text in the invoice language; configurable texts come from the .env when set there. */
    public function t(string $key): string
    {
        if (isset(self::CONFIGURABLE[$key])) {
            $configured = $this->configured(self::CONFIGURABLE[$key]);
            if ($configured !== null) return $configured;
        }
        return self::TEXTS[$this->language][$key] ?? self::TEXTS['sq'][$key] ?? $key;
    }

    public function municipality(): string { return $this->t('municipality'); }

    public function status(string $code): string { return $this->t(in_array($code, ['PAID', 'PARTIALLY_PAID', 'ISSUED', 'CANCELLED'], true) ? $code : 'ISSUED'); }

    /**
     * Tax name of the invoice line: professional services for the NACE codes listed in INVOICE_PROFESSIONAL_NACE
     * (prefixes, e.g. "69,70,71" or "6910,7111"), the business-activity tax otherwise. An empty list uses one label for all.
     */
    public function taxLabel(?string $naceCode): string
    {
        return self::isProfessional($naceCode, $this->config->string('INVOICE_PROFESSIONAL_NACE')) ? $this->t('tax_professional') : $this->t('tax_activity');
    }

    public static function isProfessional(?string $naceCode, string $prefixes): bool
    {
        $code = preg_replace('/[^0-9]/', '', (string) $naceCode) ?? '';
        if ($code === '') return false;
        foreach (explode(',', $prefixes) as $prefix) {
            $prefix = preg_replace('/[^0-9]/', '', $prefix) ?? '';
            if ($prefix !== '' && str_starts_with($code, $prefix)) return true;
        }
        return false;
    }

    private function configured(string $key): ?string
    {
        $value = trim($this->config->string($this->language === 'sq' ? $key : $key.'_'.strtoupper($this->language)));
        return $value === '' ? null : $value;
    }
}
