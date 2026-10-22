<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/** Every administrative page must refuse business portal accounts (bug B-02). */
final class AdminGuardTest extends TestCase
{
    public function testEveryAdminEndpointRequiresAStaffRole(): void
    {
        $root=dirname(__DIR__).'/public/admin';
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        $checked=0;
        foreach($files as $file){
            if($file->getExtension()!=='php')continue;
            $source=(string)file_get_contents($file->getPathname());
            $name=substr($file->getPathname(),strlen($root));
            self::assertStringNotContainsString('->requireUser()',$source,"$name must not accept any logged-in user.");
            self::assertMatchesRegularExpression('/->require(Staff|Editor|Role\(\[\'ADMIN\'\]\))\(\)|->requireRole\(\[\'ADMIN\'\]\)/',$source,"$name has no staff guard.");
            $checked++;
        }
        self::assertGreaterThan(30,$checked);
    }

    public function testWriteEndpointsRequirePostAndCsrf(): void
    {
        foreach(glob(dirname(__DIR__).'/public/admin/*/*.php')?:[] as $path){
            $source=(string)file_get_contents($path);
            if(!str_contains($source,'run_action(')&&!str_contains($source,'$_POST'))continue;
            if(str_contains($source,'$_SERVER[\'REQUEST_METHOD\']===\'POST\'')&&str_contains($source,'require_post()'))continue;
            self::assertMatchesRegularExpression('/require_post\(\)|->verify\(/',$source,basename(dirname($path)).'/'.basename($path).' changes data without CSRF verification.');
        }
    }
}
