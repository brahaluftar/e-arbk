<?php
declare(strict_types=1);

namespace App\Repository;

use App\Billing\BillingPolicy;
use PDO;

/** Business register read models. Status and tariff come from dbo.v_business_billing (alias b). */
final class BusinessRepository
{
    private const CANDIDATES = 'LEFT JOIN (SELECT NACE_CODE,COUNT(*) candidate_count FROM dbo.NACE_LIST WHERE NACE_CODE IS NOT NULL GROUP BY NACE_CODE) c ON c.NACE_CODE=a.NACE_CODE_REG';
    private const BASE_FROM = 'FROM dbo.ARBK_LIST a JOIN dbo.v_business_billing b ON b.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL ';

    public function __construct(private PDO $pdo) {}

    /** @param array<string,string> $filters @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function search(array $filters, int $page, int $pageSize): array
    {
        [$where,$params] = $this->where($filters);
        $from = self::BASE_FROM.self::CANDIDATES." WHERE $where";
        $count=$this->pdo->prepare("SELECT COUNT(*) $from");
        $count->execute($params); $total=(int)$count->fetchColumn(); $count->closeCursor();
        $pages=max(1,(int)ceil($total/$pageSize)); $page=max(1,min($page,$pages));
        $sql="SELECT a.REGULATION_ID business_id,a.NRBIZ registration_number,a.Emri legal_name,a.EMAIL email,a.NUMRI_TELEFONIT phone_number,a.MENAXHERI manager_name,a.NACE_CODE_REG arbk_nace_code,1+(SELECT COUNT(*) FROM dbo.business_units bu WHERE bu.business_id=a.REGULATION_ID AND bu.is_active=1) unit_count,b.status_code atk_status,b.effective_tariff,x.nace_category,x.assignment_method,x.tariff_snapshot applied_tariff,COALESCE(c.candidate_count,0) candidate_count $from ORDER BY a.Emri,a.REGULATION_ID OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        $statement=$this->pdo->prepare($sql);
        foreach($params as $key=>$value) $statement->bindValue($key,$value);
        $statement->bindValue(':offset',($page-1)*$pageSize,PDO::PARAM_INT); $statement->bindValue(':limit',$pageSize,PDO::PARAM_INT); $statement->execute();
        return ['rows'=>$statement->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql="SELECT a.*,b.status_code normalized_atk_status,b.effective_tariff,b.tariff_source,s.status_code atk_sync_status,s.matched_by,s.atk_record_id,s.atk_status,s.deactivated_at,s.match_count,x.nace_list_id,x.source_nace_row_guid,x.nace_category,x.tariff_snapshot,x.assignment_method,x.mapping_rule,x.assigned_at,u.full_name assigned_by FROM dbo.ARBK_LIST a JOIN dbo.v_business_billing b ON b.business_id=a.REGULATION_ID LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL LEFT JOIN dbo.app_users u ON u.id=x.assigned_by_user_id WHERE a.REGULATION_ID=:id";
        $statement=$this->pdo->prepare($sql); $statement->execute(['id'=>$id]); $row=$statement->fetch(); return is_array($row)?$row:null;
    }

    /** @return list<array<string,mixed>> */
    public function units(int $businessId): array
    {
        $statement=$this->pdo->prepare("SELECT u.*,(SELECT TOP(1) i.id FROM dbo.business_invoices i WHERE i.business_unit_id=u.id ORDER BY i.fiscal_year DESC,i.id DESC) latest_invoice_id,(SELECT TOP(1) i.fiscal_year FROM dbo.business_invoices i WHERE i.business_unit_id=u.id ORDER BY i.fiscal_year DESC,i.id DESC) latest_invoice_year FROM dbo.business_units u WHERE u.business_id=:business ORDER BY u.is_active DESC,u.unit_name,u.id");
        $statement->execute(['business'=>$businessId]);return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function permits(int $businessId): array
    {
        $statement=$this->pdo->prepare("SELECT p.id,p.serial_number,p.issued_on,p.valid_from,p.valid_until,p.status_code,CASE WHEN p.status_code='ACTIVE' AND p.valid_until<CONVERT(date,SYSDATETIME()) THEN 'EXPIRED' ELSE p.status_code END effective_status,u.full_name issued_by FROM dbo.business_permits p LEFT JOIN dbo.app_users u ON u.id=p.issued_by_user_id WHERE p.business_id=:business ORDER BY p.issued_on DESC,p.id DESC");
        $statement->execute(['business'=>$businessId]);return $statement->fetchAll();
    }

    /** @param array<string,string> $filters */
    public function export(array $filters): \PDOStatement
    {
        [$where,$params]=$this->where($filters);
        $sql="SELECT a.REGULATION_ID,a.NRBIZ,a.Emri,a.EMRI_TREGTAR,a.Lloji,a.Qyteti,a.Statusi,a.Pasiv,a.date_pasivizimit,a.NACE_CODE_REG,a.NACEPERSHKRIMI,a.SEKTORI,a.NR_PUNETOREVE,a.MADHESIA,a.TOTAL_M,a.TOTAL_F,a.Viti,a.MUAJI,a.DATA_SHUARJES,a.ATK_MBYLLUR,a.ATK_DATEMBYLLJE,a.MENAXHERI,a.NUMRI_TELEFONIT,a.EMAIL,1+(SELECT COUNT(*) FROM dbo.business_units bu WHERE bu.business_id=a.REGULATION_ID AND bu.is_active=1) NUMRI_NJESIVE,b.status_code normalized_atk_status,b.effective_tariff,a.NACE_CODE_TARIFF,a.nace_veprimtaria_tariff,a.NACE_REG_TARIFF,x.nace_category,x.tariff_snapshot,x.assignment_method,u.full_name assigned_by,a.pronare_grua,a.pronesia_grua,a.pronar_veteran,a.perqindja_veteran,a.tarifa_me_lirim ".self::BASE_FROM."LEFT JOIN dbo.app_users u ON u.id=x.assigned_by_user_id ".self::CANDIDATES." WHERE $where ORDER BY a.Emri,a.REGULATION_ID";
        $statement=$this->pdo->prepare($sql);$statement->execute($params);return $statement;
    }

    /** @return list<array<string,mixed>> */
    public function validCategories(int $businessId): array
    {
        $statement=$this->pdo->prepare("SELECT n.REGULATIONID nace_list_id,CONVERT(varchar(36),n.NACErowGUID) mapping_key,n.NACErowGUID nace_row_guid,n.Sektori sector,n.NACE_CODE nace_code,n.Veprimtaria activity,n.Tarifa tariff FROM dbo.ARBK_LIST a JOIN dbo.NACE_LIST n ON LTRIM(RTRIM(n.NACE_CODE))=LTRIM(RTRIM(a.NACE_CODE_REG)) WHERE a.REGULATION_ID=:id ORDER BY n.Sektori,n.Veprimtaria,n.NACErowGUID");
        $statement->execute(['id'=>$businessId]); return $statement->fetchAll();
    }

    /** @return array<string,int|float> */
    public function dashboard(): array
    {
        $sql="SELECT COUNT(*) total,SUM(CASE WHEN b.status_code='ACTIVE' THEN 1 ELSE 0 END) active,SUM(CASE WHEN b.status_code='DEACTIVATED' THEN 1 ELSE 0 END) deactivated,SUM(CASE WHEN b.status_code='NEEDS_REVIEW' THEN 1 ELSE 0 END) needs_review,SUM(CASE WHEN x.id IS NULL THEN 1 ELSE 0 END) unclassified,SUM(CASE WHEN x.assignment_method='AUTO' THEN 1 ELSE 0 END) automatic,SUM(CASE WHEN x.assignment_method='MANUAL' THEN 1 ELSE 0 END) manual,SUM(CASE WHEN x.id IS NULL AND c.candidate_count>1 THEN 1 ELSE 0 END) ambiguous,SUM(CASE WHEN COALESCE(c.candidate_count,0)=0 THEN 1 ELSE 0 END) no_relation ".self::BASE_FROM.self::CANDIDATES;
        return $this->pdo->query($sql)->fetch() ?: [];
    }

    /** Estimates follow the same BillingPolicy as the annual invoice job, so "to be invoiced" matches what a job would create. */
    public function financialDashboard(int $year, BillingPolicy $policy): array
    {
        $year = max(2000, min(2100, $year));
        $sql = "WITH active_businesses AS (
                SELECT b.business_id,CASE WHEN ".$policy->billableSql('b')." THEN b.effective_tariff END estimated_tariff
                FROM dbo.v_business_billing b WHERE b.status_code='ACTIVE' AND ".$policy->registeredBySql('b',':year')."
            ), unit_estimate AS (
                SELECT u.id unit_id,u.annual_tariff estimated_tariff FROM dbo.business_units u JOIN dbo.v_business_billing ub ON ub.business_id=u.business_id
                WHERE u.is_active=1 AND u.auto_invoice=1 AND u.annual_tariff>0 AND ub.status_code='ACTIVE'
            ), main_invoiced AS (
                SELECT business_id,SUM(amount) invoiced_amount FROM dbo.v_invoice_balance WHERE fiscal_year=:main_year AND business_unit_id IS NULL AND status_code<>'CANCELLED' GROUP BY business_id
            ), unit_invoiced AS (
                SELECT business_unit_id,SUM(amount) invoiced_amount FROM dbo.v_invoice_balance WHERE fiscal_year=:unit_year AND business_unit_id IS NOT NULL AND status_code<>'CANCELLED' GROUP BY business_unit_id
            )
            SELECT COALESCE((SELECT SUM(estimated_tariff) FROM active_businesses),0)+COALESCE((SELECT SUM(estimated_tariff) FROM unit_estimate),0) estimated_income,
                COALESCE((SELECT SUM(CASE WHEN e.estimated_tariff>COALESCE(i.invoiced_amount,0) THEN e.estimated_tariff-COALESCE(i.invoiced_amount,0) ELSE 0 END) FROM active_businesses e LEFT JOIN main_invoiced i ON i.business_id=e.business_id),0)
                  +COALESCE((SELECT SUM(CASE WHEN ue.estimated_tariff>COALESCE(ui.invoiced_amount,0) THEN ue.estimated_tariff-COALESCE(ui.invoiced_amount,0) ELSE 0 END) FROM unit_estimate ue LEFT JOIN unit_invoiced ui ON ui.business_unit_id=ue.unit_id),0) to_be_invoiced,
                COALESCE((SELECT SUM(amount) FROM dbo.v_invoice_balance WHERE fiscal_year=:invoice_year AND status_code<>'CANCELLED'),0) invoiced,
                COALESCE((SELECT SUM(p.amount) FROM dbo.business_invoice_payments p WHERE p.reversed_at IS NULL AND p.paid_on>=:paid_from AND p.paid_on<:paid_to),0) paid,
                COALESCE((SELECT SUM(outstanding) FROM dbo.v_invoice_balance WHERE fiscal_year=:outstanding_year),0) to_be_paid,
                (SELECT COUNT(*) FROM active_businesses WHERE estimated_tariff IS NOT NULL) priced_businesses,
                (SELECT COUNT(*) FROM active_businesses WHERE estimated_tariff IS NULL) unpriced_businesses,
                (SELECT COUNT(*) FROM unit_estimate) priced_units";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['year'=>$year,'main_year'=>$year,'unit_year'=>$year,'invoice_year'=>$year,'paid_from'=>$year.'-01-01','paid_to'=>($year+1).'-01-01','outstanding_year'=>$year]);
        $totals = $statement->fetch() ?: [];
        $statement->closeCursor();
        $monthlyStatement = $this->pdo->prepare("WITH month_list AS (
            SELECT month FROM (VALUES(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12)) months(month)
            ), invoice_totals AS (
                SELECT MONTH(issued_on) month,SUM(amount) amount FROM dbo.v_invoice_balance WHERE fiscal_year=:year AND status_code<>'CANCELLED' GROUP BY MONTH(issued_on)
            ), payment_totals AS (
                SELECT MONTH(p.paid_on) month,SUM(p.amount) amount FROM dbo.business_invoice_payments p WHERE p.reversed_at IS NULL AND p.paid_on>=:paid_from AND p.paid_on<:paid_to GROUP BY MONTH(p.paid_on)
            )
            SELECT m.month,COALESCE(i.amount,0) invoiced,COALESCE(p.amount,0) paid
            FROM month_list m LEFT JOIN invoice_totals i ON i.month=m.month LEFT JOIN payment_totals p ON p.month=m.month ORDER BY m.month");
        $monthlyStatement->execute(['year'=>$year,'paid_from'=>$year.'-01-01','paid_to'=>($year+1).'-01-01']);
        $monthly = $monthlyStatement->fetchAll();
        $yearsStatement = $this->pdo->query('SELECT DISTINCT fiscal_year FROM dbo.business_invoices ORDER BY fiscal_year DESC');
        $years = array_map('intval', $yearsStatement->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array($year, $years, true)) $years[] = $year;
        rsort($years);
        return [
            'year'=>$year,
            'years'=>$years,
            'estimated_income'=>(float)($totals['estimated_income']??0),
            'priced_businesses'=>(int)($totals['priced_businesses']??0),
            'unpriced_businesses'=>(int)($totals['unpriced_businesses']??0),
            'priced_units'=>(int)($totals['priced_units']??0),
            'invoiced'=>(float)($totals['invoiced']??0),
            'to_be_invoiced'=>(float)($totals['to_be_invoiced']??0),
            'paid'=>(float)($totals['paid']??0),
            'to_be_paid'=>(float)($totals['to_be_paid']??0),
            'monthly'=>$monthly,
        ];
    }

