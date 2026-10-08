SET XACT_ABORT ON;
GO
UPDATE dbo.business_invoices
SET invoice_number=uniref,pdf_storage_key=NULL
WHERE uniref IS NOT NULL AND invoice_number<>uniref;

UPDATE dbo.annual_invoice_jobs
SET artifact_status='PENDING',pdf_storage_key=NULL,xlsx_storage_key=NULL,artifact_error=NULL,artifacts_generated_at=NULL
WHERE status_code IN('COMPLETED','COMPLETED_WITH_ERRORS');
GO
