<?php
declare(strict_types=1);
use Aurum\Challenges;
use Aurum\Engine;
use Aurum\Identity;
use Aurum\NewsCalendar;
use Aurum\MarketData\OkxFeed;
require_once __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>dirname(dirname($_SERVER['SCRIPT_NAME'])) ?: '/']);session_start();
function admin_send(array $d,int $c=200):never{http_response_code($c);echo json_encode($d,JSON_THROW_ON_ERROR);exit;}
if(!isset($_SESSION['uid']))admin_send(['error'=>'Sign in required'],401);
$uid=(string)$_SESSION['uid'];$csrf=(string)($_SESSION['csrf']??'');session_write_close();
try{
    $store=aurum_storage();$self=$store->transaction(aurum_scope($uid),static fn(array &$s)=>$s['user']??null);
    $email=strtolower((string)($self['email']??''));
    if(!is_array($self)||!Identity::admin($self))admin_send(['error'=>'Administrator access required'],403);
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $users=[];
        foreach($store->all('user-') as $scope=>$state){
            $u=$state['user']??(isset($state['ownerEmail'])?['email'=>$state['ownerEmail']]:null);$a=$state['account']??null;if(!is_array($u)||!is_array($a))continue;
            $users[]=['id'=>substr($scope,5),'email'=>$u['email'],'createdAt'=>$u['createdAt']??null,'initial'=>$a['initial'],'balance'=>$a['balance'],'equity'=>$a['equity'],'phase'=>$a['phase'],'status'=>$a['status'],'positions'=>count($a['positions']??[]),'pending'=>count($a['pending']??[]),'trades'=>count($a['journal']??[]),'riskEvents'=>array_slice(array_reverse($a['riskEvents']??[]),0,5)];
        }
        $rules=Challenges::current($store);
        $driver=(string)aurum_config('STORAGE_DRIVER','json');$database='not-required';
        if($driver==='mariadb'){try{$store->transaction('health-probe',static fn(array &$s)=>true);$database='connected';}catch(Throwable $e){$database='error';}}
        $market='offline';if(aurum_config('APP_ENV','staging')!=='test'){try{$quote=(new OkxFeed())->ticker();$market=($quote['stale']??true)?'stale':'live';}catch(Throwable $e){}}
        $workerFile=aurum_private_root().'/private-storage/risk-worker-health.json';$worker=is_file($workerFile)?json_decode((string)file_get_contents($workerFile),true):null;
        $workerStatus=is_array($worker)&&time()-(int)($worker['timestamp']??0)<=15&&($worker['status']??'')==='observed'&&($worker['errors']??1)===0?'recent-observation':'not-running-or-stale';
        $system=['riskWorker'=>$workerStatus,'environment'=>aurum_config('APP_ENV','staging'),'storageDriver'=>$driver,'database'=>$database,'phpVersion'=>PHP_VERSION,'marketFeed'=>$market,'marketInstrument'=>OkxFeed::INSTRUMENT];
        admin_send(['users'=>$users,'rules'=>$rules,'calendar'=>NewsCalendar::current($store),'system'=>$system,'counts'=>['users'=>count($users),'active'=>count(array_filter($users,static fn($u)=>$u['status']==='active')),'failed'=>count(array_filter($users,static fn($u)=>$u['status']==='failed'))]]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST')admin_send(['error'=>'Method not allowed'],405);
    if(!hash_equals($csrf,$_SERVER['HTTP_X_CSRF_TOKEN']??''))admin_send(['error'=>'CSRF rejected'],403);
    if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)admin_send(['error'=>'Request too large'],413);
    $body=json_decode((string)file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);if(!is_array($body))throw new InvalidArgumentException();
    $action=$body['action']??'';
    if($action==='update-calendar'){
        if(!is_array($body['calendar']??null))throw new InvalidArgumentException('Calendar required');
        $calendar=NewsCalendar::validate($body['calendar']);
        $store->transaction('config-calendar',static function(array &$s)use($calendar){$s['calendar']=$calendar;});
        admin_send(['ok'=>true,'calendar'=>$calendar]);
    }
    if($action==='update-rules'){
        $rules=Challenges::validate($body['rules']??[]);
        $store->transaction('config-challenges',function(array &$s)use($rules){$rules['version']=(int)($s['rules']['version']??0)+1;$s['rules']=$rules;});
        admin_send(['ok'=>true,'rules'=>$rules]);
    }
    $target=$body['userId']??'';if(!is_string($target)||!preg_match('/^[a-f0-9]{64}(?:-a[1-4])?$/D',$target))throw new InvalidArgumentException();
    $result=$store->transaction(aurum_scope($target),function(array &$s)use($action,$email){
        if(!is_array($s['account']??null))throw new InvalidArgumentException('Account not found');
        $a=&$s['account'];$before=$a['status'];
        if($action==='lock'&&$a['status']==='active'){$a['status']='locked';$a['pending']=[];}
        elseif($action==='unlock'&&$a['status']==='locked')$a['status']='active';
        elseif($action==='reset'){
            if($a['positions']||$a['pending'])throw new InvalidArgumentException('Close positions and cancel pending orders before reset');
            Engine::ensureLedger($a);Engine::book($a,$a['initial']-$a['balance'],'admin-capital-reset',(int)(microtime(true)*1000));
            $old=$a;$dollars=(int)($a['initial']/100);$rules=$a['rules']??Challenges::defaults();$a=Engine::account($dollars,$rules);
            foreach(['ledger','journal','riskEvents','phaseHistory','requests'] as $key)$a[$key]=$old[$key]??[];
        }else throw new InvalidArgumentException('Action not permitted for current status');
        $a['riskEvents'][]=['type'=>'admin-'.$action,'admin'=>$email,'previousStatus'=>$before,'timestamp'=>(int)(microtime(true)*1000)];
        return ['status'=>$a['status']];
    });
    admin_send(['ok'=>true]+$result);
}catch(InvalidArgumentException|JsonException $e){admin_send(['error'=>$e->getMessage()?:'Invalid request'],400);}
catch(Throwable $e){error_log('Aurum admin: '.get_class($e));admin_send(['error'=>'Admin operation unavailable'],503);}
