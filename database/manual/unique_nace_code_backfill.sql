/*
  Manual-review backfill for NACE codes occurring exactly once in NACE_LIST.

  Safety:
  - requires NACE_CODE_TARIFF to be populated;
  - never overwrites an existing target value;
  - defaults to ROLLBACK so the preview can be reviewed first;
  - change the final ROLLBACK to COMMIT only after approval.
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

IF OBJECT_ID('tempdb..#unique_nace') IS NOT NULL DROP TABLE #unique_nace;

SELECT
    n.NACE_CODE,
    n.NACErowGUID,
    n.Veprimtaria,
    n.Tarifa
INTO #unique_nace
FROM dbo.NACE_LIST n
INNER JOIN (
    SELECT NACE_CODE
    FROM dbo.NACE_LIST
    WHERE NACE_CODE IS NOT NULL
    GROUP BY NACE_CODE
    HAVING COUNT(*) = 1
) unique_code ON unique_code.NACE_CODE = n.NACE_CODE;

-- Preview: review every row returned here before approving COMMIT.
SELECT
    a.REGULATION_ID,
    a.NRBIZ,
    a.Emri,
    a.NACE_CODE_REG,
    old_activity = a.nace_veprimtaria_tariff,
    new_activity = n.Veprimtaria,
    old_nace_guid = a.NaceRowGuid,
    new_nace_guid = n.NACErowGUID,
    old_tariff = a.NACE_REG_TARIFF,
    new_tariff = n.Tarifa
FROM dbo.ARBK_LIST a
INNER JOIN #unique_nace n ON n.NACE_CODE = a.NACE_CODE_REG
WHERE a.NACE_CODE_TARIFF IS NOT NULL
  AND (
      a.nace_veprimtaria_tariff IS NULL
      OR a.NaceRowGuid IS NULL
      OR a.NACE_REG_TARIFF IS NULL
  )
ORDER BY a.NACE_CODE_REG, a.Emri, a.REGULATION_ID;

UPDATE a
SET
    a.nace_veprimtaria_tariff = COALESCE(a.nace_veprimtaria_tariff, n.Veprimtaria),
    a.NaceRowGuid = COALESCE(a.NaceRowGuid, n.NACErowGUID),
    a.NACE_REG_TARIFF = COALESCE(a.NACE_REG_TARIFF, n.Tarifa)
FROM dbo.ARBK_LIST a
INNER JOIN #unique_nace n ON n.NACE_CODE = a.NACE_CODE_REG
WHERE a.NACE_CODE_TARIFF IS NOT NULL
  AND (
      a.nace_veprimtaria_tariff IS NULL
      OR a.NaceRowGuid IS NULL
      OR a.NACE_REG_TARIFF IS NULL
  );

SELECT @@ROWCOUNT AS rows_that_would_change;

-- Manual-review default. Replace with COMMIT after approving the preview.
ROLLBACK TRANSACTION;
