<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class BusinessRepository
{
    public function __construct(private PDO $pdo) {}

    /** @param array<string,string> $filters @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function search(array $filters, int $page, int $pageSize): array
    {
        [$where,$params] = $this->where($filters);
        $candidateJoin="LEFT JOIN (SELECT NACE_CODE,COUNT(*) candidate_count FROM dbo.NACE_LIST WHERE NACE_CODE IS NOT NULL GROUP BY NACE_CODE) c ON c.NACE_CODE=a.NACE_CODE_REG";
        $count=$this->pdo->prepare("SELECT COUNT(*) FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL $candidateJoin WHERE $where");
        $count->execute($params); $total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$pageSize)); $page=max(1,min($page,$pages));
        $sql="SELECT a.REGULATION_ID business_id,a.NRBIZ registration_number,a.Emri legal_name,a.NACE_CODE_REG arbk_nace_code,COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) atk_status,x.nace_category,x.tariff_snapshot applied_tariff,COALESCE(c.candidate_count,0) candidate_count FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL $candidateJoin WHERE $where ORDER BY a.Emri,a.REGULATION_ID OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        $statement=$this->pdo->prepare($sql);
        foreach($params as $key=>$value) $statement->bindValue($key,$value);
        $statement->bindValue(':offset',($page-1)*$pageSize,PDO::PARAM_INT); $statement->bindValue(':limit',$pageSize,PDO::PARAM_INT); $statement->execute();
        return ['rows'=>$statement->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $sql="SELECT a.*,COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) normalized_atk_status,s.matched_by,s.atk_record_id,s.atk_status,s.deactivated_at,s.match_count,x.nace_list_id,x.source_nace_row_guid,x.nace_category,x.tariff_snapshot,x.assignment_method,x.mapping_rule,x.assigned_at,u.full_name assigned_by FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL LEFT JOIN dbo.app_users u ON u.id=x.assigned_by_user_id WHERE a.REGULATION_ID=:id";
        $statement=$this->pdo->prepare($sql); $statement->execute(['id'=>$id]); $row=$statement->fetch(); return is_array($row)?$row:null;
    }

    /** @param array<string,string> $filters */
    public function export(array $filters): \PDOStatement
    {
        [$where,$params]=$this->where($filters);
        $candidateJoin="LEFT JOIN (SELECT NACE_CODE,COUNT(*) candidate_count FROM dbo.NACE_LIST WHERE NACE_CODE IS NOT NULL GROUP BY NACE_CODE) c ON c.NACE_CODE=a.NACE_CODE_REG";
        $sql="SELECT a.REGULATION_ID,a.NRBIZ,a.Emri,a.EMRI_TREGTAR,a.Lloji,a.Qyteti,a.Statusi,a.Pasiv,a.date_pasivizimit,a.NACE_CODE_REG,a.NACEPERSHKRIMI,a.SEKTORI,a.NR_PUNETOREVE,a.MADHESIA,a.TOTAL_M,a.TOTAL_F,a.Viti,a.MUAJI,a.DATA_SHUARJES,a.ATK_MBYLLUR,a.ATK_DATEMBYLLJE,COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) normalized_atk_status,a.NACE_CODE_TARIFF,a.nace_veprimtaria_tariff,a.NACE_REG_TARIFF,x.nace_category,x.tariff_snapshot,x.assignment_method,u.full_name assigned_by,a.pronare_grua,a.pronesia_grua,a.pronar_veteran,a.perqindja_veteran,a.tarifa_me_lirim FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL LEFT JOIN dbo.app_users u ON u.id=x.assigned_by_user_id $candidateJoin WHERE $where ORDER BY a.Emri,a.REGULATION_ID";
        $statement=$this->pdo->prepare($sql);$statement->execute($params);return $statement;
    }

    /** @return list<array<string,mixed>> */
    public function validCategories(int $businessId): array
    {
        $statement=$this->pdo->prepare("SELECT n.REGULATIONID nace_list_id,CONVERT(varchar(36),n.NACErowGUID) mapping_key,n.NACErowGUID nace_row_guid,n.Sektori sector,n.NACE_CODE nace_code,n.Veprimtaria activity,n.Tarifa tariff FROM dbo.ARBK_LIST a JOIN dbo.NACE_LIST n ON n.NACE_CODE=a.NACE_CODE_REG WHERE a.REGULATION_ID=:id ORDER BY n.Sektori,n.Veprimtaria,n.NACErowGUID");
        $statement->execute(['id'=>$businessId]); return $statement->fetchAll();
    }

    /** @return array<string,int|float> */
    public function dashboard(): array
    {
        $sql="SELECT COUNT(*) total, SUM(CASE WHEN COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='ACTIVE' THEN 1 ELSE 0 END) active, SUM(CASE WHEN COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='DEACTIVATED' THEN 1 ELSE 0 END) deactivated, SUM(CASE WHEN x.id IS NULL THEN 1 ELSE 0 END) unclassified, SUM(CASE WHEN x.assignment_method='AUTO' THEN 1 ELSE 0 END) automatic, SUM(CASE WHEN x.assignment_method='MANUAL' THEN 1 ELSE 0 END) manual, SUM(CASE WHEN x.id IS NULL AND c.candidate_count>1 THEN 1 ELSE 0 END) ambiguous FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL LEFT JOIN (SELECT NACE_CODE,COUNT(*) candidate_count FROM dbo.NACE_LIST WHERE NACE_CODE IS NOT NULL GROUP BY NACE_CODE) c ON c.NACE_CODE=a.NACE_CODE_REG";
        return $this->pdo->query($sql)->fetch() ?: [];
    }

    /** @return array{year:int,years:list<int>,estimated_income:float,priced_businesses:int,unpriced_businesses:int,invoiced:float,to_be_invoiced:float,paid:float,to_be_paid:float,monthly:list<array{month:int,invoiced:float,paid:float}>} */
    public function financialDashboard(int $year): array
    {
        $year = max(2000, min(2100, $year));
        $sql = "WITH annual_estimate AS (
            SELECT a.REGULATION_ID,
                COALESCE(
                    TRY_CONVERT(decimal(19,2), a.tarifa_me_lirim),
                    TRY_CONVERT(decimal(19,2), a.NACE_REG_TARIFF),
                    TRY_CONVERT(decimal(19,2), x.tariff_snapshot)
                ) AS estimated_tariff
            FROM dbo.ARBK_LIST a
            LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
            LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL
            WHERE COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='ACTIVE'
                AND (a.Viti IS NULL OR TRY_CONVERT(int,a.Viti)<=:year)
        ), invoice_by_business AS (
            SELECT business_id,SUM(amount) invoiced_amount
            FROM dbo.business_invoices
            WHERE fiscal_year=:estimate_invoice_year
            GROUP BY business_id
        )
        SELECT COALESCE((SELECT SUM(estimated_tariff) FROM annual_estimate),CONVERT(decimal(38,2),0)) estimated_income,
            COALESCE(SUM(CASE WHEN e.estimated_tariff>COALESCE(i.invoiced_amount,0) THEN e.estimated_tariff-COALESCE(i.invoiced_amount,0) ELSE 0 END),CONVERT(decimal(38,2),0)) to_be_invoiced,
            COALESCE((SELECT SUM(amount) FROM dbo.business_invoices WHERE fiscal_year=:invoice_year_2),CONVERT(decimal(38,2),0)) invoiced,
            COALESCE((SELECT SUM(p.amount) FROM dbo.business_invoice_payments p WHERE YEAR(p.paid_on)=:paid_year),CONVERT(decimal(38,2),0)) paid,
            COALESCE((SELECT SUM(CASE WHEN i.amount-COALESCE(paid.amount,0)>0 THEN i.amount-COALESCE(paid.amount,0) ELSE 0 END) FROM dbo.business_invoices i OUTER APPLY(SELECT SUM(p.amount) amount FROM dbo.business_invoice_payments p WHERE p.invoice_id=i.id) paid WHERE i.fiscal_year=:invoice_year_3),CONVERT(decimal(38,2),0)) to_be_paid,
            SUM(CASE WHEN estimated_tariff IS NOT NULL THEN 1 ELSE 0 END) priced_businesses,
            SUM(CASE WHEN estimated_tariff IS NULL THEN 1 ELSE 0 END) unpriced_businesses
        FROM annual_estimate e
        LEFT JOIN invoice_by_business i ON i.business_id=e.REGULATION_ID";
        $statement = $this->pdo->prepare($sql);
        $statement->execute(['year'=>$year,'estimate_invoice_year'=>$year,'invoice_year_2'=>$year,'paid_year'=>$year,'invoice_year_3'=>$year]);
        $totals = $statement->fetch() ?: [];
        $monthlyStatement = $this->pdo->prepare("WITH month_list AS (
            SELECT month FROM (VALUES(1),(2),(3),(4),(5),(6),(7),(8),(9),(10),(11),(12)) months(month)
            ), invoice_totals AS (
                SELECT MONTH(issued_on) month,SUM(amount) amount FROM dbo.business_invoices WHERE fiscal_year=:year GROUP BY MONTH(issued_on)
            ), payment_totals AS (
                SELECT MONTH(p.paid_on) month,SUM(p.amount) amount FROM dbo.business_invoice_payments p WHERE YEAR(p.paid_on)=:paid_year GROUP BY MONTH(p.paid_on)
            )
            SELECT m.month,COALESCE(i.amount,0) invoiced,COALESCE(p.amount,0) paid
            FROM month_list m LEFT JOIN invoice_totals i ON i.month=m.month LEFT JOIN payment_totals p ON p.month=m.month ORDER BY m.month");
        $monthlyStatement->execute(['year'=>$year,'paid_year'=>$year]);
        $yearsStatement = $this->pdo->query('SELECT DISTINCT fiscal_year FROM dbo.business_invoices ORDER BY fiscal_year DESC');
        $years = array_map('intval', $yearsStatement->fetchAll(PDO::FETCH_COLUMN));
        if (!in_array($year, $years, true)) $years[] = $year;
        rsort($years);
        $estimated = (float)($totals['estimated_income']??0);
        $invoiced = (float)($totals['invoiced']??0);
        $paid = (float)($totals['paid']??0);
        return [
            'year'=>$year,
            'years'=>$years,
            'estimated_income'=>$estimated,
            'priced_businesses'=>(int)($totals['priced_businesses']??0),
            'unpriced_businesses'=>(int)($totals['unpriced_businesses']??0),
            'invoiced'=>$invoiced,
            'to_be_invoiced'=>(float)($totals['to_be_invoiced']??0),
            'paid'=>$paid,
            'to_be_paid'=>(float)($totals['to_be_paid']??0),
            'monthly'=>$monthlyStatement->fetchAll(),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function financeBusinesses(int $year, string $query = ''): array
    {
        $statement = $this->pdo->prepare("SELECT TOP (50) a.REGULATION_ID id,a.NRBIZ registration_number,a.Emri legal_name,
                COALESCE(TRY_CONVERT(decimal(18,2),a.tarifa_me_lirim),TRY_CONVERT(decimal(18,2),a.NACE_REG_TARIFF),TRY_CONVERT(decimal(18,2),x.tariff_snapshot)) estimated_tariff
            FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID
            LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL
            WHERE COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)='ACTIVE'
                AND (a.Viti IS NULL OR TRY_CONVERT(int,a.Viti)<=:year)
                AND NOT EXISTS(SELECT 1 FROM dbo.business_invoices i WHERE i.business_id=a.REGULATION_ID AND i.fiscal_year=:invoice_year)
                AND (:query='' OR a.NRBIZ LIKE :number_query OR a.Emri LIKE :name_query)
            ORDER BY a.Emri,a.REGULATION_ID");
        $statement->execute(['year'=>$year,'invoice_year'=>$year,'query'=>$query,'number_query'=>'%'.$query.'%','name_query'=>'%'.$query.'%']);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function financeInvoices(int $year): array
    {
        $statement = $this->pdo->prepare("SELECT i.id,i.business_id,i.fiscal_year,i.invoice_number,i.amount,i.issued_on,i.due_on,
                a.NRBIZ registration_number,a.Emri legal_name,COALESCE(SUM(p.amount),0) paid_amount,
                CASE WHEN i.amount-COALESCE(SUM(p.amount),0)>0 THEN i.amount-COALESCE(SUM(p.amount),0) ELSE 0 END outstanding
            FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id
            LEFT JOIN dbo.business_invoice_payments p ON p.invoice_id=i.id WHERE i.fiscal_year=:year
            GROUP BY i.id,i.business_id,i.fiscal_year,i.invoice_number,i.amount,i.issued_on,i.due_on,a.NRBIZ,a.Emri
            ORDER BY i.issued_on DESC,i.id DESC");
        $statement->execute(['year'=>$year]);
        return $statement->fetchAll();
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int,per_page:int} */
    public function financeInvoicesPage(int $year,int $page,int $perPage,string $query='',?int $jobId=null):array
    {
        $page=max(1,$page);$perPage=in_array($perPage,[20,30,40,50,100],true)?$perPage:30;$where=['i.fiscal_year=:year'];$params=['year'=>$year];
        if($query!==''){$where[]='(i.invoice_number LIKE :search_number OR i.uniref LIKE :search_uniref OR a.Emri LIKE :search_name OR a.NRBIZ LIKE :search_registration)';$value='%'.$query.'%';$params['search_number']=$value;$params['search_uniref']=$value;$params['search_name']=$value;$params['search_registration']=$value;}
        if($jobId!==null&&$jobId>0){$where[]='EXISTS(SELECT 1 FROM dbo.annual_invoice_job_items ji WHERE ji.job_id=:job AND ji.invoice_id=i.id)';$params['job']=$jobId;}
        $sqlWhere=implode(' AND ',$where);$count=$this->pdo->prepare("SELECT COUNT_BIG(*) FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id WHERE $sqlWhere");$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$offset=($page-1)*$perPage;
        $sql="SELECT i.id,i.invoice_number,i.uniref,i.amount,i.currency,i.issued_on,i.due_on,i.status_code,i.emailed_at,a.NRBIZ registration_number,a.Emri legal_name,COALESCE(p.paid_amount,0) paid_amount,CASE WHEN i.amount-COALESCE(p.paid_amount,0)>0 THEN i.amount-COALESCE(p.paid_amount,0) ELSE 0 END outstanding FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id OUTER APPLY(SELECT SUM(bp.amount) paid_amount FROM dbo.business_invoice_payments bp WHERE bp.invoice_id=i.id)p WHERE $sqlWhere ORDER BY i.issued_on DESC,i.id DESC OFFSET $offset ROWS FETCH NEXT $perPage ROWS ONLY";$q=$this->pdo->prepare($sql);$q->execute($params);return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    /** @return list<array<string,mixed>> */
    public function payableInvoices(): array
    {
        return $this->pdo->query("SELECT TOP (200) i.id,i.invoice_number,i.amount-COALESCE(SUM(p.amount),0) outstanding,a.NRBIZ registration_number,a.Emri legal_name
            FROM dbo.business_invoices i JOIN dbo.ARBK_LIST a ON a.REGULATION_ID=i.business_id
            LEFT JOIN dbo.business_invoice_payments p ON p.invoice_id=i.id
            GROUP BY i.id,i.invoice_number,i.amount,i.due_on,i.issued_on,a.NRBIZ,a.Emri
            HAVING i.amount-COALESCE(SUM(p.amount),0)>0 ORDER BY i.due_on,i.issued_on,i.id")->fetchAll();
    }

    /** @param array<string,string> $filters @return array{0:string,1:array<string,string>} */
    private function where(array $filters): array
    {
        $clauses=['1=1']; $params=[];
        if(($filters['q']??'')!==''){ $clauses[]='(a.Emri LIKE :q OR a.NRBIZ LIKE :q)'; $params[':q']='%'.trim($filters['q']).'%'; }
        if(($filters['nace']??'')!==''){ $clauses[]="LTRIM(RTRIM(a.NACE_CODE_REG))=:nace"; $params[':nace']=trim($filters['nace']); }
        if(($filters['atk_status']??'')!==''){ $clauses[]="COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)=:atk"; $params[':atk']=$filters['atk_status']; }
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
