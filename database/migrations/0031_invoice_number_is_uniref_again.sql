SET XACT_ABORT ON;
GO

-- Decision: the invoice number stays the UNIREF; only its label on the invoice is "Nr. i faturës".
-- Where the first version of migration 0029 already gave invoices separate numbers (YYYY-NNNNNN), the UNIREF is
-- restored as the number and the change is written to the invoice history. Nothing happens on databases where it did not.
IF OBJECT_ID('dbo.invoice_number_sequences','U') IS NOT NULL
BEGIN
    DECLARE @applied datetime2(3)=(SELECT applied_at FROM dbo.schema_migrations WHERE migration='0029_client_and_invoice_numbers.sql');
    DECLARE @restored TABLE(invoice_id bigint NOT NULL PRIMARY KEY,old_number nvarchar(80) NOT NULL,new_number nvarchar(80) NOT NULL);
    INSERT @restored(invoice_id,old_number,new_number)
    SELECT i.id,i.invoice_number,i.uniref
    FROM dbo.business_invoices i
    WHERE i.uniref IS NOT NULL AND i.invoice_number<>i.uniref
      AND i.invoice_number LIKE '[0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9]'
      AND (EXISTS(SELECT 1 FROM dbo.business_invoice_events e WHERE e.invoice_id=i.id AND e.event_code='EDITED'
                  AND JSON_VALUE(e.changes_json,'$.invoice_number.to')=i.invoice_number)
           OR i.created_at>=@applied);
    UPDATE i SET invoice_number=r.new_number FROM dbo.business_invoices i JOIN @restored r ON r.invoice_id=i.id;
    INSERT dbo.business_invoice_events(invoice_id,event_code,changes_json,comment,actor_user_id)
    SELECT invoice_id,'EDITED',CONCAT('{"invoice_number":{"from":"',old_number,'","to":"',STRING_ESCAPE(new_number,'json'),'"}}'),
        N'Numri i faturës u rikthye në UNIREF.',NULL
    FROM @restored;
END;
GO
IF OBJECT_ID('dbo.invoice_number_sequences','U') IS NOT NULL DROP TABLE dbo.invoice_number_sequences;
GO
