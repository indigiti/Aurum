<?php
declare(strict_types=1);
namespace Aurum;
final class Identity {
    public static function issue(array &$user,int $now): string {
        $code=bin2hex(random_bytes(16));
        $user['verificationHash']=hash('sha256',$code);
        $user['verificationExpires']=$now+900;
        return $code;
    }
    public static function verify(array &$user,string $code,int $now): bool {
        if(!preg_match('/^[a-f0-9]{32}$/D',$code)||($user['verificationExpires']??0)<$now||!isset($user['verificationHash'])||!hash_equals($user['verificationHash'],hash('sha256',$code)))return false;
        $user['emailVerifiedAt']=$now;unset($user['verificationHash'],$user['verificationExpires']);return true;
    }
    public static function verified(array $user): bool { return (int)($user['emailVerifiedAt']??0)>0; }
}
