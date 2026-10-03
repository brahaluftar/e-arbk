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
        $sql="SELECT a.REGULATION_ID,a.NRBIZ,a.Emri,a.EMRI_TREGTAR,a.Lloji,a.Qyteti,a.Statusi,a.Pasiv,a.date_pasivizimit,a.NACE_CODE_REG,a.NACEPERSHKRIMI,a.SEKTORI,a.NR_PUNETOREVE,a.MADHESIA,a.TOTAL_M,a.TOTAL_F,a.Viti,a.MUAJI,a.DATA_SHUARJES,a.ATK_MBYLLUR,a.ATK_DATEMBYLLJE,COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END) normalized_atk_status,a.NACE_CODE_TARIFF,a.nace_veprimtaria_tariff,a.NACE_REG_TARIFF,x.nace_category,x.tariff_snapshot,x.assignment_method,a.pronare_grua,a.pronesia_grua,a.pronar_veteran,a.perqindja_veteran,a.tarifa_me_lirim FROM dbo.ARBK_LIST a LEFT JOIN dbo.business_atk_status s ON s.business_id=a.REGULATION_ID LEFT JOIN dbo.business_nace_assignments x ON x.business_id=a.REGULATION_ID AND x.ended_at IS NULL $candidateJoin WHERE $where ORDER BY a.Emri,a.REGULATION_ID";
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

    /** @param array<string,string> $filters @return array{0:string,1:array<string,string>} */
    private function where(array $filters): array
    {
        $clauses=['1=1']; $params=[];
        if(($filters['q']??'')!==''){ $clauses[]='(a.Emri LIKE :q OR a.NRBIZ LIKE :q)'; $params[':q']='%'.trim($filters['q']).'%'; }
        if(($filters['nace']??'')!==''){ $clauses[]="LTRIM(RTRIM(a.NACE_CODE_REG))=:nace"; $params[':nace']=trim($filters['nace']); }
        if(($filters['atk_status']??'')!==''){ $clauses[]="COALESCE(s.status_code,CASE WHEN a.ATK_MBYLLUR=1 THEN 'DEACTIVATED' ELSE 'ACTIVE' END)=:atk"; $params[':atk']=$filters['atk_status']; }
        $classification=$filters['classification']??'';
        if($classification==='UNCLASSIFIED') $clauses[]='x.id IS NULL';
        elseif($classification==='AUTO') $clauses[]="x.assignment_method='AUTO'";
        elseif($classification==='MANUAL') $clauses[]="x.assignment_method='MANUAL'";
        elseif($classification==='AMBIGUOUS') $clauses[]='x.id IS NULL AND c.candidate_count>1';
        return [implode(' AND ',$clauses),$params];
    }
}
