/* Read-only reconciliation of the financial dashboard and annual invoices. */
DECLARE @fiscal_year int = 2026;

WITH estimated AS (
    SELECT
        a.REGULATION_ID business_id,
        a.NRBIZ registration_number,
        a.Emri business_name,
        LTRIM(RTRIM(a.NACE_CODE_REG)) registered_nace,
        a.ATK_MBYLLUR raw_atk_closed,
        COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) normalized_status,
        COALESCE(
            TRY_CONVERT(decimal(19,2),a.tarifa_me_lirim),
            TRY_CONVERT(decimal(19,2),a.NACE_REG_TARIFF),
            TRY_CONVERT(decimal(19,2),x.tariff_snapshot)
        ) estimated_tariff,
        CASE
            WHEN TRY_CONVERT(decimal(19,2),a.tarifa_me_lirim) IS NOT NULL THEN 'DISCOUNT'
            WHEN TRY_CONVERT(decimal(19,2),a.NACE_REG_TARIFF) IS NOT NULL THEN 'ARBK_LIST'
            WHEN TRY_CONVERT(decimal(19,2),x.tariff_snapshot) IS NOT NULL THEN 'RELATION'
            ELSE 'NONE'
        END tariff_source
    FROM dbo.ARBK_LIST a
    LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
    LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL
    WHERE COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='ACTIVE'
      AND (a.Viti IS NULL OR TRY_CONVERT(int,a.Viti)<=@fiscal_year)
), invoiced AS (
    SELECT business_id,SUM(amount) invoiced_amount,COUNT(*) invoice_count
    FROM dbo.business_invoices
    WHERE fiscal_year=@fiscal_year
    GROUP BY business_id
)
SELECT e.*,COALESCE(i.invoiced_amount,0) invoiced_amount,COALESCE(i.invoice_count,0) invoice_count,
        CASE WHEN e.estimated_tariff>COALESCE(i.invoiced_amount,0)
             THEN e.estimated_tariff-COALESCE(i.invoiced_amount,0) ELSE 0 END missing_amount,
        CASE
            WHEN e.estimated_tariff IS NULL OR e.estimated_tariff<=0 THEN 'NO_POSITIVE_TARIFF'
            WHEN LEN(e.registered_nace)<>4 OR e.registered_nace LIKE '%[^0-9A-Za-z]%' THEN 'INVALID_NACE_FOR_UNIREF'
            WHEN e.raw_atk_closed=1 THEN 'NORMALIZED_ACTIVE_BUT_RAW_CLOSED'
            WHEN e.tariff_source='RELATION' THEN 'TARIFF_ONLY_FROM_RELATION'
            WHEN COALESCE(i.invoice_count,0)=0 THEN 'NO_INVOICE'
            WHEN i.invoiced_amount<e.estimated_tariff THEN 'UNDER_INVOICED'
            ELSE 'FULLY_INVOICED'
        END reconciliation_status
INTO #reconciliation
FROM estimated e
LEFT JOIN invoiced i ON i.business_id=e.business_id;

SELECT reconciliation_status,COUNT(*) businesses,
       SUM(COALESCE(estimated_tariff,0)) estimated_total,
       SUM(invoiced_amount) invoiced_total,SUM(missing_amount) missing_total
FROM #reconciliation
GROUP BY reconciliation_status
ORDER BY missing_total DESC,reconciliation_status;

SELECT business_id,registration_number,business_name,registered_nace,raw_atk_closed,
       normalized_status,tariff_source,estimated_tariff,invoiced_amount,missing_amount,
       reconciliation_status,
       j.id latest_job_id,ji.status_code latest_job_item_status,ji.error_message
FROM #reconciliation r
OUTER APPLY (
    SELECT TOP (1) j0.id
    FROM dbo.annual_invoice_jobs j0
    JOIN dbo.annual_invoice_job_items ji0 ON ji0.job_id=j0.id AND ji0.business_id=r.business_id
    WHERE j0.fiscal_year=@fiscal_year
    ORDER BY j0.id DESC
) j
LEFT JOIN dbo.annual_invoice_job_items ji ON ji.job_id=j.id AND ji.business_id=r.business_id
WHERE r.missing_amount>0
ORDER BY r.reconciliation_status,r.missing_amount DESC,r.business_id;
