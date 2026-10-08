<?php
declare(strict_types=1);
namespace App\Document;
use RuntimeException;
final class DocumentStore
{
    public function __construct(private string $root) {}
    public function put(string $category,string $pdf): string
    {
        if(!str_starts_with($pdf,'%PDF-')||!in_array($category,['invoices','permits'],true))throw new RuntimeException('Dokumenti PDF nuk është valid.');
        $year=date('Y');$directory=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.$category.DIRECTORY_SEPARATOR.$year;
        if(!is_dir($directory)&&!mkdir($directory,0770,true)&&!is_dir($directory))throw new RuntimeException('Dosja e dokumenteve nuk mund të krijohej.');
        $name=bin2hex(random_bytes(24)).'.pdf';$path=$directory.DIRECTORY_SEPARATOR.$name;
        if(file_put_contents($path,$pdf,LOCK_EX)!==strlen($pdf))throw new RuntimeException('Dokumenti PDF nuk mund të ruhej.');
        chmod($path,0660);return $category.'/'.$year.'/'.$name;
    }
    public function path(string $key): string
    {
        if(preg_match('~\A(?:invoices|permits)/[0-9]{4}/[a-f0-9]{48}\.pdf\z~',$key)!==1)throw new RuntimeException('Referenca e dokumentit nuk është valide.');
        $path=rtrim($this->root,'/\\').DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$key);
        if(!is_file($path))throw new RuntimeException('Dokumenti nuk u gjet.');return $path;
    }
}
