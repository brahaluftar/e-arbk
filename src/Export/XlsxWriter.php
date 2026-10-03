<?php
declare(strict_types=1);
namespace App\Export;

use RuntimeException;
use XMLWriter;
use ZipArchive;

final class XlsxWriter
{
    /** @param array<string,string> $columns @param iterable<array<string,mixed>> $rows */
    public function write(string $destination,array $columns,iterable $rows,string $sheetName='Bizneset'):int
    {
        $sheet=tempnam(sys_get_temp_dir(),'arbk_sheet_');if($sheet===false)throw new RuntimeException('Cannot create export worksheet.');
        try{
            $xml=new XMLWriter();if(!$xml->openUri($sheet))throw new RuntimeException('Cannot open export worksheet.');
            $xml->startDocument('1.0','UTF-8');$xml->startElementNS(null,'worksheet','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $xml->startElement('sheetViews');$xml->startElement('sheetView');$xml->writeAttribute('workbookViewId','0');$xml->startElement('pane');$xml->writeAttribute('ySplit','1');$xml->writeAttribute('topLeftCell','A2');$xml->writeAttribute('activePane','bottomLeft');$xml->writeAttribute('state','frozen');$xml->endElement();$xml->endElement();$xml->endElement();
            $xml->startElement('sheetData');$rowNumber=1;$this->row($xml,$rowNumber,array_values($columns),true);
            foreach($rows as $data){if($rowNumber>=1048576)break;$rowNumber++;$values=[];foreach(array_keys($columns) as $field)$values[]=$data[$field]??null;$this->row($xml,$rowNumber,$values,false);}
            $xml->endElement();$xml->startElement('autoFilter');$xml->writeAttribute('ref','A1:'.$this->column(count($columns)).$rowNumber);$xml->endElement();$xml->endElement();$xml->endDocument();$xml->flush();
            $zip=new ZipArchive();if($zip->open($destination,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Cannot create XLSX file.');
            $zip->addFromString('[Content_Types].xml',$this->contentTypes());$zip->addFromString('_rels/.rels',$this->rootRelationships());$zip->addFromString('xl/workbook.xml',$this->workbook($sheetName));$zip->addFromString('xl/_rels/workbook.xml.rels',$this->workbookRelationships());$zip->addFromString('xl/styles.xml',$this->styles());$zip->addFile($sheet,'xl/worksheets/sheet1.xml');
            if(!$zip->close())throw new RuntimeException('Cannot finalize XLSX file.');return $rowNumber-1;
        }finally{if(is_file($sheet))unlink($sheet);}
    }

    /** @param list<mixed> $values */
    private function row(XMLWriter $xml,int $number,array $values,bool $header):void
    {
        $xml->startElement('row');$xml->writeAttribute('r',(string)$number);
        foreach($values as $index=>$value){$xml->startElement('c');$xml->writeAttribute('r',$this->column($index+1).$number);if($header)$xml->writeAttribute('s','1');
            if(!$header&&(is_int($value)||is_float($value))){$xml->startElement('v');$xml->text((string)$value);$xml->endElement();}
            else{$xml->writeAttribute('t','inlineStr');$xml->startElement('is');$xml->startElement('t');if(is_string($value)&&($value!==trim($value)))$xml->writeAttribute('xml:space','preserve');$xml->text($value===null?'':(string)$value);$xml->endElement();$xml->endElement();}
            $xml->endElement();}
        $xml->endElement();
    }
    private function column(int $number):string{$letters='';while($number>0){$number--;$letters=chr(65+$number%26).$letters;$number=intdiv($number,26);}return $letters;}
    private function contentTypes():string{return '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';}
    private function rootRelationships():string{return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';}
    private function workbook(string $sheetName):string{return '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.htmlspecialchars(mb_substr($sheetName,0,31),ENT_XML1|ENT_QUOTES,'UTF-8').'" sheetId="1" r:id="rId1"/></sheets></workbook>';}
    private function workbookRelationships():string{return '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';}
    private function styles():string{return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF365F7D"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>';}
}
