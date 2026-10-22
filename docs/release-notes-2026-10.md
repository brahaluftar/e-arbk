# Release notes — bug fixes, finance features and responsive UI (October 2026)

Branch `feature/bugfixes-and-proposals`. Bug IDs (B-xx) and proposal IDs (S-xx) refer to [the Low Level Design](LLD-e-arbk.md).

## Before you deploy

1. **Rotate the production secrets.** `config/.env` with the production database password and `APP_KEY` was committed to Git. It is deleted in this release and ignored from now on, but it remains in the Git history: change the SQL login password and `APP_KEY` in `/cloudclusters/arbk-env/.env`, and purge the file from history if the repository was ever shared.
2. Back up the database. Migration `0026_billing_consistency_and_finance.sql` is additive but cannot be rolled back automatically.
3. Deploy as usual (`deploy-arbk.sh … deploy`), or directly from GitHub with `deploy/deploy-from-github.sh deploy --ref main` ([guide](deploy-from-github.md)). The migration runs, then preflight checks the new views and the PDF fonts.
4. Optional new settings in `/cloudclusters/arbk-env/.env` (defaults are fine):

| Key | Default | Meaning |
| --- | --- | --- |
| `BILLING_TARIFF_SOURCES` | `DISCOUNT,REGISTERED` | Tariff sources used by annual invoicing **and** the income estimate. Add `CLASSIFICATION` to also invoice businesses that only have a classification tariff. |
| `MUNICIPALITY_NAME_EN`, `MUNICIPALITY_NAME_SR` | built-in | Municipality name on English / Serbian invoices |
| `INVOICE_LEGAL_TEXT_EN`, `INVOICE_LEGAL_TEXT_SR` | built-in | Legal footer on English / Serbian invoices |
| `APP_TIMEZONE` | `Europe/Belgrade` | Default changed from Warsaw (same offset; Kosovo's zone) |

## New features

| # | Request | What was built | Where |
| --- | --- | --- | --- |
| 1 | Automatic processing of the daily payment reports from the Finance Directorate | Upload an XLSX or CSV report. Every row is matched to an invoice by UNIREF (or invoice number, or a UNIREF found in the description) and applied as a payment. Overpayments, unknown UNIREFs, cancelled invoices, invalid rows and duplicates are not applied; they are listed with the reason for review. The same file cannot be imported twice. A template workbook is downloadable. | **Financat → Pagesat** |
| 2 | Previous debt, invoice total and total obligations on the invoice | The invoice shows **Totali i faturës**, **Paguar**, **Borxhi paraprak** (unpaid balance of earlier invoices of the same business or unit) and **Total detyrime**. PDFs are rendered on demand, so the figures are always current. | Invoice PDF, invoice page |
| 3 | Larger barcode text | UNIREF under the barcode is printed bold at 10 pt, spaced, with "Paguani duke përdorur UNIREF-in". | Invoice PDF |
| 4 | Invoices in other languages | Albanian, English and Serbian (Latin, with č ć š ž đ). An embedded font (Liberation Sans, cp1250) replaces Arial. | PDF SQ / EN / SR buttons; language choice when emailing |
| 5 | Excel of clients with unpaid amounts | **Shkarko klientët (Excel)**: one row per client with NRB, contacts, number of unpaid invoices, invoiced, paid, unpaid, overdue, oldest due date and last payment; all years or up to a year. | **Financat → Përmbledhje** |
| 6 | Comment on invoice edits and history | Invoices can be edited (amount, due date, note), cancelled or restored. A **Koment** is mandatory; every change is stored with the old and new values, who and when. The **Historiku** timeline also shows creation, payments, storno and emails. | Invoice page |
| 6b | Delete/storno wrong payments | **Storno** with a mandatory reason. The payment stays in the ledger as reversed and no longer counts; the invoice status is recalculated. | Invoice page → Pagesat |
| 7 | Permit only without debt | The work permit can be issued only for an active business with no unpaid balance (main business and units). The business page shows the debt and why the permit is blocked. Permits can be revoked with a reason and downloaded by staff. | Business page → Lejet |
| 8 | Registration and invoicing of units | Units are registered and fully edited (name, address, contacts, NACE of exactly 4 characters, tariff, automatic invoicing, active). Annual jobs now include every active unit with automatic invoicing; a single unit can be invoiced for any year. | Business page → Njësitë; Financat → Faturimi vjetor |

## Bug fixes

| Bug | Fix |
| --- | --- |
| B-01 | `config/.env` deleted; env files ignored by Git |
| B-02 | All staff pages require a staff role (`requireStaff`/`requireEditor`); a test checks every admin endpoint |
| B-03 | Unit invoices no longer block or distort the main business's annual invoice and the dashboard |
| B-04 | Invoice status (paid / partly paid / unpaid / cancelled) follows payments (view `v_invoice_balance`; backfilled) |
| B-05, B-06 | Invoice PDFs are no longer cached; no stale balances, no orphan files |
| B-07 | Closed-business imports (and any closure date) mark the business inactive |
| B-08 | One rule for status and tariff (`v_business_billing` + `BillingPolicy`) used by dashboard, annual job and manual invoices |
| B-09 | The year selector has a "Shfaq" button (inline scripts are blocked by the CSP) |
| B-10 | Database transfer includes units, invoice history, payment imports and all three views |
| B-11 | Bulk PDF split into parts of 500 invoices |
| B-12 | Women/veteran filters restored |
| B-13, B-14 | Unit NACE must be 4 characters; unit import validates tariff, email and automatic invoicing; unknown NRB rows are reported |
| B-15 | Empty Excel cells no longer erase existing data |
| B-16 | Interrupted imports return to the queue after 6 hours; failed imports can be retried without re-uploading |
| B-17 | Impossible dates (31/02) are reported instead of shifted |
| B-18, B-19 | Failed invitation emails are sent again; bulk invitations run in rounds of 200; email links expire with the invitation |
| B-20 | Password, role or deactivation changes end the user's sessions |
| B-21 | Logged-in users on reset pages go to their own landing page |
| B-22 | Dashboard cards open the matching filter |
| B-23–B-25 | Responsive tables with labels, visible skip link, valid button font |
| B-26 | Root-hosted (XAMPP) mode serves portal, links, verification and logo |
| B-27 | Manual invoices get a UNIREF; PDF/email errors show a message instead of HTTP 500 |
| B-28 | Permit file is removed if saving fails; correct tariff NACE; revoke; expired status shown |
| B-30–B-36 | Timezone, classification transaction, Albanian labels for appeals, first-sheet detection, cron example, CLI guards, unit invoice year |

## Simplifications

- One invoice factory (S-06): every invoice gets UNIREF, history event and audit; `AnnualInvoiceService` (unused) and the separate unit-invoice generator removed.
- Views `v_business_billing` and `v_invoice_balance` replace ~10 copies of status/balance SQL (S-05, S-07).
- One rate limiter for logins and reset requests; one Graph mail path with token reuse (S-08, S-09).
- Status labels and badge colours in one place (`App\Support\Labels`, S-10); template helpers (`csrf_field`, `money`, `date_sq`, `badge`, `run_action`, `require_post`, S-11).
- "Klasifikimi NACE" is a tab of the business register (S-15); finance split into Përmbledhje / Faturimi vjetor / Faturat / Pagesat (S-17); developer panel removed from the dashboard (S-19).
- Business-user linking by email + NRB instead of drop-downs of every account (S-16).
- One stylesheet instead of three; FPDI dependency removed.

## User interface

Mobile-first redesign (LLD §17): grouped navigation that collapses into a menu below 1024 px without JavaScript; compact page headers; filters collapsed on phones; tables become labelled cards on phones and tablets; Albanian labels and coloured badges for every status; dates `dd.mm.yyyy` and amounts `1.234,56 €`. `composer ui-check` renders every screen with sample data and verifies that no page scrolls horizontally at 360, 768 and 1280 px.

## Behaviour changes to note

- A business counts as **active** only if ATK does not list it **and** it is not passive, closed (`ATK_MBYLLUR`, closure date, status "Shuar"). Counts on the dashboard can change after the upgrade.
- With the default `BILLING_TARIFF_SOURCES`, the income estimate no longer counts businesses that only have a classification tariff, because they were never invoiced.
- `bin/run-scheduled.php` no longer processes imports (the every-minute import worker does).

## Not verified here

The code was linted and unit-tested (52 tests) and every screen was rendered and screenshotted, but no SQL Server was available: the migration and the new SQL must be run on a staging copy of the database before production.
