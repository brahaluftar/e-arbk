SET XACT_ABORT ON;
GO
CREATE TABLE dbo.annual_invoice_jobs (
    id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_annual_invoice_jobs PRIMARY KEY,
    period_start date NOT NULL,
    period_end date NOT NULL,
    fiscal_year smallint NOT NULL,
    due_days smallint NOT NULL,
    status_code varchar(20) NOT NULL CONSTRAINT DF_annual_invoice_jobs_status DEFAULT 'QUEUED',
    total_items int NOT NULL CONSTRAINT DF_annual_invoice_jobs_total DEFAULT 0,
    processed_items int NOT NULL CONSTRAINT DF_annual_invoice_jobs_processed DEFAULT 0,
    created_items int NOT NULL CONSTRAINT DF_annual_invoice_jobs_created DEFAULT 0,
    skipped_items int NOT NULL CONSTRAINT DF_annual_invoice_jobs_skipped DEFAULT 0,
    failed_items int NOT NULL CONSTRAINT DF_annual_invoice_jobs_failed DEFAULT 0,
    total_amount decimal(18,2) NOT NULL CONSTRAINT DF_annual_invoice_jobs_amount DEFAULT 0,
    requested_by_user_id bigint NOT NULL,
    created_at datetime2(3) NOT NULL CONSTRAINT DF_annual_invoice_jobs_created_at DEFAULT SYSUTCDATETIME(),
    started_at datetime2(3) NULL,
    heartbeat_at datetime2(3) NULL,
    completed_at datetime2(3) NULL,
    error_message nvarchar(1900) NULL,
    CONSTRAINT FK_annual_invoice_jobs_user FOREIGN KEY(requested_by_user_id) REFERENCES dbo.app_users(id),
    CONSTRAINT CK_annual_invoice_jobs_status CHECK(status_code IN('QUEUED','PROCESSING','COMPLETED','COMPLETED_WITH_ERRORS','FAILED')),
    CONSTRAINT CK_annual_invoice_jobs_period CHECK(period_end>=period_start AND fiscal_year=YEAR(period_start)),
    CONSTRAINT CK_annual_invoice_jobs_counts CHECK(total_items>=0 AND processed_items>=0 AND created_items>=0 AND skipped_items>=0 AND failed_items>=0)
);
CREATE INDEX IX_annual_invoice_jobs_queue ON dbo.annual_invoice_jobs(status_code,created_at,id);
GO
CREATE TABLE dbo.annual_invoice_job_items (
    job_id bigint NOT NULL,
    business_id int NOT NULL,
    status_code varchar(30) NOT NULL CONSTRAINT DF_annual_invoice_job_items_status DEFAULT 'PENDING',
    invoice_id bigint NULL,
    amount decimal(18,2) NULL,
    error_message nvarchar(1000) NULL,
    updated_at datetime2(3) NOT NULL CONSTRAINT DF_annual_invoice_job_items_updated DEFAULT SYSUTCDATETIME(),
    CONSTRAINT PK_annual_invoice_job_items PRIMARY KEY(job_id,business_id),
    CONSTRAINT FK_annual_invoice_job_items_job FOREIGN KEY(job_id) REFERENCES dbo.annual_invoice_jobs(id),
    CONSTRAINT FK_annual_invoice_job_items_business FOREIGN KEY(business_id) REFERENCES dbo.ARBK_LIST(REGULATION_ID),
    CONSTRAINT FK_annual_invoice_job_items_invoice FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id),
    CONSTRAINT CK_annual_invoice_job_items_status CHECK(status_code IN('PENDING','PROCESSING','CREATED','SKIPPED_EXISTING','SKIPPED_MISSING_TARIFF','SKIPPED_INVALID_NACE','FAILED'))
);
CREATE INDEX IX_annual_invoice_job_items_work ON dbo.annual_invoice_job_items(job_id,status_code,business_id);
GO
