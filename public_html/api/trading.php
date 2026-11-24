<?php
declare(strict_types=1);
use Aurum\Engine;
use Aurum\Challenges;
use Aurum\MarketData\OkxFeed;
$private=getenv('AURUM_PRIVATE_PATH');
if(!$private){
    $candidate=dirname(__DIR__,2);
    if(is_file($candidate.'/backend/bootstrap.php'))$private=$candidate;
    else{$parent=dirname($candidate);$private=is_dir($parent.'/aurum-private')?$parent.'/aurum-private':$candidate.'/aurum-private';}
}
require_once rtrim($private,'/').'/backend/bootstrap.php';
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
if($method==='GET'&&!isset($_SESSION['uid'])){
    $publicRules=Challenges::current(aurum_storage());
    aurum_send(['csrf'=>$_SESSION['csrf'],'authenticated'=>false,'challengeRules'=>$publicRules]);
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
    if(in_array($action,['register','login'],true)){
        $email=strtolower(trim((string)($body['email']??'')));$password=$body['password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||!is_string($password)||strlen($password)<10||strlen($password)>72)aurum_send(['error'=>'Valid email and 10–72 byte password required'],400);
        $rateScope='rate-'.hash('sha256',$email);
        $allowed=$store->transaction($rateScope,function(array &$s){$now=time();$attempts=array_values(array_filter($s['attempts']??[],static fn($t)=>is_int($t)&&$t>$now-900));if(count($attempts)>=10){$s['attempts']=$attempts;return false;}$attempts[]=$now;$s['attempts']=$attempts;return true;});
        if(!$allowed)aurum_send(['error'=>'Too many authentication attempts. Try again in 15 minutes.'],429);
        $uid=hash('sha256',$email);$isAdmin=in_array($email,aurum_admin_emails(),true);$rules=Challenges::current($store);
        $store->transaction(aurum_scope($uid),function(array &$s)use($email,$password,$action,$body,$isAdmin,$rules){
            if($action==='register'){
                if(is_array($s['user']??null))throw new InvalidArgumentException('Unable to register');
                $s['user']=['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$isAdmin?'admin':'trader','createdAt'=>time()];
                $s['account']=Engine::account((int)($body['size']??100000),$rules);
            }elseif(!is_array($s['user']??null)||!password_verify($password,(string)($s['user']['hash']??'')))throw new InvalidArgumentException('Invalid credentials');
            if($isAdmin)$s['user']['role']='admin';
        });
        $store->transaction($rateScope,static function(array &$s){$s['attempts']=[];});
        session_regenerate_id(true);$_SESSION['uid']=$uid;$_SESSION['role']=$isAdmin?'admin':'trader';$_SESSION['csrf']=bin2hex(random_bytes(32));
        aurum_send(['authenticated'=>true,'role'=>$_SESSION['role'],'csrf'=>$_SESSION['csrf']]);
    }
    if(!isset($_SESSION['uid']))aurum_send(['error'=>'Sign in required'],401);
    if(!in_array($action,['state','open','close','pending','cancel-pending'],true))aurum_send(['error'=>'Unknown action'],400);
    $uid=(string)$_SESSION['uid'];$role=(string)($_SESSION['role']??'trader');$csrf=(string)$_SESSION['csrf'];session_write_close();
    $q=null;if(aurum_config('APP_ENV','staging')!=='test'){try{$q=Engine::quote((new OkxFeed())->ticker());}catch(Throwable $e){}}
    $result=$store->transaction(aurum_scope($uid),function(array &$s)use($q,$action,$body){
        if(!is_array($s['account']??null))throw new RuntimeException('Account unavailable');
        $a=&$s['account'];$error=null;$id=null;
        if($q!==null){try{Engine::mark($a,$q);}catch(RuntimeException $e){$q=null;}}
        if($action!=='state'){
            $key=$body['requestId']??'';
            if(!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key))$error='Invalid request ID';
            elseif(array_key_exists($key,$a['requests']))$id=$a['requests'][$key];
            elseif($q===null)$error='Feed unavailable or stale; order blocked';
            else{
                try{
                    if($action==='open')$id=Engine::open($a,$q,$body);
                    elseif($action==='close'){ $id=(string)($body['id']??'');Engine::close($a,$id,$q); }
                    elseif($action==='pending')$id=Engine::placePending($a,$q,$body);
                    else{$id=(string)($body['id']??'');Engine::cancelPending($a,$id);}
                    $a['requests'][$key]=$id;
                    if(count($a['requests'])>500)$a['requests']=array_slice($a['requests'],-400,null,true);
                    Engine::mark($a,$q);
                }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            }
        }
        $view=$a;unset($view['requests']);$view['margin']=Engine::margin($a);
        return ['account'=>$view,'feed'=>$q===null?'offline':'live','error'=>$error,'id'=>$id];
    });
    aurum_send($result+['authenticated'=>true,'role'=>$role,'csrf'=>$csrf],$result['error']===null?200:409);
}catch(InvalidArgumentException|JsonException $e){aurum_send(['error'=>'Invalid request or credentials'],400);}
catch(Throwable $e){error_log('Aurum API: '.get_class($e));aurum_send(['error'=>'Service unavailable'],503);}
