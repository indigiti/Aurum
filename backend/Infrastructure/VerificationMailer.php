<?php
declare(strict_types=1);
namespace Aurum;
final class VerificationMailer {
    public static function send(string $email,string $code): bool {
        $from=(string)aurum_config('MAIL_FROM','');
        if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from)||!function_exists('mail'))return false;
        return mail($email,'Aurum email verification',"Your Aurum verification code is:\n\n".$code."\n\nEnter it together with your email and password in Aurum within 15 minutes. If you did not request it, ignore this email.",['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
    }
}
