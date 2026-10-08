<?php
declare(strict_types=1);
namespace App\Document;
use RuntimeException;
final class JobArtifactStore
{
    public function __construct(private string $root){}
    public function destination(int $jobId,string $date,string $extension):array
    {
        if($jobId<1||preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$date)!==1||!in_array($extension,['pdf','xlsx'],true))throw new RuntimeException('Referenca e eksportit nuk është valide.');
        $year=substr($date,0,4);$directory=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.'job-exports'.DIRECTORY_SEPARATOR.$year;if(!is_dir($directory)&&!mkdir($directory,0770,true)&&!is_dir($directory))throw new RuntimeException('Dosja e eksporteve nuk mund të krijohej.');
        $key='job-exports/'.$year.'/job-'.$jobId.'-'.$date.'.'.$extension;return [$key,$directory.DIRECTORY_SEPARATOR.'job-'.$jobId.'-'.$date.'.'.$extension];
    }
    public function path(string $key):string
    {
        if(preg_match('~\Ajob-exports/\d{4}/job-\d+-\d{4}-\d{2}-\d{2}\.(?:pdf|xlsx)\z~',$key)!==1)throw new RuntimeException('Referenca e eksportit nuk është valide.');$path=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);if(!is_file($path))throw new RuntimeException('Eksporti nuk u gjet.');return $path;
    }
}
