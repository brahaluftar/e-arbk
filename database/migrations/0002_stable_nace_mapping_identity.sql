SET XACT_ABORT ON;
GO
ALTER TABLE dbo.business_nace_assignments ADD
    mapping_key char(64) NULL,
    source_sector nvarchar(510) NULL,
    source_activity nvarchar(510) NULL;
GO
UPDATE x SET
    mapping_key=CONVERT(varchar(64),HASHBYTES('SHA2_256',CONCAT(LTRIM(RTRIM(n.NACE_CODE)),N'|',LTRIM(RTRIM(n.Sektori)),N'|',LTRIM(RTRIM(n.Veprimtaria)),N'|',CONVERT(varchar(50),CONVERT(decimal(19,4),n.Tarifa)))),2),
    source_sector=LTRIM(RTRIM(n.Sektori)),source_activity=LTRIM(RTRIM(n.Veprimtaria))
FROM dbo.business_nace_assignments x
CROSS APPLY (SELECT TOP (1) source.* FROM dbo.NACE_LIST source WHERE LTRIM(RTRIM(source.NACE_CODE))=LTRIM(RTRIM(x.original_nace_code)) AND LTRIM(RTRIM(source.Sektori))+N' — '+LTRIM(RTRIM(source.Veprimtaria))=x.nace_category AND source.Tarifa=x.tariff_snapshot ORDER BY source.NACErowGUID) n;
GO
ALTER TABLE dbo.business_nace_assignments ALTER COLUMN mapping_key char(64) NOT NULL;
ALTER TABLE dbo.business_nace_assignments ALTER COLUMN source_sector nvarchar(510) NOT NULL;
ALTER TABLE dbo.business_nace_assignments ALTER COLUMN source_activity nvarchar(510) NOT NULL;
GO
CREATE INDEX IX_business_nace_mapping_key ON dbo.business_nace_assignments(mapping_key,ended_at) INCLUDE(business_id);
GO
