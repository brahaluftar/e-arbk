<?php
declare(strict_types=1);
namespace App\Billing;

use App\Support\Config;
use InvalidArgumentException;

/**
 * Which businesses are invoiced, and from which tariff, in one place.
 * The SQL fragments read dbo.v_business_billing; dashboards and the annual job use the same rule.
 */
final class BillingPolicy
{
    public const SOURCES = ['DISCOUNT', 'REGISTERED', 'CLASSIFICATION'];

    /** @var list<string> */
    private array $sources;

    /** @param list<string> $sources */
    public function __construct(array $sources = ['DISCOUNT', 'REGISTERED'])
    {
        $sources = array_values(array_unique(array_map(static fn(string $s): string => strtoupper(trim($s)), $sources)));
        if ($sources === [] || array_diff($sources, self::SOURCES) !== []) throw new InvalidArgumentException('BILLING_TARIFF_SOURCES may contain only DISCOUNT, REGISTERED and CLASSIFICATION.');
        $this->sources = $sources;
    }

    public static function fromConfig(Config $config): self
    {
        return new self(array_filter(explode(',', $config->string('BILLING_TARIFF_SOURCES')), static fn(string $s): bool => trim($s) !== ''));
    }

    /** @return list<string> */
    public function sources(): array { return $this->sources; }

    /** Predicate for an alias of dbo.v_business_billing: active business with a positive tariff from an allowed source. */
    public function billableSql(string $alias = 'b'): string
    {
        $this->alias($alias);
        $list = implode(',', array_map(static fn(string $s): string => "'".$s."'", $this->sources));
        return "$alias.status_code='ACTIVE' AND $alias.effective_tariff>0 AND $alias.tariff_source IN($list)";
    }

    /** Predicate excluding businesses registered after the fiscal year; $parameter is a unique named placeholder. */
    public function registeredBySql(string $alias, string $parameter): string
    {
        $this->alias($alias);
        if (preg_match('/\A:[a-z_][a-z0-9_]*\z/', $parameter) !== 1) throw new InvalidArgumentException('Invalid parameter name.');
        return "($alias.registration_year IS NULL OR $alias.registration_year<=$parameter)";
    }

    private function alias(string $alias): void
    {
        if (preg_match('/\A[a-z][a-z0-9_]*\z/', $alias) !== 1) throw new InvalidArgumentException('Invalid SQL alias.');
    }
}
