SET XACT_ABORT ON;
GO

-- Clearing all revenue deletes every payment; report rows that had been applied become CLEARED and can be
-- applied again by rebuilding the revenue from one report.
ALTER TABLE dbo.payment_import_rows DROP CONSTRAINT CK_payment_import_rows_status;
GO
ALTER TABLE dbo.payment_import_rows ADD CONSTRAINT CK_payment_import_rows_status
    CHECK(status_code IN ('APPLIED','DUPLICATE','UNMATCHED','OVERPAYMENT','CANCELLED_INVOICE','INVALID','CLEARED'));
GO

ALTER TABLE dbo.business_invoice_events DROP CONSTRAINT CK_business_invoice_events_code;
GO
ALTER TABLE dbo.business_invoice_events ADD CONSTRAINT CK_business_invoice_events_code
    CHECK(event_code IN ('CREATED','EDITED','CANCELLED','RESTORED','PAYMENT_RECORDED','PAYMENT_REVERSED','EMAILED','PAYMENTS_CLEARED'));
GO

ALTER TABLE dbo.payment_import_batches ADD
    restored_at datetime2(3) NULL,
    restored_by_user_id bigint NULL;
GO
ALTER TABLE dbo.payment_import_batches ADD CONSTRAINT FK_payment_import_batches_restorer
    FOREIGN KEY(restored_by_user_id) REFERENCES dbo.app_users(id);
GO
