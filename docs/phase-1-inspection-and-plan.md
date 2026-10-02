# Phase 1 inspection and implementation plan

## Environment findings

- Target server/database: `alternator\sql2025`, database `ARBK`, SQL Server with Windows integrated authentication available locally.
- Runtime: PHP 8.2.0; `pdo_sqlsrv` and `sqlsrv` are enabled.
- The target workspace was empty at inspection time.
- The adjacent `e-transporti` application is framework-free PHP using PSR-4 classes, PDO SQL Server prepared statements, physical PHP routes, migrations split by `GO`, session authentication, database roles/permissions, CSRF tokens, a shared layout, responsive municipal styling, CLI workers, Monolog, and FPDF.
- Phase 2 reuses those architectural conventions and layout language. Its identity tables are local because `e-transporti` is a separate application/database; no safe shared identity provider or cross-application session contract was present.

## Master schema observed

### `ARBK_LIST` (36,806 rows; revised schema)

- Identity: `ARBKrowGUID` (uniqueidentifier), `REGULATION_ID` (unique int), and `NRBIZ`.
- Name/type: `Emri`, `Lloji`.
- Registered NACE: `NACE_CODE_REG`, `NACEPERSHKRIMI`.
- Tariff targets: `NACE_CODE_TARIFF`, `nace_veprimtaria_tariff`, `NaceRowGuid`.
- Municipality/status: `Qyteti`, `Statusi`.
- Legacy ATK fields: `ATK_MBYLLUR`, `ATK_DATEMBYLLJE`.
- No fiscal number, street address, email, telephone, or trade-name field exists.

### `ATK_LIST` (64,261 rows)

- Matching fields: `NRB` (business number), `NRFISKAL`, `NRTVSH`.
- Status fields: `Statusi`, `DataMbylljes`.
- ARBK has no fiscal number, so the safest available match is normalized `ARBK_LIST.NRBIZ = ATK_LIST.NRB`.
- 75 ARBK businesses matched during inspection. All had one distinct inactive status; 832 ATK business numbers are duplicated, so match multiplicity and conflicting statuses are retained for review.

### `NACE_LIST` (233 rows; revised schema)

- Stable mapping identity: `NACErowGUID` (uniqueidentifier).
- Legacy regulation identity: `REGULATIONID` (int, not unique).
- Category/sector: `Sektori`.
- NACE: `NACE_CODE`.
- Category detail: `Veprimtaria`.
- Tariff: `Tarifa` (float).
- The table has 233 unique `NACErowGUID` values but only 180 distinct `REGULATIONID` values. Application relations therefore use `NACErowGUID`.

## Phase 2 design

1. Keep all three imported tables read-only from application code.
2. Materialize normalized ATK status in `business_atk_status`, recording the match rule, match count, source record, and synchronization time.
3. Store versioned application classifications in `business_nace_assignments`; only one active row per business is allowed.
4. Populate `NACE_CODE_TARIFF` only when `NACE_CODE_REG = NACE_LIST.NACE_CODE`; populate `nace_veprimtaria_tariff` only when `NACEPERSHKRIMI = NACE_LIST.Veprimtaria`. Existing non-null manual values are preserved.
5. Set `NaceRowGuid` and automatically classify only when code and activity jointly identify exactly one NACE row.
6. Validate manual selections by `NACErowGUID` and exact registered NACE code.
7. Record mapping sync, automatic/manual assignments, and ATK runs in `audit_log`.
8. Expose listing, filtering, detail, and constrained classification pages with ADMIN/OFFICIAL/READ_ONLY permissions.

## Later migration plan

Phase 3 should add effective-dated `tariff_rules`, `app_settings`, and a concurrency-safe `invoice_sequences` table. Phase 4 should add immutable invoice snapshots, unique UNIREF, bulk-run and idempotency tables. Later phases should separately add email templates/outbox, hashed portal/link tokens, click events, contact-update history, and AI action logs. These are intentionally not created before their owning phase is implemented.

## Validation note

After the manual schema/data revision, synchronization preserved 793 existing values, filled 18,398 missing tariff codes and 27 missing tariff activities, and created 820 exact GUID relations. Final validation found zero invalid code, activity, or GUID relations. A second run changed zero records.
