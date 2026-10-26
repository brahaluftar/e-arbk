SET XACT_ABORT ON;
GO

IF OBJECT_ID('dbo.usp_fix_uniref_check_characters','P') IS NOT NULL DROP PROCEDURE dbo.usp_fix_uniref_check_characters;
GO
IF OBJECT_ID('dbo.fn_uniref_check_character','FN') IS NOT NULL DROP FUNCTION dbo.fn_uniref_check_character;
GO

-- MOD-36 check character of a 15-character UNIREF base reference (same rule as App\Billing\UnirefGenerator):
-- trimmed and uppercased, Σ value × weight (weights 16..2), remainder mapped directly to 0-9A-Z, no complement.
-- Returns NULL when the base is not exactly 15 characters of 0-9A-Z.
CREATE FUNCTION dbo.fn_uniref_check_character(@base nvarchar(40))
RETURNS char(1)
WITH SCHEMABINDING
AS
BEGIN
    DECLARE @alphabet char(36) = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    DECLARE @input nvarchar(40) = UPPER(LTRIM(RTRIM(@base)));
    IF @input IS NULL OR DATALENGTH(@input) <> 30 RETURN NULL;
    DECLARE @position int = 1, @sum int = 0, @value int;
    WHILE @position <= 15
    BEGIN
        -- Binary comparison: accented letters and lowercase never match an alphabet character.
        SET @value = CHARINDEX(SUBSTRING(@input, @position, 1) COLLATE Latin1_General_BIN2, @alphabet COLLATE Latin1_General_BIN2) - 1;
        IF @value < 0 RETURN NULL;
        SET @sum += @value * (17 - @position);
        SET @position += 1;
    END;
    RETURN SUBSTRING(@alphabet, @sum % 36 + 1, 1);
END;
GO

-- Checks the check character of every invoice UNIREF and, with @apply = 1, corrects the wrong ones.
-- The invoice number follows the UNIREF when it was equal to it. Returns one row per invoice that is not OK:
--   WRONG (@apply = 0) / FIXED (@apply = 1), INVALID_BASE (first 15 characters are not 0-9A-Z),
--   CONFLICT (the corrected UNIREF is already used by another invoice; left unchanged).
CREATE PROCEDURE dbo.usp_fix_uniref_check_characters
    @apply bit = 0
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;
    DECLARE @checked TABLE(
        invoice_id bigint NOT NULL PRIMARY KEY,
        invoice_number nvarchar(80) NOT NULL,
        current_uniref nvarchar(40) NOT NULL,
        correct_uniref nvarchar(40) NULL,
        result varchar(20) NOT NULL);

    INSERT @checked(invoice_id, invoice_number, current_uniref, correct_uniref, result)
    SELECT i.id, i.invoice_number, i.uniref, c.correct_uniref,
        CASE WHEN c.correct_uniref IS NULL THEN 'INVALID_BASE'
             WHEN c.correct_uniref COLLATE Latin1_General_BIN2 = i.uniref COLLATE Latin1_General_BIN2 THEN 'OK'
             ELSE 'WRONG' END
    FROM dbo.business_invoices i
    CROSS APPLY (SELECT UPPER(LEFT(LTRIM(RTRIM(i.uniref)), 15)) base) b
    CROSS APPLY (SELECT b.base + dbo.fn_uniref_check_character(b.base) correct_uniref) c
    WHERE i.uniref IS NOT NULL;

    UPDATE c SET result = 'CONFLICT'
    FROM @checked c
    WHERE c.result = 'WRONG'
      AND (EXISTS(SELECT 1 FROM dbo.business_invoices o WHERE o.id <> c.invoice_id AND (o.uniref = c.correct_uniref OR o.invoice_number = c.correct_uniref))
        OR EXISTS(SELECT 1 FROM @checked d WHERE d.invoice_id <> c.invoice_id AND d.result = 'WRONG' AND d.correct_uniref = c.correct_uniref));

    IF @apply = 1 AND EXISTS(SELECT 1 FROM @checked WHERE result = 'WRONG')
    BEGIN
        BEGIN TRANSACTION;
        UPDATE i SET
            invoice_number = CASE WHEN i.invoice_number = i.uniref THEN c.correct_uniref ELSE i.invoice_number END,
            uniref = c.correct_uniref,
            pdf_storage_key = NULL
        FROM dbo.business_invoices i
        JOIN @checked c ON c.invoice_id = i.id
        WHERE c.result = 'WRONG';
        UPDATE @checked SET result = 'FIXED' WHERE result = 'WRONG';
        COMMIT TRANSACTION;
    END;

    SELECT invoice_id, invoice_number, current_uniref, correct_uniref, result,
        (SELECT COUNT(*) FROM @checked) checked_count
    FROM @checked
    WHERE result <> 'OK'
    UNION ALL
    -- Summary row (invoice_id NULL) so the caller always gets the number of UNIREFs checked.
    SELECT NULL, NULL, NULL, NULL, 'SUMMARY', (SELECT COUNT(*) FROM @checked)
    ORDER BY invoice_id;
END;
GO
