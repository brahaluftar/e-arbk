/*
  DESTRUCTIVE RESET: removes every invoice and its operational details.
  Preserves businesses, users, classifications and work permits.
  Stop bin/process-annual-invoices.php before running this script.
  Physical files under var/documents/invoices and var/documents/job-exports
  are not accessible to SQL Server and must be removed separately.
*/
SET XACT_ABORT ON;
SET NOCOUNT ON;

BEGIN TRY
    BEGIN TRANSACTION;

    /* Email tracking whose logical entity is an invoice. */
    DELETE e
    FROM dbo.message_events e
    JOIN dbo.outbound_messages m ON m.id=e.message_id
    WHERE m.related_entity_type='INVOICE';

    DELETE l
    FROM dbo.message_action_links l
    JOIN dbo.outbound_messages m ON m.id=l.message_id
    WHERE m.related_entity_type='INVOICE';

    DELETE FROM dbo.outbound_messages
    WHERE related_entity_type='INVOICE';

    /* Generic audit rows do not have foreign keys and are reset selectively. */
    DELETE FROM dbo.audit_log
    WHERE entity_type IN('BUSINESS_INVOICE','INVOICE_APPEAL','ANNUAL_INVOICE_JOB')
       OR action_code IN('ANNUAL_INVOICE_CREATED','ANNUAL_INVOICE_JOB_QUEUED','INVOICE_APPEAL_SUBMITTED','INVOICE_APPEAL_STATUS_CHANGED');

    /* SQL Server blocks TRUNCATE of a referenced parent even when children are empty. */
    ALTER TABLE dbo.invoice_appeal_events DROP CONSTRAINT FK_invoice_appeal_events_appeal;
    ALTER TABLE dbo.invoice_appeals DROP CONSTRAINT FK_invoice_appeals_invoice;
    ALTER TABLE dbo.business_invoice_payments DROP CONSTRAINT FK_business_invoice_payments_invoice;
    ALTER TABLE dbo.annual_invoice_job_items DROP CONSTRAINT FK_annual_invoice_job_items_invoice;
    ALTER TABLE dbo.annual_invoice_job_items DROP CONSTRAINT FK_annual_invoice_job_items_job;

    TRUNCATE TABLE dbo.invoice_appeal_events;
    TRUNCATE TABLE dbo.invoice_appeals;
    TRUNCATE TABLE dbo.business_invoice_payments;
    TRUNCATE TABLE dbo.annual_invoice_job_items;
    TRUNCATE TABLE dbo.annual_invoice_jobs;
    TRUNCATE TABLE dbo.business_invoices;
    TRUNCATE TABLE dbo.invoice_uniref_sequences;

    ALTER TABLE dbo.invoice_appeal_events WITH CHECK
      ADD CONSTRAINT FK_invoice_appeal_events_appeal
      FOREIGN KEY(appeal_id) REFERENCES dbo.invoice_appeals(id);

    ALTER TABLE dbo.invoice_appeals WITH CHECK
      ADD CONSTRAINT FK_invoice_appeals_invoice
      FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id);

    ALTER TABLE dbo.business_invoice_payments WITH CHECK
      ADD CONSTRAINT FK_business_invoice_payments_invoice
      FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id);

    ALTER TABLE dbo.annual_invoice_job_items WITH CHECK
      ADD CONSTRAINT FK_annual_invoice_job_items_invoice
      FOREIGN KEY(invoice_id) REFERENCES dbo.business_invoices(id);

    ALTER TABLE dbo.annual_invoice_job_items WITH CHECK
      ADD CONSTRAINT FK_annual_invoice_job_items_job
      FOREIGN KEY(job_id) REFERENCES dbo.annual_invoice_jobs(id);

    COMMIT TRANSACTION;

    SELECT table_name,row_count
    FROM (VALUES
      ('business_invoices',(SELECT COUNT_BIG(*) FROM dbo.business_invoices)),
      ('business_invoice_payments',(SELECT COUNT_BIG(*) FROM dbo.business_invoice_payments)),
      ('invoice_appeals',(SELECT COUNT_BIG(*) FROM dbo.invoice_appeals)),
      ('invoice_appeal_events',(SELECT COUNT_BIG(*) FROM dbo.invoice_appeal_events)),
      ('annual_invoice_jobs',(SELECT COUNT_BIG(*) FROM dbo.annual_invoice_jobs)),
      ('annual_invoice_job_items',(SELECT COUNT_BIG(*) FROM dbo.annual_invoice_job_items)),
      ('invoice_uniref_sequences',(SELECT COUNT_BIG(*) FROM dbo.invoice_uniref_sequences))
    ) result(table_name,row_count);
END TRY
BEGIN CATCH
    IF @@TRANCOUNT>0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
