SET XACT_ABORT ON;
GO
ALTER TABLE dbo.annual_invoice_jobs DROP CONSTRAINT CK_annual_invoice_jobs_status;
GO
ALTER TABLE dbo.annual_invoice_jobs ALTER COLUMN status_code varchar(30) NOT NULL;
GO
ALTER TABLE dbo.annual_invoice_jobs ADD CONSTRAINT CK_annual_invoice_jobs_status
    CHECK(status_code IN('QUEUED','PROCESSING','COMPLETED','COMPLETED_WITH_ERRORS','FAILED'));
GO
