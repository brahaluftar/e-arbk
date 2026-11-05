SET XACT_ABORT ON;
GO

-- A business closed or passive in ARBK (ATK_MBYLLUR, Pasiv, DATA_SHUARJES, Statusi 'Shuar') is DEACTIVATED, whatever
-- the ATK list says. Before, the 15-minute ATK synchronization wrote ACTIVE for every business missing from ATK_LIST,
-- and v_business_master only looked at ATK_MBYLLUR when that row was missing, so closed businesses showed as active.

-- Correct the ATK status rows now; AtkStatusService keeps them this way from now on.
UPDATE s SET status_code='DEACTIVATED',
    matched_by=CASE WHEN s.match_count>0 THEN s.matched_by ELSE 'ARBK' END,
    deactivated_at=COALESCE(s.deactivated_at,CONVERT(nvarchar(40),a.DATA_SHUARJES,23),CONVERT(nvarchar(40),a.date_pasivizimit,23)),
    synchronized_at=SYSUTCDATETIME()
FROM dbo.business_atk_status s JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=s.business_id
WHERE s.status_code<>'DEACTIVATED'
  AND (a.ATK_MBYLLUR=1 OR a.Pasiv=1 OR a.DATA_SHUARJES IS NOT NULL OR LTRIM(RTRIM(ISNULL(a.Statusi,N'')))=N'Shuar');
GO

ALTER VIEW dbo.v_business_billing AS
SELECT a.REGULATION_ID business_id,
    CONVERT(varchar(20),CASE
        WHEN a.ATK_MBYLLUR=1 OR a.Pasiv=1 OR a.DATA_SHUARJES IS NOT NULL
            OR LTRIM(RTRIM(ISNULL(a.Statusi,N'')))=N'Shuar' THEN 'DEACTIVATED'
        WHEN s.status_code='NEEDS_REVIEW' THEN 'NEEDS_REVIEW'
        WHEN s.status_code='DEACTIVATED' THEN 'DEACTIVATED'
        ELSE 'ACTIVE' END) status_code,
    CONVERT(decimal(18,2),CASE
        WHEN a.tarifa_me_lirim IS NOT NULL THEN a.tarifa_me_lirim
        WHEN TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0 THEN TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)
        ELSE TRY_CONVERT(decimal(18,2),x.tariff_snapshot) END) effective_tariff,
    CONVERT(varchar(20),CASE
        WHEN a.tarifa_me_lirim IS NOT NULL THEN 'DISCOUNT'
        WHEN TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF)>0 THEN 'REGISTERED'
        WHEN x.tariff_snapshot IS NOT NULL THEN 'CLASSIFICATION' END) tariff_source,
    TRY_CONVERT(int,a.Viti) registration_year
FROM dbo.ARBK_LIST a
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL;
GO

-- Same status rule for reports that read v_business_master.
ALTER VIEW dbo.v_business_master AS
SELECT a.REGULATION_ID business_id,a.ARBKrowGUID business_guid,a.NRBIZ registration_number,
 CAST(NULL AS varchar(20)) fiscal_number,a.Emri legal_name,a.EMRI_TREGTAR trade_name,
 CAST(NULL AS nvarchar(500)) business_address,a.Qyteti municipality,a.NACE_CODE_REG arbk_nace_code,
 a.NACEPERSHKRIMI arbk_nace_description,a.SEKTORI sector,a.NR_PUNETOREVE employee_count,
 a.MADHESIA business_size,a.TOTAL_M total_male,a.TOTAL_F total_female,a.Viti registration_year,
 a.MUAJI registration_month,a.DATA_SHUARJES closed_date,a.NACE_CODE_TARIFF tariff_nace_code,
 a.nace_veprimtaria_tariff tariff_nace_activity,a.NaceRowGuid nace_row_guid,a.NACE_REG_TARIFF registered_tariff,
 a.pronare_grua woman_owner,a.pronesia_grua woman_ownership_percentage,
 a.pronar_veteran veteran_owner,a.perqindja_veteran veteran_ownership_percentage,
 a.tarifa_me_lirim discounted_tariff,a.Statusi arbk_status,
 CAST(NULL AS nvarchar(254)) business_email,
 CONVERT(varchar(20),CASE
    WHEN a.ATK_MBYLLUR=1 OR a.Pasiv=1 OR a.DATA_SHUARJES IS NOT NULL
        OR LTRIM(RTRIM(ISNULL(a.Statusi,N'')))=N'Shuar' THEN 'DEACTIVATED'
    WHEN s.status_code IN('NEEDS_REVIEW','DEACTIVATED') THEN s.status_code
    ELSE 'ACTIVE' END) atk_status,
 s.deactivated_at,x.source_nace_row_guid,x.nace_category,x.tariff_snapshot applied_tariff,
 x.assignment_method,x.assigned_at
FROM dbo.ARBK_LIST a
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL;
GO
