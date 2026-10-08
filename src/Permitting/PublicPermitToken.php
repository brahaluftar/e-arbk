<?php
declare(strict_types=1);
namespace App\Permitting;
use InvalidArgumentException;
final class PublicPermitToken
{
    /** @return array{token:string,selector:string,hash:string} */
    public function issue():array{$selector=bin2hex(random_bytes(8));$secret=bin2hex(random_bytes(32));return ['token'=>$selector.'.'.$secret,'selector'=>$selector,'hash'=>hash('sha256',$secret)];}
    /** @return array{selector:string,hash:string} */
    public function parse(string $token):array{if(preg_match('/\A([a-f0-9]{16})\.([a-f0-9]{64})\z/',trim($token),$m)!==1)throw new InvalidArgumentException('Kodi i verifikimit nuk është valid.');return ['selector'=>$m[1],'hash'=>hash('sha256',$m[2])];}
}
