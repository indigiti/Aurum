<?php
declare(strict_types=1);
namespace Aurum;
use InvalidArgumentException;
final class AuthService {
    public static function authenticate(Storage $store,IdentityPolicy $policy,callable $sendVerification,string $action,array $body,string $ip): array {
        $email=strtolower(trim((string)($body['email']??'')));$password=$body['password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||!is_string($password)||strlen($password)<10||strlen($password)>72)return ['status'=>400,'error'=>'Valid email and 10–72 byte password required'];
        $rateScope='rate-'.hash('sha256',$email);
        $allowed=$store->transaction($rateScope,function(array &$s){$now=time();$attempts=array_values(array_filter($s['attempts']??[],static fn($t)=>is_int($t)&&$t>$now-900));if(count($attempts)>=10){$s['attempts']=$attempts;return false;}$attempts[]=$now;$s['attempts']=$attempts;return true;});
        if(!$allowed)return ['status'=>429,'error'=>'Too many authentication attempts. Try again in 15 minutes.'];
        $uid=hash('sha256',$email);$rules=ChallengeSettings::current($store);
        $ipScope='rate-ip-'.hash('sha256',$ip);
        $allowedIp=$store->transaction($ipScope,static function(array &$s){$now=time();$attempts=array_values(array_filter($s['attempts']??[],static fn($t)=>is_int($t)&&$t>$now-900));if(count($attempts)>=60)return false;$attempts[]=$now;$s['attempts']=$attempts;return true;});
        if(!$allowedIp)return ['status'=>429,'error'=>'Too many requests'];
        $identity=$store->transaction(Accounts::scope($uid),function(array &$s)use($email,$password,$action,$body,$rules,$policy){
            if($action==='register'){
                if(is_array($s['user']??null))throw new InvalidArgumentException('Unable to register');
                $s['user']=['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'trader','createdAt'=>time()];
                $s['account']=Engine::account((int)($body['size']??100000),$rules);
            }elseif(!is_array($s['user']??null)||!password_verify($password,(string)($s['user']['hash']??'')))throw new InvalidArgumentException('Invalid credentials');
            $user=&$s['user'];
            if($policy->requiresVerification($email)&&!Identity::verified($user)){
                $code=$body['verificationCode']??'';
                if(!is_string($code))throw new InvalidArgumentException('Invalid code');
                if($code!==''){
                    if(!Identity::verify($user,trim($code),time()))throw new InvalidArgumentException('Invalid verification code');
                }else{
                    if(($user['verificationExpires']??0)>time()+840)return ['verify'=>true,'code'=>null];
                    return ['verify'=>true,'code'=>Identity::issue($user,time())];
                }
            }
            $user['role']=$policy->admin($user)?'admin':'trader';
            if(password_needs_rehash($user['hash'],PASSWORD_DEFAULT))$user['hash']=password_hash($password,PASSWORD_DEFAULT);
            return ['verify'=>false,'role'=>$user['role']];
        });
        if($identity['verify']){
            $sent=$identity['code']===null?null:$sendVerification($email,$identity['code']);
            return ['authenticated'=>false,'verificationRequired'=>true,'message'=>$sent===false?'Email delivery is unavailable. Contact the administrator.':'Enter the verification code sent to your email; use Sign in after entering it.'];
        }
        $isAdmin=$identity['role']==='admin';
        $store->transaction($rateScope,static function(array &$s){$s['attempts']=[];});
        return ['authenticated'=>true,'uid'=>$uid,'role'=>$isAdmin?'admin':'trader'];
    }
}
