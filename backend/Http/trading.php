<?php
declare(strict_types=1);
use Aurum\ChallengeSettings;
use Aurum\TradeService;
use Aurum\RequestContext;
use Aurum\Engine;
use Aurum\Demo;
use Aurum\Identity;
use Aurum\Accounts;
use Aurum\NewsCalendar;
use Aurum\SimulatedPayout;
use Aurum\Challenges;
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>dirname(dirname($_SERVER['SCRIPT_NAME'])) ?: '/']);
session_start();
function aurum_send(array $data,int $code=200): never {http_response_code($code);echo json_encode($data,JSON_THROW_ON_ERROR);exit;}

$_SESSION['csrf']??=bin2hex(random_bytes(32));
$method=$_SERVER['REQUEST_METHOD'];
if(!in_array($method,['GET','POST'],true))aurum_send(['error'=>'Method not allowed'],405);
if($method==='GET'&&!isset($_SESSION['uid'])&&!Demo::active()){
    $publicRules=ChallengeSettings::current(aurum_storage());
    aurum_send(['csrf'=>$_SESSION['csrf'],'authenticated'=>false,'challengeRules'=>$publicRules,'demoEnabled'=>Demo::enabled(),'demo'=>false]);
}
try{
    $body=[];
    if($method==='POST'){
        if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))aurum_send(['error'=>'CSRF rejected'],403);
        if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)aurum_send(['error'=>'Request too large'],413);
        $body=json_decode((string)file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($body))aurum_send(['error'=>'Invalid body'],400);
    }
    $action=$method==='GET'?'state':($body['action']??'');
    if($action==='logout'){
        $_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']??'Strict']);}
        session_destroy();aurum_send(['ok'=>true]);
    }
    $store=aurum_storage();
    if(in_array($action,['start-demo','reset-demo'],true)){
        if(!Demo::enabled())aurum_send(['error'=>'Demo is disabled'],403);
        if(isset($_SESSION['uid']))aurum_send(['error'=>'Sign out before starting a demo'],409);
        if($action==='reset-demo'&&!Demo::active())aurum_send(['error'=>'No active demo'],401);
        if($action==='reset-demo'||!Demo::active()){
            if($action==='start-demo'){session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(32));}Demo::start();
        }
        $a=$_SESSION['demo']['account'];$view=$a;unset($view['requests']);$view['margin']=Engine::margin($a);
        aurum_send(['authenticated'=>true,'role'=>'demo','demo'=>true,'demoEnabled'=>true,'accountId'=>'primary','account'=>$view,'accounts'=>Demo::listing(),'csrf'=>$_SESSION['csrf'],'feed'=>'offline']);
    }
    if(Demo::active()&&!in_array($action,['register','login'],true)){
        Demo::guardAccount();
        if($action==='list-accounts')aurum_send(['accounts'=>Demo::listing()]);
        if(!in_array($action,['state','open','close','pending','cancel-pending','modify-protection','modify-pending'],true))aurum_send(['error'=>'Action unavailable in demo mode'],403);
        $q=null;
        if(aurum_config('APP_ENV','staging')==='test')$q=['bid'=>400000,'ask'=>400025,'ts'=>(int)(microtime(true)*1000),'newsStatus'=>'clear'];
        else{try{$q=aurum_market()->quote($store);}catch(Throwable $e){}}
        if($action!=='state'&&count($_SESSION['demo']['account']['requests'])>=300&&(!is_string($body['requestId']??null)||!array_key_exists($body['requestId'],$_SESSION['demo']['account']['requests'])))aurum_send(['error'=>'Demo trade limit reached; reset the demo to continue'],409);
        $result=TradeService::execute($_SESSION['demo'],$q,$action,$body);
        aurum_send($result+['authenticated'=>true,'role'=>'demo','demo'=>true,'demoEnabled'=>true,'csrf'=>$_SESSION['csrf'],'accountId'=>'primary'],$result['error']===null?200:409);
    }
    if(in_array($action,['register','login'],true)){
        $email=strtolower(trim((string)($body['email']??'')));$password=$body['password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||!is_string($password)||strlen($password)<10||strlen($password)>72)aurum_send(['error'=>'Valid email and 10–72 byte password required'],400);
        $rateScope='rate-'.hash('sha256',$email);
        $allowed=$store->transaction($rateScope,function(array &$s){$now=time();$attempts=array_values(array_filter($s['attempts']??[],static fn($t)=>is_int($t)&&$t>$now-900));if(count($attempts)>=10){$s['attempts']=$attempts;return false;}$attempts[]=$now;$s['attempts']=$attempts;return true;});
        if(!$allowed)aurum_send(['error'=>'Too many authentication attempts. Try again in 15 minutes.'],429);
        $uid=hash('sha256',$email);$rules=ChallengeSettings::current($store);
        $ipScope='rate-ip-'.hash('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'));
        $allowedIp=$store->transaction($ipScope,static function(array &$s){$now=time();$attempts=array_values(array_filter($s['attempts']??[],static fn($t)=>is_int($t)&&$t>$now-900));if(count($attempts)>=60)return false;$attempts[]=$now;$s['attempts']=$attempts;return true;});
        if(!$allowedIp)aurum_send(['error'=>'Too many requests'],429);
        $identity=$store->transaction(aurum_scope($uid),function(array &$s)use($email,$password,$action,$body,$rules){
            if($action==='register'){
                if(is_array($s['user']??null))throw new InvalidArgumentException('Unable to register');
                $s['user']=['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'trader','createdAt'=>time()];
                $s['account']=Engine::account((int)($body['size']??100000),$rules);
            }elseif(!is_array($s['user']??null)||!password_verify($password,(string)($s['user']['hash']??'')))throw new InvalidArgumentException('Invalid credentials');
            $user=&$s['user'];
            if(Identity::requiresVerification($email)&&!Identity::verified($user)){
                $code=$body['verificationCode']??'';
                if(!is_string($code))throw new InvalidArgumentException('Invalid code');
                if($code!==''){
                    if(!Identity::verify($user,trim($code),time()))throw new InvalidArgumentException('Invalid verification code');
                }else{
                    if(($user['verificationExpires']??0)>time()+840)return ['verify'=>true,'code'=>null];
                    return ['verify'=>true,'code'=>Identity::issue($user,time())];
                }
            }
            $user['role']=Identity::admin($user)?'admin':'trader';
            if(password_needs_rehash($user['hash'],PASSWORD_DEFAULT))$user['hash']=password_hash($password,PASSWORD_DEFAULT);
            return ['verify'=>false,'role'=>$user['role']];
        });
        if($identity['verify']){
            $sent=$identity['code']===null?null:aurum_send_verification($email,$identity['code']);
            aurum_send(['authenticated'=>false,'verificationRequired'=>true,'csrf'=>$_SESSION['csrf'],'message'=>$sent===false?'Email delivery is unavailable. Contact the administrator.':'Enter the verification code sent to your email; use Sign in after entering it.']);
        }
        $isAdmin=$identity['role']==='admin';
        $store->transaction($rateScope,static function(array &$s){$s['attempts']=[];});
        unset($_SESSION['demo']);session_regenerate_id(true);$_SESSION['uid']=$uid;$_SESSION['role']=$isAdmin?'admin':'trader';$_SESSION['csrf']=bin2hex(random_bytes(32));
        aurum_send(['authenticated'=>true,'role'=>$_SESSION['role'],'demo'=>false,'csrf'=>$_SESSION['csrf']]);
    }
    if(!isset($_SESSION['uid']))aurum_send(['error'=>'Sign in required'],401);
    if(!in_array($action,['state','open','close','pending','cancel-pending','modify-protection','modify-pending','create-account','list-accounts','request-simulated-payout'],true))aurum_send(['error'=>'Unknown action'],400);
    $uid=(string)$_SESSION['uid'];$csrf=(string)$_SESSION['csrf'];session_write_close();
    $identity=Accounts::authorize($store,$uid);$role=Identity::admin($identity)?'admin':'trader';
    $accountId=RequestContext::requestedId();
    if($action==='list-accounts')aurum_send(['accounts'=>Accounts::listing($store,$uid)]);
    if($action==='create-account'){
        $size=filter_var($body['size']??null,FILTER_VALIDATE_INT);if($size===false)throw new InvalidArgumentException('Invalid account size');
        $newId=Accounts::create($store,$uid,$identity['email'],$size,ChallengeSettings::current($store));
        aurum_send(['accounts'=>Accounts::listing($store,$uid),'createdAccountId'=>$newId]);
    }
    $q=null;if(aurum_config('APP_ENV','staging')!=='test'){try{$q=aurum_market()->quote($store);}catch(Throwable $e){}}
    $result=$store->transaction(Accounts::scope($uid,$accountId),static fn(array &$s)=>TradeService::execute($s,$q,$action,$body));
    aurum_send($result+['authenticated'=>true,'role'=>$role,'demo'=>false,'csrf'=>$csrf,'accountId'=>$accountId],$result['error']===null?200:409);
}catch(InvalidArgumentException|JsonException $e){aurum_send(['error'=>'Invalid request or credentials'],400);}
catch(Throwable $e){error_log('Aurum API: '.get_class($e));aurum_send(['error'=>'Service unavailable'],503);}
