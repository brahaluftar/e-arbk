SET XACT_ABORT ON;
GO
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
 COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) atk_status,
 s.deactivated_at,x.source_nace_row_guid,x.nace_category,x.tariff_snapshot applied_tariff,
 x.assignment_method,x.assigned_at
FROM dbo.ARBK_LIST a
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL;
GO
