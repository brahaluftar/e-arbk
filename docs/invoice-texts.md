# Invoice texts and layout: guidelines

This guide covers the redesigned invoice (November 2026), which follows the recommendations for the business tax invoice. It lists every text printed on the invoice, the wording to use, and where to change it.

Invoices are rendered **when they are opened**, so a change to these texts or to the layout applies to **every invoice, old and new**, the next time it is opened, printed or emailed.

## 1. Layout: the five parts and what changed

| Part | Content | Change from the recommendations |
|---|---|---|
| 1 Header | Logo, "Sistemi i Faturimit Komunal", municipality, issuing directorate, **FATURA**, fiscal year | **UNIREF removed** from the header (rec. 1) |
| 2 Subjekti | Business name, address, email, **Nr. i klientit**, NRB | **Client number added**: unique per business, assigned automatically (rec. 2) |
| 3 Detajet e faturës | **Nr. i faturës**, Data, Periudha, Statusi, NACE | Label **"UNIREF" renamed "Nr. i faturës"**; the number itself is unchanged (it is the UNIREF) (rec. 3). The fixed due date is no longer printed; see part 5 |
| 4 Description and amounts | Tax line: **activity tax or professional-services tax** by NACE code (rec. 4). Amounts box: **Taksa për vitin YYYY**, **Borxhi paraprak**, (Paguar, only when something was paid), **TOTALI** | Payment reference shown **larger**: framed UNIREF in 19 pt bold, with the barcode below it |
| 5 Footer | Address, email, IBAN; **Baza ligjore**; **Afati i pagesës**; **VËREJTJE**; system note; "Dokument i gjeneruar…" | Legal basis, payment terms and the enforcement warning added (rec. 5) |

**How the amounts are calculated:**
- **Taksa për vitin YYYY** is the amount of this invoice (0 if the invoice is cancelled).
- **Borxhi paraprak** is the unpaid balance of the same business's invoices from earlier fiscal years.
- **Paguar** is printed only when payments exist on this invoice.
- **TOTALI** = (tax of the year − paid on this invoice) + previous debt.

## 2. Texts you can change without a code release

These texts are read from the server environment file **`/cloudclusters/arbk-env/.env`**.
- **Empty or missing key:** the built-in text in the tables below is used.
- **English and Serbian invoices:** each key also has an `_EN` and an `_SR` variant, for example `INVOICE_WARNING_EN`. Empty means the built-in translation.

| On the invoice | `.env` key | Built-in Albanian text (use as recommended) |
|---|---|---|
| Municipality (header) | `MUNICIPALITY_NAME` | KOMUNA E PRISHTINËS |
| Issuing directorate (header) | `INVOICE_ISSUER` | Drejtoria për Zhvillim Ekonomik dhe Inovacion |
| Tax line, general businesses | `INVOICE_TAX_LABEL` | Taksa për ushtrimin e veprimtarisë |
| Tax line, professional services | `INVOICE_TAX_LABEL_PROFESSIONAL` | Taksa për leje për shërbime profesionale |
| Which NACE codes are professional services | `INVOICE_PROFESSIONAL_NACE` | `69,70,71,72,73,74,75` (NACE section M) |
| Legal basis (part 5) | `INVOICE_LEGAL_BASIS` | Në pajtim me dispozitat e nenit 24, paragrafi 2 të Ligjit për Vetëqeverisje Lokale Nr. 03/L-040 të dt. 04.06.2008, nenet 10 dhe 11 të Ligjit për Financat e Pushtetit Lokal Nr. 03/L-049 të dt. 03.06.2008, Rregullorës për ndryshimin dhe plotësimin e rregullorës për tarifa, ngarkesa dhe gjoba komunale nr. 01-030/01-148721/26 të dt. 30.9.2026, Drejtoria për Zhvillim Ekonomik dhe Inovacion lëshon këtë faturë. |
| Payment terms | `INVOICE_PAYMENT_TERMS` | Afati i pagesës: 30 ditë nga momenti i pranimit të faturës. |
| Warning (printed in red) | `INVOICE_WARNING` | VËREJTJE: Në rast të mospagesës mjetet do të realizohen përmes urdhërit përmbarimor. |
| System note (small grey text) | `INVOICE_LEGAL_TEXT` | Kjo faturë është lëshuar nga sistemi komunal i faturimit. Pagesa realizohet duke përdorur UNIREF-in e paraqitur në faturë. |
| Address / Email / IBAN (footer) | `MUNICIPALITY_ADDRESS`, `MUNICIPALITY_CONTACT`, `MUNICIPAL_BANK_ACCOUNT` | No built-in text. **Must be filled in**; otherwise these fields are printed empty and the admin dashboard shows a warning |

