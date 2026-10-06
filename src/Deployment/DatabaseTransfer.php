<?php
declare(strict_types=1);

namespace App\Deployment;

use PDO;
use RuntimeException;
use Throwable;

/** Logical, fresh-database transfer of the application's SQL Server objects. */
final class DatabaseTransfer
{
    public const TABLES = ['app_users','ARBK_LIST','ATK_LIST','NACE_LIST','business_atk_status','business_nace_assignments','audit_log','business_import_runs','business_import_staging','schema_migrations','login_rate_limits','password_reset_tokens'];
    private const JSON = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    public static function quote(string $name): string { return '['.str_replace(']',']]', $name).']'; }

    /** Export under shared table locks: no source data/schema changes. */
    public function export(PDO $pdo, string $directory, string $root): array
    {
        if (file_exists($directory)) throw new RuntimeException('Export directory already exists; choose a new path.');
        if (!mkdir($directory,0700,true)) throw new RuntimeException('Cannot create export directory.');
        mkdir($directory.'/files',0700);
        $pdo->exec('SET LOCK_TIMEOUT 15000; SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;');
        $pdo->beginTransaction();
        try {
            foreach (self::TABLES as $table) $pdo->query('SELECT COUNT_BIG(*) FROM dbo.'.self::quote($table).' WITH(TABLOCK,HOLDLOCK)')->fetchColumn();
            if ((int)$pdo->query("SELECT COUNT(*) FROM dbo.business_import_runs WHERE status IN('QUEUED','PROCESSING')")->fetchColumn() !== 0) throw new RuntimeException('Finish queued/processing imports before taking a deployment snapshot.');
            $manifest = ['format'=>1,'created_at'=>gmdate(DATE_ATOM),'minimum_sql_compatibility'=>130,
                'collation'=>(string)$pdo->query("SELECT CONVERT(varchar(128),DATABASEPROPERTYEX(DB_NAME(),'Collation'))")->fetchColumn(),
                'excluded'=>['dbo.arbk','dbo.atk2','dbo.BizList1','legacy stored procedures','login_rate_limits rows','password_reset_tokens rows','rowversion values'],
                'before'=>[], 'after'=>[], 'tables'=>[], 'files'=>[]];
            $sequence=$pdo->query("SELECT CONVERT(bigint,current_value) current_value,CONVERT(bigint,increment) increment FROM sys.sequences WHERE name='arbk_regulation_id_seq' AND schema_id=SCHEMA_ID('dbo')")->fetch();
            if (!$sequence || (int)$sequence['increment'] !== 1) throw new RuntimeException('Expected ARBK ID sequence is missing or unsupported.');
            $next=max((int)$sequence['current_value']+1,(int)$pdo->query('SELECT ISNULL(MAX(REGULATION_ID),0)+1 FROM dbo.ARBK_LIST')->fetchColumn());
            $manifest['before'][]='CREATE SEQUENCE dbo.arbk_regulation_id_seq AS bigint START WITH '.$next.' INCREMENT BY 1;';
            foreach (self::TABLES as $table) {
                $metadata=$this->table($pdo,$table);
                $manifest['before'][]=$metadata['create'];
                array_push($manifest['after'],...$metadata['after']);
                unset($metadata['create'],$metadata['after']);
                $path=$table.'.jsonl.gz';
                $handle=gzopen($directory.'/'.$path,'wb6');
                if ($handle===false) throw new RuntimeException('Cannot create data file.');
                $hash=hash_init('sha256');$count=0;
                try {
                    if (!in_array($table, ['login_rate_limits', 'password_reset_tokens'], true)) {
                        $statement=$pdo->query($metadata['select']);
                        while ($row=$statement->fetch(PDO::FETCH_NUM)) {
                            $line=json_encode($row,self::JSON)."\n";
                            if (gzwrite($handle,$line)!==strlen($line)) throw new RuntimeException('Data export write failed.');
                            hash_update($hash,$line);$count++;
                        }
                        $statement->closeCursor();
                    }
                } finally { gzclose($handle); }
                $metadata += ['name'=>$table,'file'=>$path,'rows'=>$count,'data_sha256'=>hash_final($hash),'sha256'=>hash_file('sha256',$directory.'/'.$path)];
                $manifest['tables'][]=$metadata;
                echo "Exported $table: $count rows\n";
            }
            // Foreign keys are installed only after all tables and their keys exist.
            foreach (self::TABLES as $table) {
                $statement=$pdo->prepare("SELECT fk.name,fk.object_id,OBJECT_NAME(fk.referenced_object_id) referenced_table,SCHEMA_NAME(t.schema_id) referenced_schema,fk.delete_referential_action_desc delete_action,fk.update_referential_action_desc update_action FROM sys.foreign_keys fk JOIN sys.tables t ON t.object_id=fk.referenced_object_id WHERE fk.parent_object_id=OBJECT_ID(:name)");
                $statement->execute(['name'=>'dbo.'.$table]);
                foreach ($statement->fetchAll() as $fk) {
                    if ($fk['referenced_schema']!=='dbo' || !in_array($fk['referenced_table'],self::TABLES,true)) throw new RuntimeException('Foreign key points outside the deployment table set.');
                    $columns=$pdo->query('SELECT COL_NAME(parent_object_id,parent_column_id) source_name,COL_NAME(referenced_object_id,referenced_column_id) target_name FROM sys.foreign_key_columns WHERE constraint_object_id='.(int)$fk['object_id'].' ORDER BY constraint_column_id')->fetchAll();
                    $source=implode(',',array_map(fn($c)=>self::quote($c['source_name']),$columns));
                    $target=implode(',',array_map(fn($c)=>self::quote($c['target_name']),$columns));
                    $manifest['after'][]='ALTER TABLE dbo.'.self::quote($table).' WITH CHECK ADD CONSTRAINT '.self::quote($fk['name']).' FOREIGN KEY('.$source.') REFERENCES dbo.'.self::quote($fk['referenced_table']).'('.$target.') ON DELETE '.str_replace('_',' ',$fk['delete_action']).' ON UPDATE '.str_replace('_',' ',$fk['update_action']).';';
                }
            }
            $view=$pdo->query("SELECT OBJECT_DEFINITION(OBJECT_ID('dbo.v_business_master'))")->fetchColumn();
            if (!is_string($view) || $view==='') throw new RuntimeException('Business view definition is unavailable.');
            $manifest['after'][]=preg_replace('/\b(?:CREATE\s+OR\s+ALTER|ALTER)\s+VIEW\b/i','CREATE VIEW',$view,1);
            foreach ($pdo->query('SELECT id,storage_path,file_sha256 FROM dbo.business_import_runs')->fetchAll() as $run) {
                $relative=str_replace('\\','/',(string)$run['storage_path']);
                if (preg_match('~\Avar/imports/([a-f0-9]{32}\.xlsx)\z~D',$relative,$m)!==1) throw new RuntimeException('Import file path is not portable for run '.$run['id']);
                $source=$root.'/'.$relative;$destination='files/'.$m[1];
                if (!is_file($source) || !hash_equals($run['file_sha256'],hash_file('sha256',$source))) throw new RuntimeException('Missing or changed XLSX for import run '.$run['id']);
                if (!copy($source,$directory.'/'.$destination)) throw new RuntimeException('Cannot copy import workbook.');
                $manifest['files'][]=['file'=>$destination,'target'=>$relative,'sha256'=>$run['file_sha256']];
            }
            $pdo->commit();
            file_put_contents($directory.'/schema.sql',implode("\nGO\n",array_merge($manifest['before'],$manifest['after']))."\nGO\n");
            $manifest['schema_sha256']=hash_file('sha256',$directory.'/schema.sql');
            file_put_contents($directory.'/manifest.json',json_encode($manifest,self::JSON|JSON_PRETTY_PRINT)."\n");
            return $manifest;
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        finally { $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED; SET LOCK_TIMEOUT -1;'); }
    }

    private function table(PDO $pdo,string $table): array
    {
        $s=$pdo->prepare("SELECT c.name,ty.name type,c.max_length,c.precision,c.scale,c.is_nullable,c.is_identity,c.is_computed,c.is_rowguidcol,c.collation_name,dc.name default_name,dc.definition default_sql,CONVERT(varchar(50),ic.seed_value) seed_value,CONVERT(varchar(50),ic.increment_value) increment_value,CONVERT(varchar(50),ic.last_value) last_value FROM sys.columns c JOIN sys.types ty ON ty.user_type_id=c.user_type_id LEFT JOIN sys.default_constraints dc ON dc.object_id=c.default_object_id LEFT JOIN sys.identity_columns ic ON ic.object_id=c.object_id AND ic.column_id=c.column_id WHERE c.object_id=OBJECT_ID(:name) ORDER BY c.column_id");
        $s->execute(['name'=>'dbo.'.$table]);$columns=$s->fetchAll();
        if (!$columns) throw new RuntimeException('Missing table '.$table);
        $definitions=[];$transfer=[];$select=[];$identity=false;$reseed=null;
        foreach ($columns as $column) {
            if ((int)$column['is_computed']) throw new RuntimeException('Computed columns are not supported by this exporter.');
            $name=self::quote($column['name']);$type=$this->sqlType($column);$definition=$name.' '.$type;
            if ($column['collation_name']!==null) {
                if (preg_match('/\A[A-Za-z0-9_]+\z/D',$column['collation_name'])!==1) throw new RuntimeException('Unsupported collation name.');
                $definition.=' COLLATE '.$column['collation_name'];
            }
            if ((int)$column['is_identity']) {
                $definition.=' IDENTITY('.$column['seed_value'].','.$column['increment_value'].')';$identity=true;$reseed=$column['last_value'];
            }
            if ((int)$column['is_rowguidcol']) $definition.=' ROWGUIDCOL';
            $definition.=(int)$column['is_nullable']?' NULL':' NOT NULL';
            if ($column['default_sql']!==null) $definition.=' CONSTRAINT '.self::quote($column['default_name']).' DEFAULT '.$column['default_sql'];
            $definitions[]=$definition;
            if (in_array($column['type'],['timestamp','rowversion'],true)) continue;
            $binary=in_array($column['type'],['binary','varbinary'],true);
            $expression=match($column['type']) {
                'float','real'=>'CONVERT(varchar(64),'.$name.',3)',
                'money','smallmoney'=>'CONVERT(varchar(64),'.$name.',2)',
                'date','datetime','datetime2','smalldatetime','time','datetimeoffset'=>'CONVERT(varchar(50),'.$name.',126)',
                'binary','varbinary'=>'CONVERT(varchar(max),'.$name.',2)',
                default=>'CONVERT(nvarchar(max),'.$name.')'
            };
            $select[]=$expression;
            $transfer[]=['name'=>$column['name'],'type'=>$type,'binary'=>$binary];
        }
        $create='CREATE TABLE dbo.'.self::quote($table)." (\n ".implode(",\n ",$definitions)."\n);";
        $after=[];$order=[];
        $s=$pdo->prepare("SELECT i.index_id,i.name,i.type,i.is_unique,i.is_primary_key,i.is_unique_constraint,i.filter_definition,i.is_disabled FROM sys.indexes i WHERE i.object_id=OBJECT_ID(:name) AND i.index_id>0 ORDER BY i.index_id");$s->execute(['name'=>'dbo.'.$table]);
        foreach ($s->fetchAll() as $index) {
            if (!in_array((int)$index['type'],[1,2],true) || (int)$index['is_disabled']) throw new RuntimeException('Unsupported or disabled index on '.$table);
            $q=$pdo->prepare('SELECT c.name,ic.is_descending_key,ic.is_included_column FROM sys.index_columns ic JOIN sys.columns c ON c.object_id=ic.object_id AND c.column_id=ic.column_id WHERE ic.object_id=OBJECT_ID(:name) AND ic.index_id=:idx ORDER BY ic.key_ordinal,ic.index_column_id');$q->execute(['name'=>'dbo.'.$table,'idx'=>$index['index_id']]);
            $keys=[];$included=[];$keyNames=[];
            foreach ($q->fetchAll() as $key) {
                if ((int)$key['is_included_column']) $included[]=self::quote($key['name']);
                else { $keys[]=self::quote($key['name']).((int)$key['is_descending_key']?' DESC':' ASC');$keyNames[]=self::quote($key['name']); }
            }
            if ($order===[] && (int)$index['is_unique'] && $index['filter_definition']===null) $order=$keyNames;
            $cluster=(int)$index['type']===1?'CLUSTERED':'NONCLUSTERED';
            if ((int)$index['is_primary_key'] || (int)$index['is_unique_constraint']) {
                $sql='ALTER TABLE dbo.'.self::quote($table).' ADD CONSTRAINT '.self::quote($index['name']).((int)$index['is_primary_key']?' PRIMARY KEY ':' UNIQUE ').$cluster.' ('.implode(',',$keys).')';
            } else {
                $sql='CREATE '.((int)$index['is_unique']?'UNIQUE ':'').$cluster.' INDEX '.self::quote($index['name']).' ON dbo.'.self::quote($table).' ('.implode(',',$keys).')';
                if ($included!==[]) $sql.=' INCLUDE('.implode(',',$included).')';
                if ($index['filter_definition']!==null) $sql.=' WHERE '.$index['filter_definition'];
            }
            $after[]=$sql.';';
        }
        $q=$pdo->prepare('SELECT name,definition FROM sys.check_constraints WHERE parent_object_id=OBJECT_ID(:name)');$q->execute(['name'=>'dbo.'.$table]);
        foreach ($q->fetchAll() as $check) $after[]='ALTER TABLE dbo.'.self::quote($table).' WITH CHECK ADD CONSTRAINT '.self::quote($check['name']).' CHECK '.$check['definition'].';';
        if ($reseed!==null) $after[]="DBCC CHECKIDENT ('dbo.".str_replace("'","''",$table)."', RESEED, ".$reseed.') WITH NO_INFOMSGS;';
        if ($order===[]) $order=match($table) {'ATK_LIST'=>['[ID]'],'NACE_LIST'=>['[NACErowGUID]'],default=>throw new RuntimeException('No stable ordering for '.$table)};
        return ['create'=>$create,'after'=>$after,'columns'=>$transfer,'identity'=>$identity,'select'=>'SELECT '.implode(',',$select).' FROM dbo.'.self::quote($table).' ORDER BY '.implode(',',$order)];
    }

    private function sqlType(array $column): string
    {
        $type=$column['type'];
        if (in_array($type,['varchar','nvarchar','char','nchar','binary','varbinary'],true)) {
            $length=(int)$column['max_length'];
            if ($length===-1) return $type.'(max)';
            return $type.'('.(in_array($type,['nvarchar','nchar'],true)?intdiv($length,2):$length).')';
        }
        if (in_array($type,['decimal','numeric'],true)) return $type.'('.(int)$column['precision'].','.(int)$column['scale'].')';
        if (in_array($type,['datetime2','datetimeoffset','time'],true)) return $type.'('.(int)$column['scale'].')';
        if ($type==='float') return 'float('.(int)$column['precision'].')';
        if (in_array($type,['bigint','int','smallint','tinyint','bit','real','money','smallmoney','date','datetime','smalldatetime','uniqueidentifier','timestamp','rowversion'],true)) return $type;
        throw new RuntimeException('Unsupported column type '.$type);
    }

    public function manifest(string $directory): array
    {
        $manifest=json_decode((string)file_get_contents($directory.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
        if (($manifest['format']??null)!==1 || array_column($manifest['tables']??[],'name')!==self::TABLES) throw new RuntimeException('Unsupported database transfer manifest.');
        foreach (array_merge($manifest['tables'],$manifest['files']) as $file) {
            if (preg_match('~\A(?:files/)?[A-Za-z0-9_.-]+\z~D',$file['file'])!==1 || str_contains($file['file'],'..')) throw new RuntimeException('Unsafe transfer path.');
            $path=$directory.'/'.$file['file'];
            if (!is_file($path) || !hash_equals($file['sha256'],hash_file('sha256',$path))) throw new RuntimeException('Transfer file checksum mismatch: '.$file['file']);
        }
        if (!hash_equals($manifest['schema_sha256'],hash_file('sha256',$directory.'/schema.sql'))) throw new RuntimeException('Schema checksum mismatch.');
        return $manifest;
    }

    /** Entire fresh schema + data + constraints commit together; never merge into existing data. */
    public function import(PDO $pdo,string $directory): void
    {
        $manifest=$this->manifest($directory);$digest=hash_file('sha256',$directory.'/manifest.json');
        if ((int)$pdo->query('SELECT compatibility_level FROM sys.databases WHERE name=DB_NAME()')->fetchColumn()<130) throw new RuntimeException('SQL Server compatibility level 130 or newer is required.');
        $pdo->exec('SET XACT_ABORT ON; SET ANSI_NULLS ON; SET QUOTED_IDENTIFIER ON; SET ANSI_PADDING ON; SET ANSI_WARNINGS ON; SET CONCAT_NULL_YIELDS_NULL ON; SET ARITHABORT ON; SET NUMERIC_ROUNDABORT OFF;');
        $pdo->beginTransaction();$identityTable=null;
        try {
            $lock=(int)$pdo->query("DECLARE @r int; EXEC @r=sys.sp_getapplock @Resource=N'arbk-initial-database-transfer',@LockMode='Exclusive',@LockOwner='Transaction',@LockTimeout=0; SELECT @r")->fetchColumn();
            if ($lock<0) throw new RuntimeException('Another database import is running.');
            if ($pdo->query("SELECT OBJECT_ID('dbo.deployment_imports','U')")->fetchColumn()) {
                $s=$pdo->prepare('SELECT COUNT(*) FROM dbo.deployment_imports WHERE manifest_sha256=:hash');$s->execute(['hash'=>$digest]);
                if ((int)$s->fetchColumn()===1) {$pdo->commit();echo "This snapshot was already imported; existing data left unchanged.\n";return;}
            }
            if ((int)$pdo->query('SELECT COUNT(*) FROM sys.objects WHERE is_ms_shipped=0 AND type IN(\'U\',\'V\',\'SO\',\'P\',\'FN\',\'IF\',\'TF\')')->fetchColumn()!==0) throw new RuntimeException('Target database is not empty. Import refuses to overwrite existing objects or data.');
            foreach ($manifest['before'] as $sql) $pdo->exec($sql);
            foreach ($manifest['tables'] as $table) {
                $name='dbo.'.self::quote($table['name']);
                if ($table['identity']) {$pdo->exec('SET IDENTITY_INSERT '.$name.' ON');$identityTable=$name;}
                $names=[];$extract=[];$values=[];
                foreach ($table['columns'] as $i=>$column) {
                    $names[]=self::quote($column['name']);
                    $extract[]='[c'.$i.'] nvarchar(max) \'$['.$i.']\'';
                    $values[]='CONVERT('.$column['type'].',[c'.$i.']'.($column['binary']?',2':'').')';
                }
                $statement=$pdo->prepare('INSERT '.$name.'('.implode(',',$names).') SELECT '.implode(',',$values).' FROM OPENJSON(?) WITH('.implode(',',$extract).')');
                $handle=gzopen($directory.'/'.$table['file'],'rb');if ($handle===false) throw new RuntimeException('Cannot read transfer data.');
                $batch=[];$count=0;$hash=hash_init('sha256');
                try {
                    while (($line=gzgets($handle))!==false) {
                        hash_update($hash,$line);$row=json_decode($line,true,512,JSON_THROW_ON_ERROR);
                        if (!is_array($row) || count($row)!==count($names)) throw new RuntimeException('Malformed transfer row.');
                        $batch[]=$row;$count++;
                        if (count($batch)>=500) {$statement->execute([json_encode($batch,self::JSON)]);$batch=[];}
                    }
                    if ($batch!==[]) $statement->execute([json_encode($batch,self::JSON)]);
                } finally {gzclose($handle);}
                if ($count!==$table['rows'] || !hash_equals($table['data_sha256'],hash_final($hash))) throw new RuntimeException('Transfer content mismatch for '.$table['name']);
                if ($identityTable!==null) {$pdo->exec('SET IDENTITY_INSERT '.$identityTable.' OFF');$identityTable=null;}
                echo 'Imported '.$table['name'].': '.$count." rows\n";
            }
            foreach ($manifest['after'] as $sql) $pdo->exec($sql);
            $this->verify($pdo,$directory,$manifest);
            $pdo->exec('CREATE TABLE dbo.deployment_imports(manifest_sha256 char(64) NOT NULL PRIMARY KEY,imported_at datetime2(3) NOT NULL DEFAULT SYSUTCDATETIME());');
            $pdo->prepare('INSERT dbo.deployment_imports(manifest_sha256) VALUES(:hash)')->execute(['hash'=>$digest]);
            $pdo->commit();echo "Database import committed.\n";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($identityTable!==null) {try{$pdo->exec('SET IDENTITY_INSERT '.$identityTable.' OFF');}catch(Throwable){}}
            throw $e;
        }
    }

    public function verify(PDO $pdo,string $directory,?array $manifest=null): void
    {
        $manifest??=$this->manifest($directory);
        foreach ($manifest['tables'] as $table) {
            $count=0;$hash=hash_init('sha256');$s=$pdo->query($table['select']);
            while ($row=$s->fetch(PDO::FETCH_NUM)) {hash_update($hash,json_encode($row,self::JSON)."\n");$count++;}
            $s->closeCursor();
            if ($count!==$table['rows'] || !hash_equals($table['data_sha256'],hash_final($hash))) throw new RuntimeException('Database verification mismatch: '.$table['name']);
            echo 'Verified '.$table['name'].': '.$count." rows and content checksum\n";
        }
    }
}
