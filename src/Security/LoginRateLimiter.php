<?php
declare(strict_types=1);
namespace App\Security;
use App\Support\Config;
use PDO;

final class LoginRateLimiter
{
    public function __construct(private PDO $pdo,private Config $config) {}

    public function allowed(string $email,string $ip): bool
    {
        foreach($this->dimensions($email,$ip) as [$action,$hash]) {
            $statement=$this->pdo->prepare('SELECT blocked_until FROM dbo.login_rate_limits WHERE action_code=:action AND dimension_hash=CONVERT(binary(32),:hash,2)');
            $statement->execute(['action'=>$action,'hash'=>$hash]);
            $blocked=$statement->fetchColumn();
            if(is_string($blocked) && strtotime($blocked.' UTC')>time()) return false;
        }
        return true;
    }

    public function failed(string $email,string $ip): void
    {
        foreach($this->dimensions($email,$ip) as [$action,$hash]) $this->recordFailure($action,$hash);
    }

    public function succeeded(string $email,string $ip): void
    {
        foreach($this->dimensions($email,$ip) as [$action,$hash]) {
            $statement=$this->pdo->prepare('DELETE dbo.login_rate_limits WHERE action_code=:action AND dimension_hash=CONVERT(binary(32),:hash,2)');
            $statement->execute(['action'=>$action,'hash'=>$hash]);
        }
    }

    private function recordFailure(string $action,string $hash): void
    {
        $window=$this->config->int('LOGIN_RATE_WINDOW_SECONDS');$max=$this->config->int('LOGIN_RATE_MAX_ATTEMPTS');$block=$this->config->int('LOGIN_RATE_BLOCK_SECONDS');
        $sql="MERGE dbo.login_rate_limits WITH(HOLDLOCK) AS target USING(SELECT :action action_code,CONVERT(binary(32),:hash,2) dimension_hash) source ON target.action_code=source.action_code AND target.dimension_hash=source.dimension_hash WHEN MATCHED THEN UPDATE SET attempt_count=CASE WHEN window_started_at<DATEADD(second,CONVERT(int,:window1),SYSUTCDATETIME()) THEN 1 ELSE attempt_count+1 END,window_started_at=CASE WHEN window_started_at<DATEADD(second,CONVERT(int,:window2),SYSUTCDATETIME()) THEN SYSUTCDATETIME() ELSE window_started_at END,blocked_until=CASE WHEN (CASE WHEN window_started_at<DATEADD(second,CONVERT(int,:window3),SYSUTCDATETIME()) THEN 1 ELSE attempt_count+1 END)>=CONVERT(int,:maximum) THEN DATEADD(second,CONVERT(int,:block),SYSUTCDATETIME()) ELSE blocked_until END,updated_at=SYSUTCDATETIME() WHEN NOT MATCHED THEN INSERT(action_code,dimension_hash,window_started_at,attempt_count,updated_at) VALUES(source.action_code,source.dimension_hash,SYSUTCDATETIME(),1,SYSUTCDATETIME());";
        $statement=$this->pdo->prepare($sql);$statement->execute(['action'=>$action,'hash'=>$hash,'window1'=>-$window,'window2'=>-$window,'window3'=>-$window,'maximum'=>$max,'block'=>$block]);
    }

    /** @return list<array{string,string}> */
    private function dimensions(string $email,string $ip): array
    {
        $key=$this->config->string('APP_KEY');if($key==='') $key='development-only-rate-limit-key';
        return [['login_ip',hash_hmac('sha256',$ip,$key)],['login_email',hash_hmac('sha256',mb_strtoupper(trim($email),'UTF-8'),$key)]];
    }
}
