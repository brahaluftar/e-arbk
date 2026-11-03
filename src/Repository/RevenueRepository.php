<?php
declare(strict_types=1);
namespace App\Repository;

use App\Billing\BillingPolicy;
use App\Support\NaceSections;
use PDO;

/**
 * Revenue drill-down for a fiscal year: NACE section → division → code → invoices and businesses not yet invoiced,
 * and month → payments received and invoices issued. Totals match the financial dashboard.
 */
final class RevenueRepository
{
    public const METRICS = ['estimated', 'to_be_invoiced', 'invoiced', 'paid', 'outstanding', 'estimated_count', 'invoice_count'];
    /** URL value for invoices and businesses without a usable NACE code. */
    public const NO_CODE = '-';

    public function __construct(private PDO $pdo) {}

    /** Normalised NACE code ("56.10 " → "5610"), NULL when blank. */
    private static function code(string $column): string
    {
        return "NULLIF(REPLACE(REPLACE(LTRIM(RTRIM($column)),'.',''),' ',''),'')";
    }

    /**
     * Same estimate as the dashboard, per entity: active billable businesses registered by the year and active units
     * with automatic invoicing, with what is still to be invoiced against their invoices of the year.
     */
    private static function estimateSql(BillingPolicy $policy): string
    {
        return "main_invoiced AS (
                SELECT business_id,SUM(amount) amount FROM dbo.v_invoice_balance WHERE fiscal_year=:main_year AND business_unit_id IS NULL AND status_code<>'CANCELLED' GROUP BY business_id
            ), unit_invoiced AS (
                SELECT business_unit_id,SUM(amount) amount FROM dbo.v_invoice_balance WHERE fiscal_year=:unit_year AND business_unit_id IS NOT NULL AND status_code<>'CANCELLED' GROUP BY business_unit_id
            ), estimate AS (
                SELECT ".self::code('a.NACE_CODE_REG')." nace_code,a.NACEPERSHKRIMI description,b.business_id,CONVERT(bigint,NULL) unit_id,a.Emri legal_name,a.NRBIZ registration_number,CONVERT(nvarchar(255),NULL) unit_name,
                    b.effective_tariff estimated,COALESCE(m.amount,0) invoiced,CASE WHEN b.effective_tariff>COALESCE(m.amount,0) THEN b.effective_tariff-COALESCE(m.amount,0) ELSE 0 END to_be_invoiced
                FROM dbo.v_business_billing b JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=b.business_id LEFT JOIN main_invoiced m ON m.business_id=b.business_id
                WHERE ".$policy->billableSql('b').' AND '.$policy->registeredBySql('b', ':estimate_year')."
                UNION ALL
                SELECT ".self::code('u.nace_code').",u.nace_description,u.business_id,u.id,a.Emri,a.NRBIZ,u.unit_name,
                    u.annual_tariff,COALESCE(ui.amount,0),CASE WHEN u.annual_tariff>COALESCE(ui.amount,0) THEN u.annual_tariff-COALESCE(ui.amount,0) ELSE 0 END
                FROM dbo.business_units u JOIN dbo.v_business_billing ub ON ub.business_id=u.business_id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=u.business_id
                LEFT JOIN unit_invoiced ui ON ui.business_unit_id=u.id
                WHERE u.is_active=1 AND u.auto_invoice=1 AND u.annual_tariff>0 AND ub.status_code='ACTIVE'
            )";
    }

    /** @return array<string,int> */
    private static function estimateParameters(int $year): array
    {
        return ['main_year' => $year, 'unit_year' => $year, 'estimate_year' => $year];
    }

    /** One row per NACE code with the estimate and the invoices of the year. @return list<array<string,mixed>> */
    public function byNace(int $year, BillingPolicy $policy): array
    {
        $statement = $this->pdo->prepare('WITH '.self::estimateSql($policy).", estimated AS (
                SELECT nace_code,MAX(description) description,SUM(estimated) estimated,SUM(to_be_invoiced) to_be_invoiced,COUNT(*) estimated_count FROM estimate GROUP BY nace_code
            ), invoiced AS (
                SELECT ".self::code('i.registered_nace_code_snapshot')." nace_code,MAX(i.nace_description_snapshot) description,
                    SUM(b.amount) invoiced,SUM(b.paid_amount) paid,SUM(b.outstanding) outstanding,COUNT(*) invoice_count
                FROM dbo.business_invoices i JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id
                WHERE i.fiscal_year=:invoice_year AND b.status_code<>'CANCELLED'
                GROUP BY ".self::code('i.registered_nace_code_snapshot')."
            )
            SELECT COALESCE(e.nace_code,v.nace_code) nace_code,COALESCE(v.description,e.description) description,
                COALESCE(e.estimated,0) estimated,COALESCE(e.to_be_invoiced,0) to_be_invoiced,COALESCE(e.estimated_count,0) estimated_count,
                COALESCE(v.invoiced,0) invoiced,COALESCE(v.paid,0) paid,COALESCE(v.outstanding,0) outstanding,COALESCE(v.invoice_count,0) invoice_count
            FROM estimated e FULL OUTER JOIN invoiced v ON v.nace_code=e.nace_code");
        $statement->execute(self::estimateParameters($year) + ['invoice_year' => $year]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Groups byNace() rows by section (no filter), by division (within $section) or by code (within $division).
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>> each with key, label and the METRICS, largest estimate or invoice first
     */
    public static function rollUp(array $rows, ?string $section = null, ?string $division = null): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $code = (string)($row['nace_code'] ?? '');
            if ($section !== null && NaceSections::section($code) !== $section) continue;
            if ($division !== null && NaceSections::division($code) !== $division) continue;
            if ($section === null) { $key = NaceSections::section($code); $label = NaceSections::name($key); }
            elseif ($division === null) { $key = NaceSections::division($code); $label = $key === NaceSections::UNKNOWN ? 'Pa divizion' : 'Divizioni '.$key; }
            else { $key = $code === '' ? self::NO_CODE : $code; $label = trim((string)($row['description'] ?? '')) ?: ($code === '' ? 'Pa kod NACE' : 'NACE '.$code); }
            $groups[$key] ??= ['key' => $key, 'label' => $label] + array_fill_keys(self::METRICS, 0);
            foreach (self::METRICS as $metric) $groups[$key][$metric] += str_ends_with($metric, '_count') ? (int)$row[$metric] : (float)$row[$metric];
        }
        $groups = array_values($groups);
        usort($groups, static fn(array $a, array $b): int => [$b['estimated'] + $b['invoiced'], $a['key']] <=> [$a['estimated'] + $a['invoiced'], $b['key']]);
        return $groups;
    }

    /** @param list<array<string,mixed>> $rows @return array<string,float|int> */
    public static function totals(array $rows): array
    {
        $totals = array_fill_keys(self::METRICS, 0);
        foreach ($rows as $row) foreach (self::METRICS as $metric) $totals[$metric] += $row[$metric];
        return $totals;
    }

    private static function codeFilter(string $expression, string $code): string
    {
        return $code === self::NO_CODE ? "$expression IS NULL" : "$expression=:code";
    }

    /** @return array<string,mixed> */
    private static function codeParameter(string $code): array
    {
        return $code === self::NO_CODE ? [] : ['code' => $code];
    }

    /** Invoices of the year for one NACE code, largest outstanding first. @return list<array<string,mixed>> */
    public function invoices(int $year, string $code, ?int $limit = 200): array
    {
        $top = $limit === null ? '' : 'TOP ('.max(1, $limit).') ';
        $statement = $this->pdo->prepare("SELECT {$top}i.id,i.invoice_number,i.uniref,i.business_id,i.business_unit_id,i.business_name_snapshot,a.NRBIZ registration_number,u.unit_name,
                i.issued_on,i.due_on,b.amount,b.paid_amount,b.outstanding,b.status_code
            FROM dbo.business_invoices i JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id
            LEFT JOIN dbo.business_units u ON u.id=i.business_unit_id
            WHERE i.fiscal_year=:year AND b.status_code<>'CANCELLED' AND ".self::codeFilter(self::code('i.registered_nace_code_snapshot'), $code).'
            ORDER BY b.outstanding DESC,i.business_name_snapshot,i.id');
        $statement->execute(['year' => $year] + self::codeParameter($code));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Businesses and units of one NACE code whose estimate is not fully invoiced yet. @return list<array<string,mixed>> */
    public function notInvoiced(int $year, string $code, BillingPolicy $policy, ?int $limit = 200): array
    {
        $top = $limit === null ? '' : 'TOP ('.max(1, $limit).') ';
        $statement = $this->pdo->prepare('WITH '.self::estimateSql($policy)."
            SELECT {$top}business_id,unit_id,legal_name,registration_number,unit_name,estimated,invoiced,to_be_invoiced FROM estimate
            WHERE to_be_invoiced>0 AND ".self::codeFilter('nace_code', $code).' ORDER BY to_be_invoiced DESC,legal_name,business_id');
        $statement->execute(self::estimateParameters($year) + self::codeParameter($code));
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Invoices issued and payments received (payment date, reversals excluded) per month. @return list<array<string,mixed>> */
    public function months(int $year): array
    {
        $statement = $this->pdo->prepare("WITH month_list AS (
                SELECT month FROM (VALUES(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12)) months(month)
            ), invoice_totals AS (
                SELECT MONTH(issued_on) month,SUM(amount) amount,COUNT(*) invoice_count FROM dbo.v_invoice_balance WHERE fiscal_year=:year AND status_code<>'CANCELLED' GROUP BY MONTH(issued_on)
            ), payment_totals AS (
                SELECT MONTH(paid_on) month,SUM(amount) amount,COUNT(*) payment_count FROM dbo.business_invoice_payments WHERE reversed_at IS NULL AND paid_on>=:paid_from AND paid_on<:paid_to GROUP BY MONTH(paid_on)
            )
            SELECT m.month,COALESCE(i.amount,0) invoiced,COALESCE(i.invoice_count,0) invoice_count,COALESCE(p.amount,0) paid,COALESCE(p.payment_count,0) payment_count
            FROM month_list m LEFT JOIN invoice_totals i ON i.month=m.month LEFT JOIN payment_totals p ON p.month=m.month ORDER BY m.month");
        $statement->execute(['year' => $year, 'paid_from' => $year.'-01-01', 'paid_to' => ($year + 1).'-01-01']);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Payments received in one month (any invoice year), newest first. @return list<array<string,mixed>> */
    public function monthPayments(int $year, int $month, ?int $limit = 200): array
    {
        $top = $limit === null ? '' : 'TOP ('.max(1, $limit).') ';
        [$from, $to] = self::monthRange($year, $month);
        $statement = $this->pdo->prepare("SELECT {$top}p.id,p.paid_on,p.amount,p.reference,p.import_row_id,i.id invoice_id,i.invoice_number,i.fiscal_year,i.business_id,i.business_name_snapshot,a.NRBIZ registration_number
            FROM dbo.business_invoice_payments p JOIN dbo.business_invoices i ON i.id=p.invoice_id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id
            WHERE p.reversed_at IS NULL AND p.paid_on>=:paid_from AND p.paid_on<:paid_to ORDER BY p.paid_on DESC,p.id DESC");
        $statement->execute(['paid_from' => $from, 'paid_to' => $to]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Invoices of the fiscal year issued in one month (by month, as in the dashboard chart). @return list<array<string,mixed>> */
    public function monthInvoices(int $year, int $month, ?int $limit = 200): array
    {
        $top = $limit === null ? '' : 'TOP ('.max(1, $limit).') ';
        $statement = $this->pdo->prepare("SELECT {$top}i.id,i.invoice_number,i.uniref,i.business_id,i.business_name_snapshot,a.NRBIZ registration_number,u.unit_name,
                i.issued_on,b.amount,b.paid_amount,b.outstanding,b.status_code
            FROM dbo.business_invoices i JOIN dbo.v_invoice_balance b ON b.invoice_id=i.id JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id
            LEFT JOIN dbo.business_units u ON u.id=i.business_unit_id
            WHERE i.fiscal_year=:year AND b.status_code<>'CANCELLED' AND MONTH(i.issued_on)=:month
            ORDER BY i.issued_on DESC,i.id DESC");
        $statement->execute(['year' => $year, 'month' => max(1, min(12, $month))]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{string,string} */
    private static function monthRange(int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        return [sprintf('%04d-%02d-01', $year, $month), $month === 12 ? sprintf('%04d-01-01', $year + 1) : sprintf('%04d-%02d-01', $year, $month + 1)];
    }
}