    /** Active businesses without a main invoice for the year, for the manual invoice form. @return list<array<string,mixed>> */
    public function financeBusinesses(int $year, string $query = ''): array
    {
        $statement = $this->pdo->prepare("SELECT TOP (50) a.REGULATION_ID id,a.NRBIZ registration_number,a.Emri legal_name,b.effective_tariff estimated_tariff
            FROM dbo.ARBK_LIST a JOIN dbo.v_business_billing b ON b.business_id=a.REGULATION_ID
            WHERE b.status_code='ACTIVE'
                AND NOT EXISTS(SELECT 1 FROM dbo.business_invoices i WHERE i.business_id=a.REGULATION_ID AND i.business_unit_id IS NULL AND i.fiscal_year=:invoice_year)
                AND (a.NRBIZ LIKE :number_query OR a.Emri LIKE :name_query)
            ORDER BY a.Emri,a.REGULATION_ID");
        $statement->execute(['invoice_year'=>$year,'number_query'=>'%'.$query.'%','name_query'=>'%'.$query.'%']);
        return $statement->fetchAll();
    }

    /** @param array<string,string> $filters @return array{0:string,1:array<string,string>} */
    private function where(array $filters): array
    {
        $clauses=['1=1']; $params=[];
        if(($filters['q']??'')!==''){
            $clauses[]='(a.Emri LIKE :q_name OR a.NRBIZ LIKE :q_registration)';
            $query='%'.trim($filters['q']).'%';
            $params[':q_name']=$query;
            $params[':q_registration']=$query;
        }
        if(($filters['nace']??'')!==''){ $clauses[]="LTRIM(RTRIM(a.NACE_CODE_REG))=:nace"; $params[':nace']=trim($filters['nace']); }
        if(($filters['atk_status']??'')!==''){ $clauses[]="b.status_code=:atk"; $params[':atk']=$filters['atk_status']; }
        if(($filters['pronare_grua']??'')==='1')$clauses[]='a.pronare_grua=1';elseif(($filters['pronare_grua']??'')==='0')$clauses[]='ISNULL(a.pronare_grua,0)=0';
        if(($filters['pronar_veteran']??'')==='1')$clauses[]='a.pronar_veteran=1';elseif(($filters['pronar_veteran']??'')==='0')$clauses[]='ISNULL(a.pronar_veteran,0)=0';
        $classification=$filters['classification']??'';
        if($classification==='UNCLASSIFIED') $clauses[]='x.id IS NULL';
        elseif($classification==='AUTO') $clauses[]="x.assignment_method='AUTO'";
        elseif($classification==='MANUAL') $clauses[]="x.assignment_method='MANUAL'";
        elseif($classification==='AMBIGUOUS') $clauses[]='x.id IS NULL AND c.candidate_count>1';
        elseif($classification==='NO_RELATION') $clauses[]='COALESCE(c.candidate_count,0)=0';
        return [implode(' AND ',$clauses),$params];
    }
}
