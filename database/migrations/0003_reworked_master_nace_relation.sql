SET XACT_ABORT ON;
GO
ALTER TABLE dbo.business_nace_assignments ADD source_nace_row_guid uniqueidentifier NULL;
GO
UPDATE x SET source_nace_row_guid=n.NACErowGUID
FROM dbo.business_nace_assignments x
CROSS APPLY (
 SELECT TOP (1) source.NACErowGUID FROM dbo.NACE_LIST source
 WHERE LTRIM(RTRIM(source.NACE_CODE))=LTRIM(RTRIM(x.original_nace_code))
   AND LTRIM(RTRIM(source.Sektori))=LTRIM(RTRIM(x.source_sector))
   AND LTRIM(RTRIM(source.Veprimtaria))=LTRIM(RTRIM(x.source_activity))
   AND source.Tarifa=x.tariff_snapshot
 ORDER BY source.NACErowGUID
) n;
GO
UPDATE dbo.business_nace_assignments
SET ended_at=SYSUTCDATETIME(),end_reason=N'Superseded by exact code and activity relation rework'
WHERE ended_at IS NULL AND assignment_method='AUTO';
GO
DROP VIEW dbo.v_business_master;
GO
CREATE VIEW dbo.v_business_master AS
SELECT a.REGULATION_ID business_id,a.ARBKrowGUID business_guid,a.NRBIZ registration_number,
 CAST(NULL AS varchar(20)) fiscal_number,a.Emri legal_name,CAST(NULL AS nvarchar(255)) trade_name,
 CAST(NULL AS nvarchar(500)) business_address,a.Qyteti municipality,a.NACE_CODE_REG arbk_nace_code,
 a.NACEPERSHKRIMI arbk_nace_description,a.NACE_CODE_TARIFF tariff_nace_code,
 a.nace_veprimtaria_tariff tariff_nace_activity,a.NaceRowGuid nace_row_guid,a.Statusi arbk_status,
 CAST(NULL AS nvarchar(254)) business_email,
 COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) atk_status,
 s.deactivated_at,x.source_nace_row_guid,x.nace_category,x.tariff_snapshot applied_tariff,
 x.assignment_method,x.assigned_at
FROM dbo.ARBK_LIST a
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL;
GO
CREATE INDEX IX_business_nace_source_guid ON dbo.business_nace_assignments(source_nace_row_guid,ended_at) INCLUDE(business_id);
GO
