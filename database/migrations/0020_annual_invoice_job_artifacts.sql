SET XACT_ABORT ON;
GO
ALTER TABLE dbo.annual_invoice_jobs ADD
    artifact_status varchar(20) NOT NULL CONSTRAINT DF_annual_invoice_jobs_artifact_status DEFAULT 'PENDING',
    pdf_storage_key nvarchar(300) NULL,
    xlsx_storage_key nvarchar(300) NULL,
    artifact_error nvarchar(1900) NULL,
    artifacts_generated_at datetime2(3) NULL;
GO
ALTER TABLE dbo.annual_invoice_jobs ADD CONSTRAINT CK_annual_invoice_jobs_artifact_status
    CHECK(artifact_status IN('PENDING','BUILDING','READY','FAILED'));
GO
CREATE INDEX IX_annual_invoice_jobs_artifacts ON dbo.annual_invoice_jobs(artifact_status,status_code,id);
CREATE INDEX IX_business_invoice_payments_invoice ON dbo.business_invoice_payments(invoice_id) INCLUDE(amount,paid_on);
GO
