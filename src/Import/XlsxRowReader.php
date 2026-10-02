<?php
declare(strict_types=1);
namespace App\Import;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

final class XlsxRowReader
{
    /** @return Generator<int,array<int,string|null>> */
    public function rows(string $path): Generator
    {
        $zip=new ZipArchive();if($zip->open($path)!==true) throw new RuntimeException('Invalid XLSX archive.');
        try {if($zip->locateName('xl/worksheets/sheet1.xml')===false) throw new RuntimeException('XLSX sheet1.xml is missing.');}
        finally{$zip->close();}
        $shared=$this->sharedStrings($path);$reader=new XMLReader();
        if(!$reader->open($this->uri($path,'xl/worksheets/sheet1.xml'),null,LIBXML_NONET|LIBXML_COMPACT)) throw new RuntimeException('Cannot read XLSX worksheet.');
        try {
            while($reader->read()) {
                if($reader->nodeType!==XMLReader::ELEMENT||$reader->localName!=='row') continue;
                $xml=$reader->readOuterXml();$row=simplexml_load_string($xml);if($row===false) continue;
                $values=[];
                foreach($row->xpath('./*[local-name()="c"]')?:[] as $cell){$ref=(string)$cell['r'];$column=$this->columnIndex($ref);$type=(string)$cell['t'];$valueNode=($cell->xpath('./*[local-name()="v"]')?:[])[0]??null;$raw=$valueNode===null?'':(string)$valueNode;
                    if($type==='s')$value=$shared[(int)$raw]??null;elseif($type==='inlineStr'){$parts=$cell->xpath('.//*[local-name()="t"]')?:[];$value=implode('',array_map(static fn($part)=>(string)$part,$parts));}elseif($type==='b')$value=$raw==='1'?'1':'0';else $value=$raw===''?null:$raw;
                    $values[$column]=$value;
                }
                if($values===[]) yield [];
                else {$max=max(array_keys($values));$dense=[];for($i=0;$i<=$max;$i++)$dense[]=$values[$i]??null;yield $dense;}
            }
        } finally {$reader->close();}
    }

    /** @return list<string> */
    private function sharedStrings(string $path): array
    {
        $zip=new ZipArchive();$zip->open($path);$exists=$zip->locateName('xl/sharedStrings.xml')!==false;$zip->close();if(!$exists)return [];
        $reader=new XMLReader();if(!$reader->open($this->uri($path,'xl/sharedStrings.xml'),null,LIBXML_NONET|LIBXML_COMPACT))return [];$strings=[];
        try {while($reader->read()){if($reader->nodeType===XMLReader::ELEMENT&&$reader->localName==='si'){$node=simplexml_load_string($reader->readOuterXml());$text='';if($node!==false){foreach($node->xpath('.//*[local-name()="t"]')?:[] as $part)$text.=(string)$part;}$strings[]=$text;}}}finally{$reader->close();}
        return $strings;
    }
    private function uri(string $path,string $entry): string{return 'zip://'.str_replace('\\','/',$path).'#'.$entry;}
    private function columnIndex(string $reference): int{preg_match('/^[A-Z]+/i',$reference,$m);$letters=strtoupper($m[0]??'A');$value=0;foreach(str_split($letters) as $letter)$value=$value*26+(ord($letter)-64);return $value-1;}
}