**Built-in English and Serbian texts** (used for `?lang=en` and `?lang=sr` invoices):

| Key | English | Serbian |
|---|---|---|
| `INVOICE_ISSUER` | Directorate for Economic Development and Innovation | Direkcija za ekonomski razvoj i inovacije |
| `INVOICE_TAX_LABEL` | Business activity tax | Taksa za obavljanje delatnosti |
| `INVOICE_TAX_LABEL_PROFESSIONAL` | Professional services permit tax | Taksa za dozvolu za profesionalne usluge |
| `INVOICE_PAYMENT_TERMS` | Payment due: within 30 days of receiving the invoice. | Rok plaćanja: 30 dana od prijema fakture. |
| `INVOICE_WARNING` | NOTE: In case of non-payment, the amount will be collected through an enforcement order. | NAPOMENA: U slučaju neplaćanja, sredstva će biti naplaćena putem rešenja o izvršenju. |
| `INVOICE_LEGAL_BASIS` | In accordance with Article 24, paragraph 2 of the Law on Local Self-Government No. 03/L-040 of 04.06.2008, Articles 10 and 11 of the Law on Local Government Finance No. 03/L-049 of 03.06.2008, and the Regulation amending and supplementing the Regulation on municipal tariffs, charges and fines No. 01-030/01-148721/26 of 30.09.2026, the Directorate for Economic Development and Innovation issues this invoice. | U skladu sa odredbama člana 24, stav 2 Zakona o lokalnoj samoupravi br. 03/L-040 od 04.06.2008, članova 10 i 11 Zakona o finansijama lokalne samouprave br. 03/L-049 od 03.06.2008. i Uredbe o izmeni i dopuni Uredbe o opštinskim tarifama, naknadama i kaznama br. 01-030/01-148721/26 od 30.09.2026, Direkcija za ekonomski razvoj i inovacije izdaje ovu fakturu. |

### How to change a text

1. On the server, edit `/cloudclusters/arbk-env/.env` (as root): one line per key, without quotes.
   ```
   INVOICE_LEGAL_BASIS=Në pajtim me dispozitat e nenit 24, ... lëshon këtë faturë.
   INVOICE_WARNING_EN=NOTE: ...
   ```
   The file is UTF-8, so ë, ç, č and ć are fine. Keep each text on **one line**.
2. Open any invoice PDF to check. The `.env` is read on every request, so no restart or deploy is needed.
3. Keep a copy before editing: `cp /cloudclusters/arbk-env/.env /cloudclusters/arbk-env/.env.bak-$(date +%Y%m%d%H%M)`.

### Tax line: general or professional services

The recommendation allows either one general name or two specific names. Both are supported:

- **Two names (default).** Businesses whose tariff NACE code (or registered code, if there is no tariff code) **starts with** one of the values in `INVOICE_PROFESSIONAL_NACE` get `INVOICE_TAX_LABEL_PROFESSIONAL`. All others get `INVOICE_TAX_LABEL`.
  - The default list is NACE section M (69–75): legal, accounting, consulting, architecture and engineering, research, advertising, design.
  - To add or remove activities, list divisions or full codes, e.g. `INVOICE_PROFESSIONAL_NACE=69,70,71,72,73,74,75,8621,8622,8623` (adds general and specialist medical practice and dental practice).
