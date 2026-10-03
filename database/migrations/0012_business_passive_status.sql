IF COL_LENGTH('dbo.ARBK_LIST', 'Pasiv') IS NULL
    ALTER TABLE dbo.ARBK_LIST ADD Pasiv bit NULL;
GO
IF COL_LENGTH('dbo.ARBK_LIST', 'date_pasivizimit') IS NULL
    ALTER TABLE dbo.ARBK_LIST ADD date_pasivizimit date NULL;
GO
IF COL_LENGTH('dbo.business_import_staging', 'passive') IS NULL
    ALTER TABLE dbo.business_import_staging ADD passive bit NULL;
GO
IF COL_LENGTH('dbo.business_import_staging', 'passive_date') IS NULL
    ALTER TABLE dbo.business_import_staging ADD passive_date date NULL;
GO

UPDATE dbo.ARBK_LIST
SET Pasiv=1,
    date_pasivizimit=COALESCE(date_pasivizimit,TRY_CONVERT(date,LTRIM(RTRIM(SUBSTRING(Statusi,CHARINDEX('-',Statusi)+1,40))),103)),
    ATK_MBYLLUR=1,
    Statusi=N'Pasiv'
WHERE LTRIM(RTRIM(Statusi)) LIKE N'Pasiv-%';
GO
