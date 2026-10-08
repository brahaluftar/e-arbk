SET NOCOUNT ON;
SET XACT_ABORT ON;

IF OBJECT_ID('tempdb..#income_estimate') IS NOT NULL
    DROP TABLE #income_estimate;

;WITH nace_tariffs AS (
    SELECT
        LTRIM(RTRIM(n.NACE_CODE)) AS nace_code,
        COUNT_BIG(*) AS candidate_count,
        MIN(TRY_CONVERT(decimal(19, 2), n.Tarifa)) AS lowest_tariff
    FROM dbo.NACE_LIST AS n
    WHERE NULLIF(LTRIM(RTRIM(n.NACE_CODE)), '') IS NOT NULL
    GROUP BY LTRIM(RTRIM(n.NACE_CODE))
)
SELECT
    a.REGULATION_ID AS business_id,
    a.NRBIZ AS registration_number,
    a.Emri AS business_name,
    LTRIM(RTRIM(a.NACE_CODE_REG)) AS registered_nace_code,
    COALESCE(t.candidate_count, 0) AS candidate_count,
    t.lowest_tariff AS estimated_tariff,
    CASE
        WHEN t.candidate_count IS NULL THEN 'NO_RELATION'
        WHEN t.candidate_count = 1 THEN 'UNIQUE'
        ELSE 'AMBIGUOUS_LOWEST_TARIFF'
    END AS relation_status
INTO #income_estimate
FROM dbo.ARBK_LIST AS a
LEFT JOIN dbo.business_atk_status AS s
    ON s.business_id = a.REGULATION_ID
LEFT JOIN nace_tariffs AS t
    ON t.nace_code = LTRIM(RTRIM(a.NACE_CODE_REG))
WHERE COALESCE(
    s.status_code,
    CASE WHEN a.ATK_MBYLLUR = 1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END
) = 'ACTIVE';

-- Overall estimated income. Businesses without a NACE_LIST relation are excluded
-- from the monetary total and reported separately.
SELECT
    COUNT_BIG(*) AS active_businesses,
    SUM(CASE WHEN relation_status = 'UNIQUE' THEN 1 ELSE 0 END) AS unique_relations,
    SUM(CASE WHEN relation_status = 'AMBIGUOUS_LOWEST_TARIFF' THEN 1 ELSE 0 END) AS ambiguous_relations,
    SUM(CASE WHEN relation_status = 'NO_RELATION' THEN 1 ELSE 0 END) AS businesses_without_relation,
    CAST(COALESCE(SUM(estimated_tariff), 0) AS decimal(19, 2)) AS estimated_income_lowest_tariff
FROM #income_estimate;

-- Contribution by relation type.
SELECT
    relation_status,
    COUNT_BIG(*) AS business_count,
    CAST(COALESCE(SUM(estimated_tariff), 0) AS decimal(19, 2)) AS estimated_income
FROM #income_estimate
GROUP BY relation_status
ORDER BY relation_status;

DROP TABLE #income_estimate;