- **One general name.** Leave the list empty (`INVOICE_PROFESSIONAL_NACE=`) and set one name for everyone, e.g. `INVOICE_TAX_LABEL=Taksa për ushtrimin e veprimtarisë dhe për shërbime profesionale`.

The activity description from ARBK (for example "Ndërmarrje projektuese dhe inxhinierike") is printed on a second line under the tax name.

## 3. Fixed labels (changed only in the code)

Short labels are in `src/Billing/InvoiceTranslations.php` (array `TEXTS`, one block per language). They need a code change, a pull request and a deploy.

| Label | sq | en | sr |
|---|---|---|---|
| System name | Sistemi i Faturimit Komunal | Municipal Invoicing System | Opštinski sistem fakturisanja |
| Title | FATURA | INVOICE | FAKTURA |
| Fiscal year | Viti fiskal | Fiscal year | Fiskalna godina |
| Client number | Nr. i klientit | Client No. | Br. klijenta |
| Invoice number | Nr. i faturës | Invoice No. | Br. fakture |
| Tax of the year | Taksa për vitin %d | Tax for %d | Taksa za %d. godinu |
| Previous debt | Borxhi paraprak | Previous debt | Prethodni dug |
| Total | TOTALI | TOTAL | UKUPNO |
| Payment reference | UNIREF – referenca e pagesës | UNIREF – payment reference | UNIREF – poziv na broj |
| Under the UNIREF | Gjatë pagesës shënoni UNIREF-in si referencë. | Quote the UNIREF as the payment reference. | Prilikom plaćanja navedite UNIREF kao poziv na broj. |
| Legal basis heading | Baza ligjore | Legal basis | Pravni osnov |

## 4. Client number (Nr. i klientit)

- **Assignment:** every business has one, unique.
  - Existing businesses were numbered from **100001** in ARBK order.
  - New businesses, for example from an ARBK import, get the next free number automatically.
- **Not editable:** the number is assigned by the system and stays the same.
- **Where it appears:** on every invoice of the business, old and new, on the business page, and in the Excel "Detyrimet e papaguara" (unpaid obligations). You can also search by it in the business register and the invoice list.

## 5. Invoice number (Nr. i faturës)

- **The number does not change:** the invoice number is the UNIREF, as before (for example `PREAH71120005439`). Only the label in "Detajet e faturës" changed, from "UNIREF" to "Nr. i faturës".
- **The UNIREF is also the payment reference.** It is printed again, framed and large, above the barcode.
- **Manual invoices** can still be given an external number.
- **If a server already ran the first version of migration 0029**, which gave invoices separate numbers (`2026-000001`), migration 0031 puts the UNIREF back as the number. It writes the change to the invoice history ("Numri i faturës u rikthye në UNIREF.").

## 6. Rework of existing invoices

Migrations 0029 and 0031 run automatically during the deploy. They do the following:
1. Give every business a client number.
2. Queue the annual-job bulk exports (PDF and Excel under "Faturimi vjetor") to be rebuilt with the new design by the export worker.
3. Keep the UNIREF as the invoice number (section 5).

Single invoices need nothing: they always show the new design. Back up the database before deploying.

## 7. Points to confirm before printing

- **"30 ditë nga momenti i pranimit":** the system still stores a due date (issue date + `INVOICE_DUE_DAYS`, 30 by default). It uses it only internally, to mark invoices as overdue; it is no longer printed.
- **English and Serbian legal texts** are working translations. Have them reviewed, then set `INVOICE_LEGAL_BASIS_EN` / `_SR`, `INVOICE_WARNING_EN` / `_SR` if the wording changes.
- **Professional-services list:** confirm which activities the regulation treats as professional services, and adjust `INVOICE_PROFESSIONAL_NACE`.
