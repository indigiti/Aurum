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
    public static function requiresVerification(string $email): bool {
        return aurum_config('APP_ENV','staging')!=='test'&&(aurum_config('APP_ENV','staging')==='production'||filter_var(aurum_config('REQUIRE_EMAIL_VERIFICATION',false),FILTER_VALIDATE_BOOLEAN)||in_array($email,aurum_admin_emails(),true));
    }
    public static function admin(array $user): bool {
        return in_array($user['email']??'',aurum_admin_emails(),true)&&(aurum_config('APP_ENV','staging')==='test'||self::verified($user));
    }
    public static function send(string $email,string $code): bool {
        $from=(string)aurum_config('MAIL_FROM','');
        if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from)||!function_exists('mail'))return false;
        return mail($email,'Aurum email verification',"Your Aurum verification code is:\n\n".$code."\n\nEnter it together with your email and password in Aurum within 15 minutes. If you did not request it, ignore this email.",['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
    }
}
