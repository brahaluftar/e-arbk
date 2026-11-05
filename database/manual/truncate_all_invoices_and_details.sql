/*
  DESTRUCTIVE RESET: removes every invoice and everything that depends on it, so invoicing can start again:
  invoices, invoice history, payments (and storno), Finance Directorate payment imports, appeals,
  annual invoice jobs, invoice emails, invoice audit rows and the UNIREF sequences (UNIREFs start at 000001 again).
  Preserves businesses, units, users, classifications, business imports and work permits.

  Easiest from the server (also removes the old PDF/XLSX export files):
      php bin/reset-invoices.php --confirm
  Stop the annual-invoice cron job (bin/process-annual-invoices.php) before running it.
*/
SET XACT_ABORT ON;
SET NOCOUNT ON;

BEGIN TRY
    BEGIN TRANSACTION;

    /* Invoice emails and their tracking links. */
    DELETE e FROM dbo.message_events e JOIN dbo.outbound_messages m ON m.id=e.message_id WHERE m.related_entity_type='INVOICE';
    DELETE l FROM dbo.message_action_links l JOIN dbo.outbound_messages m ON m.id=l.message_id WHERE m.related_entity_type='INVOICE';
    DELETE FROM dbo.outbound_messages WHERE related_entity_type='INVOICE';

    /* Audit rows have no foreign keys; only the invoicing ones are removed. */
    DELETE FROM dbo.audit_log
    WHERE entity_type IN('BUSINESS_INVOICE','INVOICE_PAYMENT','INVOICE_APPEAL','ANNUAL_INVOICE_JOB','PAYMENT_IMPORT_BATCH');

    DELETE FROM dbo.invoice_appeal_events;
    DELETE FROM dbo.invoice_appeals;

    /* Payments and import rows point at each other. */
    UPDATE dbo.business_invoice_payments SET import_row_id=NULL WHERE import_row_id IS NOT NULL;
    DELETE FROM dbo.payment_import_rows;
    DELETE FROM dbo.payment_import_batches;
    DELETE FROM dbo.business_invoice_payments;

    DELETE FROM dbo.business_invoice_events;
    DELETE FROM dbo.annual_invoice_job_items;
    DELETE FROM dbo.annual_invoice_jobs;
    DELETE FROM dbo.business_invoices;
    DELETE FROM dbo.invoice_uniref_sequences;

    /* New rows start again at id 1. A table that never had a row is skipped: reseeding it to 0 would make 0 its first id. */
    DECLARE @reseed nvarchar(max)=N'';
    SELECT @reseed+=N'DBCC CHECKIDENT(''dbo.'+t.name+N''', RESEED, 0) WITH NO_INFOMSGS;'
    FROM sys.identity_columns c JOIN sys.tables t ON t.object_id=c.object_id
    WHERE t.schema_id=SCHEMA_ID('dbo') AND c.last_value IS NOT NULL
      AND t.name IN('invoice_appeal_events','invoice_appeals','payment_import_rows','payment_import_batches','business_invoice_payments',
                    'business_invoice_events','annual_invoice_job_items','annual_invoice_jobs','business_invoices');
    EXEC sys.sp_executesql @reseed;

    COMMIT TRANSACTION;

    SELECT table_name,row_count
    FROM (VALUES
      ('business_invoices',(SELECT COUNT_BIG(*) FROM dbo.business_invoices)),
      ('business_invoice_payments',(SELECT COUNT_BIG(*) FROM dbo.business_invoice_payments)),
      ('business_invoice_events',(SELECT COUNT_BIG(*) FROM dbo.business_invoice_events)),
      ('payment_import_batches',(SELECT COUNT_BIG(*) FROM dbo.payment_import_batches)),
      ('payment_import_rows',(SELECT COUNT_BIG(*) FROM dbo.payment_import_rows)),
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
