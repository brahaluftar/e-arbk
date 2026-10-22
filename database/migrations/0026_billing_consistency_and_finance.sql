SET XACT_ABORT ON;
GO

-- Payments entered by mistake are reversed (storno), never deleted, so the ledger keeps its history.
ALTER TABLE dbo.business_invoice_payments ADD
    reversed_at datetime2(3) NULL,
    reversed_by_user_id bigint NULL,
    reversal_reason nvarchar(500) NULL,
    import_row_id bigint NULL;
GO
ALTER TABLE dbo.business_invoice_payments ADD
    CONSTRAINT FK_business_invoice_payments_reverser FOREIGN KEY(reversed_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_invoice_payments_reversal CHECK(
        (reversed_at IS NULL AND reversed_by_user_id IS NULL AND reversal_reason IS NULL)
        OR (reversed_at IS NOT NULL AND reversed_by_user_id IS NOT NULL AND reversal_reason IS NOT NULL));
GO

-- Invoice history: every creation, edit, cancellation and payment change with who, when and a comment.
CREATE TABLE dbo.business_invoice_events (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_business_invoice_events PRIMARY KEY,
    invoice_id bigint NOT NULL,
    event_code varchar(30) NOT NULL,
    changes_json nvarchar(max) NULL,
    comment nvarchar(1000) NULL,
    actor_user_id bigint NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_business_invoice_events_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT FK_business_invoice_events_invoice FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id),
    CONSTRAINT FK_business_invoice_events_actor FOREIGN KEY(actor_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_business_invoice_events_code CHECK(event_code IN ('CREATED','EDITED','CANCELLED','RESTORED','PAYMENT_RECORDED','PAYMENT_REVERSED','EMAILED')),
    CONSTRAINT CK_business_invoice_events_json CHECK(changes_json IS NULL OR ISJSON(changes_json)=1)
);
CREATE INDEX IX_business_invoice_events_invoice ON dbo.business_invoice_events(invoice_id,created_at,id);
GO
INSERT dbo.business_invoice_events(invoice_id,event_code,comment,actor_user_id,created_at)
SELECT id,'CREATED',N'Regjistruar para historikut të faturave.',created_by_user_id,created_at FROM dbo.business_invoices;
GO

-- Daily payment reports received from the Finance Directorate.
CREATE TABLE dbo.payment_import_batches (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_payment_import_batches PRIMARY KEY,
    original_filename nvarchar(255) NOT NULL,
    file_sha256 char(64) NOT NULL,
    status_code varchar(20) NOT NULL CONSTRAINT DF_payment_import_batches_status DEFAULT 'PROCESSING',
    total_rows int NOT NULL CONSTRAINT DF_payment_import_batches_total DEFAULT 0,
    applied_rows int NOT NULL CONSTRAINT DF_payment_import_batches_applied DEFAULT 0,
    review_rows int NOT NULL CONSTRAINT DF_payment_import_batches_review DEFAULT 0,
    duplicate_rows int NOT NULL CONSTRAINT DF_payment_import_batches_duplicate DEFAULT 0,
    applied_amount decimal(18,2) NOT NULL CONSTRAINT DF_payment_import_batches_amount DEFAULT 0,
    error_message nvarchar(1900) NULL,
    uploaded_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_payment_import_batches_created DEFAULT SYSUTCDATETIME(),
    completed_at datetime2(3) NULL,
    CONSTRAINT FK_payment_import_batches_user FOREIGN KEY(uploaded_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT UQ_payment_import_batches_hash UNIQUE(file_sha256),
    CONSTRAINT CK_payment_import_batches_status CHECK(status_code IN ('PROCESSING','COMPLETED','FAILED'))
);
CREATE TABLE dbo.payment_import_rows (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_payment_import_rows PRIMARY KEY,
    batch_id bigint NOT NULL,
    source_row_number int NOT NULL,
    uniref varchar(40) NULL,
    invoice_number nvarchar(80) NULL,
    amount decimal(18,2) NULL,
    paid_on date NULL,
    reference nvarchar(100) NULL,
    payer_name nvarchar(255) NULL,
    status_code varchar(20) NOT NULL,
    message nvarchar(500) NULL,
    invoice_id bigint NULL,
    payment_id bigint NULL,
    CONSTRAINT FK_payment_import_rows_batch FOREIGN KEY(batch_id) REFERENCES dbo.payment_import_batches(id),
    CONSTRAINT FK_payment_import_rows_invoice FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id),
    CONSTRAINT FK_payment_import_rows_payment FOREIGN KEY(payment_id) REFERENCES dbo.business_invoice_payments(id),
    CONSTRAINT UQ_payment_import_rows_source UNIQUE(batch_id,source_row_number),
    CONSTRAINT CK_payment_import_rows_status CHECK(status_code IN ('APPLIED','DUPLICATE','UNMATCHED','OVERPAYMENT','CANCELLED_INVOICE','INVALID'))
);
CREATE INDEX IX_payment_import_rows_review ON dbo.payment_import_rows(batch_id,status_code);
GO
ALTER TABLE dbo.business_invoice_payments ADD CONSTRAINT FK_business_invoice_payments_import_row
    FOREIGN KEY(import_row_id) REFERENCES dbo.payment_import_rows(id);
CREATE INDEX IX_business_invoice_payments_duplicates ON dbo.business_invoice_payments(invoice_id,paid_on,amount) INCLUDE(reference,reversed_at);
GO

-- Annual jobs also invoice active business units: items get their own key and an optional unit.
ALTER TABLE dbo.annual_invoice_job_items DROP CONSTRAINT PK_annual_invoice_job_items;
GO
ALTER TABLE dbo.annual_invoice_job_items ADD
    id bigint IDENTITY(1,1) NOT NULL,
    business_unit_id bigint NULL;
GO
ALTER TABLE dbo.annual_invoice_job_items ADD
    CONSTRAINT PK_annual_invoice_job_items PRIMARY KEY(id),
    CONSTRAINT FK_annual_invoice_job_items_unit FOREIGN KEY(business_unit_id) REFERENCES dbo.business_units(id);
CREATE UNIQUE INDEX UX_annual_invoice_job_items_main ON dbo.annual_invoice_job_items(job_id,business_id) WHERE business_unit_id IS NULL;
CREATE UNIQUE INDEX UX_annual_invoice_job_items_unit ON dbo.annual_invoice_job_items(job_id,business_unit_id) WHERE business_unit_id IS NOT NULL;
ALTER TABLE dbo.annual_invoice_job_items DROP CONSTRAINT CK_annual_invoice_job_items_status;
GO
ALTER TABLE dbo.annual_invoice_job_items ADD CONSTRAINT CK_annual_invoice_job_items_status
    CHECK(status_code IN('PENDING','PROCESSING','CREATED','SKIPPED_EXISTING','SKIPPED_MISSING_TARIFF','SKIPPED_INVALID_NACE','SKIPPED_NOT_BILLABLE','FAILED'));
GO

-- Single source of truth for paid amount, balance and status of an invoice (reversed payments excluded).
CREATE VIEW dbo.v_invoice_balance AS
SELECT i.id invoice_id,i.business_id,i.business_unit_id,i.fiscal_year,i.issued_on,i.due_on,i.amount,
    CONVERT(decimal(18,2),COALESCE(p.paid_amount,0)) paid_amount,
    CONVERT(decimal(18,2),CASE WHEN i.status_code='CANCELLED' THEN 0
        WHEN i.amount-COALESCE(p.paid_amount,0)>0 THEN i.amount-COALESCE(p.paid_amount,0) ELSE 0 END) outstanding,
    CONVERT(varchar(20),CASE WHEN i.status_code='CANCELLED' THEN 'CANCELLED'
        WHEN COALESCE(p.paid_amount,0)>=i.amount THEN 'PAID'
        WHEN COALESCE(p.paid_amount,0)>0 THEN 'PARTIALLY_PAID' ELSE 'ISSUED' END) status_code,
    p.last_paid_on
FROM dbo.business_invoices i
OUTER APPLY(SELECT SUM(bp.amount) paid_amount,MAX(bp.paid_on) last_paid_on FROM dbo.business_invoice_payments bp
    WHERE bp.invoice_id=i.id AND bp.reversed_at IS NULL) p;
GO

-- Single source of truth for the business status and the tariff used for estimates and invoicing.
-- A business is ACTIVE only when ATK does not list it and it is not closed, passive or dissolved in ARBK.
CREATE VIEW dbo.v_business_billing AS
SELECT a.REGULATION_ID business_id,
    CONVERT(varchar(20),CASE
        WHEN s.status_code='NEEDS_REVIEW' THEN 'NEEDS_REVIEW'
        WHEN s.status_code='DEACTIVATED' OR a.ATK_MBYLLUR=1 OR a.Pasiv=1 OR a.DATA_SHUARJES IS NOT NULL
            OR LTRIM(RTRIM(ISNULL(a.Statusi,N'')))=N'Shuar' THEN 'DEACTIVATED'
        ELSE 'ACTIVE' END) status_code,
    CONVERT(decimal(18,2),CASE
        WHEN a.tarifa_me_lirim IS NOT NULL THEN a.tarifa_me_lirim
        WHEN TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0 THEN TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)
        ELSE TRY_CONVERT(decimal(18,2),x.tariff_snapshot) END) effective_tariff,
    CONVERT(varchar(20),CASE
        WHEN a.tarifa_me_lirim IS NOT NULL THEN 'DISCOUNT'
        WHEN TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0 THEN 'REGISTERED'
        WHEN x.tariff_snapshot IS NOT NULL THEN 'CLASSIFICATION' END) tariff_source,
    TRY_CONVERT(int,a.Viti) registration_year
FROM dbo.ARBK_LIST a
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL;
GO

-- Stored invoice status now follows the payments (it was always ISSUED before).
UPDATE i SET status_code=b.status_code
FROM dbo.business_invoices i JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id
WHERE i.status_code<>b.status_code;
GO

-- Invoice PDFs are rendered on demand (they show live balance and previous debt); cached files are no longer used.
UPDATE dbo.business_invoices SET pdf_storage_key=NULL WHERE pdf_storage_key IS NOT NULL;
GO
