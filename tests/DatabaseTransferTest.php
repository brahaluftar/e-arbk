<?php
declare(strict_types=1);

use App\Deployment\DatabaseTransfer;
use PHPUnit\Framework\TestCase;

final class DatabaseTransferTest extends TestCase
{
    private string $directory;
    private array $manifest;

    protected function setUp(): void
    {
        $this->directory=sys_get_temp_dir().'/arbk-transfer-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory.'/schema.sql','-- test schema');
        $this->manifest=['format'=>1,'tables'=>[],'files'=>[],'schema_sha256'=>hash_file('sha256',$this->directory.'/schema.sql')];
        foreach(DatabaseTransfer::TABLES as $name){
            $file=$name.'.jsonl.gz';file_put_contents($this->directory.'/'.$file,gzencode(''));
            $this->manifest['tables'][]=['name'=>$name,'file'=>$file,'sha256'=>hash_file('sha256',$this->directory.'/'.$file),'rows'=>0];
        }
        $this->save();
    }

    private function save(): void {file_put_contents($this->directory.'/manifest.json',json_encode($this->manifest,JSON_THROW_ON_ERROR));}
    protected function tearDown(): void {foreach(glob($this->directory.'/*')?:[] as $file)unlink($file);rmdir($this->directory);}

    public function testAcceptsIntactTransferFiles(): void
    {
        self::assertCount(14,(new DatabaseTransfer())->manifest($this->directory)['tables']);
        self::assertContains('password_reset_tokens', DatabaseTransfer::TABLES);
        self::assertContains('business_invoices', DatabaseTransfer::TABLES);
        self::assertContains('business_invoice_payments', DatabaseTransfer::TABLES);
    }

    public function testRejectsChangedDataBeforeDatabaseAccess(): void
    {
        file_put_contents($this->directory.'/ARBK_LIST.jsonl.gz','corrupt');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');
        (new DatabaseTransfer())->manifest($this->directory);
    }

    public function testRejectsTraversalPaths(): void
    {
        $this->manifest['tables'][0]['file']='../outside';$this->save();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe transfer path');
        (new DatabaseTransfer())->manifest($this->directory);
    }

    public function testRejectsUnexpectedTables(): void
    {
        $this->manifest['tables'][0]['name']='unrelated';$this->save();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported database transfer manifest');
        (new DatabaseTransfer())->manifest($this->directory);
    }
}
