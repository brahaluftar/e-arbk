-- Read-only diagnostics. Change @job_id as needed.
DECLARE @job_id bigint=4;

-- 1. Job totals and actual item states.
SELECT j.* FROM dbo.annual_invoice_jobs j WHERE j.id=@job_id;
SELECT status_code,COUNT_BIG(*) item_count,COUNT(invoice_id) invoice_count,
       SUM(COALESCE(amount,0)) amount
FROM dbo.annual_invoice_job_items WHERE job_id=@job_id GROUP BY status_code ORDER BY status_code;

-- 2. Repeated errors (07002 means PDO parameter count, not SQL COUNT()).
SELECT TOP(20) error_message,COUNT_BIG(*) occurrences
FROM dbo.annual_invoice_job_items WHERE job_id=@job_id AND error_message IS NOT NULL
GROUP BY error_message ORDER BY occurrences DESC;

-- 3. Businesses eligible for invoicing: active relation AUTO/MANUAL and positive tariff.
SELECT x.assignment_method,COUNT_BIG(*) eligible_businesses,
       SUM(COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),x.tariff_snapshot))) estimated_total
FROM dbo.ARBK_LIST a
JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL AND x.assignment_method IN('AUTO','MANUAL')
LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
WHERE COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='ACTIVE'
  AND COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),x.tariff_snapshot))>0
GROUP BY x.assignment_method;

-- 4. Data-quality categories excluded before job creation (read-only counts).
SELECT exclusion_reason,COUNT_BIG(*) businesses
FROM (
 SELECT a.REGULATION_ID,
  CASE WHEN x.id IS NULL THEN 'NO_ACTIVE_AUTO_MANUAL_RELATION'
       WHEN COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),x.tariff_snapshot)) IS NULL THEN 'TARIFF_NULL_OR_NOT_NUMERIC'
       WHEN COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),x.tariff_snapshot))<=0 THEN 'TARIFF_ZERO_OR_NEGATIVE'
       WHEN LEN(LTRIM(RTRIM(a.NACE_CODE_REG)))<>4 THEN 'REGISTERED_NACE_NOT_4_CHARS'
       ELSE 'ELIGIBLE' END exclusion_reason
 FROM dbo.ARBK_LIST a
 LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL AND x.assignment_method IN('AUTO','MANUAL')
) d GROUP BY exclusion_reason ORDER BY businesses DESC;

-- 5. Detailed preview of eligible invoice fields, without writing anything.
SELECT TOP(200) a.REGULATION_ID business_id,a.NRBIZ,a.Emri,
       x.assignment_method,LTRIM(RTRIM(a.NACE_CODE_REG)) registered_nace,
       COALESCE(LTRIM(RTRIM(n.NACE_CODE)),LTRIM(RTRIM(a.NACE_CODE_TARIFF)),LTRIM(RTRIM(x.original_nace_code))) tariff_nace,
       x.nace_category,
       TRY_CONVERT(decimal(18,2),x.tariff_snapshot) relation_tariff,
       TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim) discounted_tariff,
       COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),x.tariff_snapshot)) invoice_amount
FROM dbo.ARBK_LIST a
JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL AND x.assignment_method IN('AUTO','MANUAL')
LEFT JOIN dbo.NACE_LIST n ON n.NACErowGUID=x.source_nace_row_guid
WHERE COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),x.tariff_snapshot))>0
ORDER BY a.REGULATION_ID;
