<?php
declare(strict_types=1);
use Aurum\Engine;
use Aurum\MarketData\OkxFeed;
require_once dirname(__DIR__,2).'/backend/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>dirname(dirname($_SERVER['SCRIPT_NAME'])) ?: '/']);
session_start();
function send(array $data,int $code=200): never {http_response_code($code);echo json_encode($data,JSON_THROW_ON_ERROR);exit;}
$_SESSION['csrf']??=bin2hex(random_bytes(32));
$method=$_SERVER['REQUEST_METHOD'];
if(!in_array($method,['GET','POST'],true))send(['error'=>'Method not allowed'],405);
if($method==='GET'&&!isset($_SESSION['uid']))send(['csrf'=>$_SESSION['csrf'],'authenticated'=>false]);
try {
    $body=[];
    if($method==='POST'){
        if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))send(['error'=>'CSRF rejected'],403);
        if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)send(['error'=>'Request too large'],413);
        $body=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
        if(!is_array($body))send(['error'=>'Invalid body'],400);
    }
    $action=$method==='GET'?'state':($body['action']??'');
    $store=aurum_storage();
    if($action==='logout'){$_SESSION=[];session_destroy();send(['ok'=>true]);}
    if(in_array($action,['register','login'],true)){
        $email=strtolower(trim((string)($body['email']??'')));$password=$body['password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||!is_string($password)||strlen($password)<10||strlen($password)>72)send(['error'=>'Valid email and 10–72 byte password required'],400);
        $uid=$store->transaction(function(array &$s)use($email,$password,$action,$body){
            $id=hash('sha256',$email);
            if($action==='register'){
                if(isset($s['users'][$id]))throw new InvalidArgumentException('Unable to register');
                $a=Engine::account((int)($body['size']??100000));
                $s['users'][$id]=['email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT)];
                $s['accounts'][$id]=$a;
            }elseif(!isset($s['users'][$id])||!password_verify($password,$s['users'][$id]['hash']))throw new InvalidArgumentException('Invalid credentials');
            return $id;
        });
        session_regenerate_id(true);$_SESSION['uid']=$uid;$_SESSION['csrf']=bin2hex(random_bytes(32));
        send(['authenticated'=>true,'csrf'=>$_SESSION['csrf']]);
    }
    if(!isset($_SESSION['uid']))send(['error'=>'Sign in required'],401);
    if(!in_array($action,['state','open','close'],true))send(['error'=>'Unknown action'],400);
    $uid=$_SESSION['uid'];$csrf=$_SESSION['csrf'];session_write_close();
    $q=null;try{$q=Engine::quote((new OkxFeed())->ticker());}catch(Throwable $e){}
    $result=$store->transaction(function(array &$s)use($uid,$q,$action,$body){
        if(!isset($s['accounts'][$uid]))throw new RuntimeException('Account unavailable');
        $a=&$s['accounts'][$uid];$error=null;$id=null;
        if($q!==null){try{Engine::mark($a,$q);}catch(RuntimeException $e){$q=null;}}
        if($action!=='state'){
            $key=$body['requestId']??'';
            if(!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key))$error='Invalid request ID';
            elseif(isset($a['requests'][$key])){$id=$a['requests'][$key];}
            elseif($q===null)$error='Feed unavailable or stale; order blocked';
            else {
                try {
                    if($action==='open')$id=Engine::open($a,$q,$body);
                    else{$id=(string)($body['id']??'');Engine::close($a,$id,$q);}
                    $a['requests'][$key]=$id;Engine::mark($a,$q);
                }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            }
        }
        $view=$a;unset($view['requests']);$view['margin']=Engine::margin($a);
        return ['account'=>$view,'feed'=>$q===null?'offline':'live','error'=>$error,'id'=>$id];
    });
    send($result+['authenticated'=>true,'csrf'=>$csrf],$result['error']===null?200:409);
}catch(InvalidArgumentException|JsonException $e){send(['error'=>'Invalid request or credentials'],400);}
catch(Throwable $e){error_log('Aurum API: '.get_class($e));send(['error'=>'Service unavailable'],503);}
