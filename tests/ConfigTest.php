<?php
declare(strict_types=1);

use App\Support\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    private string $directory;
    private string|false $previousFile;
    private string|false $previousValue;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/arbk-config-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $this->previousFile = getenv('ARBK_ENV_FILE');
        $this->previousValue = getenv('ARBK_CONFIG_TEST_VALUE');
        putenv('ARBK_ENV_FILE');
        putenv('ARBK_CONFIG_TEST_VALUE');
    }

    protected function tearDown(): void
    {
        foreach (['.env','external.env'] as $file) if (is_file($this->directory.'/'.$file)) unlink($this->directory.'/'.$file);
        rmdir($this->directory);
        putenv($this->previousFile === false ? 'ARBK_ENV_FILE' : 'ARBK_ENV_FILE='.$this->previousFile);
        putenv($this->previousValue === false ? 'ARBK_CONFIG_TEST_VALUE' : 'ARBK_CONFIG_TEST_VALUE='.$this->previousValue);
    }

    public function testLocalDevelopmentFallback(): void
    {
        if (is_dir(Config::PRODUCTION_ENV_DIRECTORY)) self::markTestSkipped('Production directory is present.');
        file_put_contents($this->directory.'/.env',"ARBK_CONFIG_TEST_VALUE=local\n");
        self::assertSame('local',Config::load($this->directory,[])->string('ARBK_CONFIG_TEST_VALUE'));
    }

    public function testExternalFileReplacesLocalFileAndKeepsDefaults(): void
    {
        file_put_contents($this->directory.'/.env',"ARBK_CONFIG_TEST_VALUE=local\nLOCAL_ONLY=do-not-load\n");
        file_put_contents($this->directory.'/external.env',"ARBK_CONFIG_TEST_VALUE=external\n");
        putenv('ARBK_ENV_FILE='.$this->directory.'/external.env');
        $config=Config::load($this->directory,['ARBK_CONFIG_TEST_VALUE'=>'default','DEFAULT_ONLY'=>'retained']);
        self::assertSame('external',$config->string('ARBK_CONFIG_TEST_VALUE'));
        self::assertSame('retained',$config->string('DEFAULT_ONLY'));
        self::assertSame('',$config->string('LOCAL_ONLY'));
        putenv('ARBK_CONFIG_TEST_VALUE=process');
        self::assertSame('process',Config::load($this->directory,[])->string('ARBK_CONFIG_TEST_VALUE'));
    }

    public function testMissingExplicitFileDoesNotFallBackToLocalFile(): void
    {
        file_put_contents($this->directory.'/.env',"ARBK_CONFIG_TEST_VALUE=local\n");
        putenv('ARBK_ENV_FILE='.$this->directory.'/missing.env');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing or unreadable');
        Config::load($this->directory,[]);
    }
}
