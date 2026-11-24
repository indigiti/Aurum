<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/Challenges.php';
require_once __DIR__.'/../backend/Engine.php';
require_once __DIR__.'/../backend/Storage.php';
require_once __DIR__.'/../backend/Analytics.php';
require_once __DIR__.'/../backend/Backtester.php';
use Aurum\Engine;
use Aurum\Backtester;
use Aurum\Analytics;
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$q=['bid'=>400000,'ask'=>400025,'ts'=>(int)(microtime(true)*1000)];
$a=Engine::account(100000);Engine::mark($a,$q);
Engine::open($a,$q,['side'=>'buy','units'=>100,'sl'=>3990,'tp'=>4020]);Engine::mark($a,$q);
check($a['equity']===$a['initial']-2500,'Spread mark');
$q['bid']=402000;$q['ask']=402025;$q['ts']++;Engine::mark($a,$q);
check(!$a['positions']&&$a['journal'][0]['reason']==='take-profit','Take profit');
check($a['balance']===$a['initial']+197500,'Long P&L');
Engine::open($a,$q,['side'=>'sell','units'=>100,'sl'=>4030]);$q['bid']=403000;$q['ask']=403025;$q['ts']++;Engine::mark($a,$q);
check(!$a['positions']&&$a['journal'][1]['reason']==='stop-loss','Short stop');
$b=Engine::account(5000);Engine::mark($b,$q);$failed=false;
try{Engine::open($b,$q,['side'=>'buy','units'=>1000]);}catch(InvalidArgumentException){$failed=true;}check($failed,'Margin rejection');
$c=Engine::account(100000);Engine::mark($c,$q);Engine::open($c,$q,['side'=>'buy','units'=>500]);$q['bid']-=2000;$q['ask']-=2000;$q['ts']++;Engine::mark($c,$q);
check($c['status']==='failed'&&!$c['positions'],'Risk liquidation');
$d=Engine::account(100000);$d['balance']=$d['initial']+800000;$d['days']=array_fill_keys(['a','b','c','d','e'],true);Engine::mark($d,$q);
check($d['phase']===2&&$d['balance']===$d['initial'],'Phase progression');
$rules=Challenges::validate(['dailyLossPct'=>4,'maxLossPct'=>8,'phaseTargets'=>[6,4],'minTradingDays'=>3,'leverage'=>20]);
$e=Engine::account(25000,$rules);check($e['rules']['dailyLossPct']===4.0,'Account rule snapshot');
$pending=Engine::account(100000);Engine::mark($pending,$q);
Engine::placePending($pending,$q,['type'=>'buy-stop','price'=>4100,'units'=>10]);
$q['bid']=410100;$q['ask']=410125;$q['ts']++;Engine::mark($pending,$q);
check(count($pending['positions'])===1&&count($pending['pending'])===0,'Pending stop activation');
$failed=false;try{Engine::quote(['bid'=>1,'ask'=>2,'timestamp'=>1,'stale'=>false]);}catch(RuntimeException){$failed=true;}check($failed,'Stale quotes rejected');
$summary=Analytics::summarize($a);check($summary['closedTrades']===2&&$summary['wins']===1,'Analytics');
$candles=[];for($i=0;$i<80;$i++){$v=4000+sin($i/5)*20+$i*.2;$candles[]=['time'=>1700000000+$i*60,'open'=>$v,'high'=>$v+2,'low'=>$v-2,'close'=>$v,'confirmed'=>true];}
$bt=Backtester::run($candles,5,12,10,.3,1,0);check($bt['barCount']===80&&isset($bt['netPnl']),'Backtest');
$dir=sys_get_temp_dir().'/aurum-test-'.bin2hex(random_bytes(8));$store=new Aurum\JsonStorage($dir);
$store->transaction('user-test',function(array &$s){$s['x']=42;});
check($store->transaction('user-test',fn(array &$s)=>$s['x'])===42,'Scoped persistence');
try{$store->transaction('user-test',function(array &$s){$s['x']=99;throw new RuntimeException('abort');});}catch(RuntimeException){}
check($store->transaction('user-test',fn(array &$s)=>$s['x'])===42,'Transaction rollback');
check(count($store->all('user-'))===1,'Storage enumeration');
foreach(glob($dir.'/*')?:[] as $path)unlink($path);rmdir($dir);
echo "Aurum engine, risk, pending, analytics, backtest and JSON tests passed\n";
