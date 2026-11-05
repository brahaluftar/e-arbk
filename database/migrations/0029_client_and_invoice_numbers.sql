SET XACT_ABORT ON;
GO

-- Client number: unique per business, printed on invoices. New businesses get the next number automatically.
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

-- The invoice number stays the UNIREF (only its label on the invoice changed); see migration 0031.

-- Annual job exports (bulk PDF/XLSX) are rebuilt with the new invoice design by the export worker.
UPDATE dbo.annual_invoice_jobs SET artifact_status='PENDING',artifact_error=NULL WHERE artifact_status IN('READY','FAILED');
GO
