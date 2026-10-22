<?php
declare(strict_types=1);
use App\Billing\BillingPolicy;
use PHPUnit\Framework\TestCase;

final class BillingPolicyTest extends TestCase
{
    public function testBuildsOneBillableRuleFromTheConfiguredSources(): void
    {
        $policy=new BillingPolicy(['registered','DISCOUNT','CLASSIFICATION']);
        self::assertSame(['REGISTERED','DISCOUNT','CLASSIFICATION'],$policy->sources());
        self::assertSame("b.status_code='ACTIVE' AND b.effective_tariff>0 AND b.tariff_source IN('REGISTERED','DISCOUNT','CLASSIFICATION')",$policy->billableSql('b'));
        self::assertSame('(x.registration_year IS NULL OR x.registration_year<=:year)',$policy->registeredBySql('x',':year'));
    }

    public function testRejectsUnknownSourcesAndUnsafeSql(): void
    {
        foreach([fn()=>new BillingPolicy(['REGISTERED','ANYTHING']),fn()=>new BillingPolicy([]),fn()=>(new BillingPolicy())->billableSql('b; DROP'),fn()=>(new BillingPolicy())->registeredBySql('b','1 OR 1=1')] as $case){
            try{$case();self::fail('Accepted invalid input');}catch(InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
