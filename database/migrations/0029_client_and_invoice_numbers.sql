SET XACT_ABORT ON;
GO

-- Client number: unique per business, printed on invoices, editable by staff. New businesses get the next number.
ALTER TABLE dbo.ARBK_LIST ADD client_number varchar(20) NULL;
GO
WITH numbered AS (SELECT client_number,ROW_NUMBER() OVER(ORDER BY REGULATION_ID) rn FROM dbo.ARBK_LIST)
UPDATE numbered SET client_number=CONVERT(varchar(20),100000+rn) WHERE client_number IS NULL;
GO
DECLARE @nextClient bigint=(SELECT ISNULL(MAX(TRY_CONVERT(bigint,client_number)),100000)+1 FROM dbo.ARBK_LIST);
IF OBJECT_ID('dbo.arbk_client_number_seq','SO') IS NULL BEGIN
    DECLARE @sequenceSql nvarchar(300)=N'CREATE SEQUENCE dbo.arbk_client_number_seq AS bigint START WITH '+CONVERT(nvarchar(30),@nextClient)+N' INCREMENT BY 1;';
    EXEC sys.sp_executesql @sequenceSql;
END;
GO
ALTER TABLE dbo.ARBK_LIST ADD CONSTRAINT DF_ARBK_LIST_client_number DEFAULT(CONVERT(varchar(20),NEXT VALUE FOR dbo.arbk_client_number_seq)) FOR client_number;
CREATE UNIQUE INDEX UX_ARBK_LIST_client_number ON dbo.ARBK_LIST(client_number) WHERE client_number IS NOT NULL;
GO

-- Invoice numbers are separate from the UNIREF: <fiscal year>-<6-digit sequence>, e.g. 2026-000001.
CREATE TABLE dbo.invoice_number_sequences (
    fiscal_year smallint NOT NULL CONSTRAINT PK_invoice_number_sequences PRIMARY KEY,
    last_value int NOT NULL,
    updated_at datetime2(3) NOT NULL CONSTRAINT DF_invoice_number_sequences_updated DEFAULT SYSUTCDATETIME(),
    CONSTRAINT CK_invoice_number_sequences_value CHECK(last_value BETWEEN 0 AND 999999)
);
GO

-- Existing invoices numbered by the system (number = UNIREF, or the old FAT- prefix) get the new numbers,
-- per fiscal year in order of issue. Numbers entered by hand are kept. Every change is written to the invoice history.
DECLARE @renumbered TABLE(invoice_id bigint NOT NULL PRIMARY KEY,old_number nvarchar(80) NOT NULL,new_number nvarchar(80) NOT NULL);
INSERT @renumbered(invoice_id,old_number,new_number)
SELECT id,invoice_number,CONCAT(fiscal_year,'-',RIGHT('000000'+CONVERT(varchar(6),ROW_NUMBER() OVER(PARTITION BY fiscal_year ORDER BY issued_on,id)),6))
FROM dbo.business_invoices
WHERE invoice_number=uniref OR invoice_number LIKE 'FAT-%';
IF EXISTS(SELECT 1 FROM @renumbered r JOIN dbo.business_invoices i ON i.invoice_number=r.new_number AND i.id NOT IN(SELECT invoice_id FROM @renumbered))
    THROW 50029,'A hand-entered invoice number already uses the new format; rename it before migrating.',1;
UPDATE i SET invoice_number=r.new_number FROM dbo.business_invoices i JOIN @renumbered r ON r.invoice_id=i.id;
INSERT dbo.business_invoice_events(invoice_id,event_code,changes_json,comment,actor_user_id)
SELECT invoice_id,'EDITED',CONCAT('{"invoice_number":{"from":"',STRING_ESCAPE(old_number,'json'),'","to":"',new_number,'"}}'),
    N'Numri i faturës u nda nga UNIREF-i (formati i ri VVVV-NNNNNN).',NULL
FROM @renumbered;
INSERT dbo.invoice_number_sequences(fiscal_year,last_value)
SELECT fiscal_year,COUNT(*) FROM dbo.business_invoices i JOIN @renumbered r ON r.invoice_id=i.id GROUP BY fiscal_year;
GO

-- Annual job exports (bulk PDF/XLSX) are rebuilt with the new invoice design by the export worker.
UPDATE dbo.annual_invoice_jobs SET artifact_status='PENDING',artifact_error=NULL WHERE artifact_status IN('READY','FAILED');
GO
