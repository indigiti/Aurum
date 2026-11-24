<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/Engine.php';
require_once __DIR__.'/../backend/Storage.php';
use Aurum\Engine;
function check(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$q=['bid'=>400000,'ask'=>400025,'ts'=>(int)(microtime(true)*1000)];
$a=Engine::account(100000);Engine::mark($a,$q);
$id=Engine::open($a,$q,['side'=>'buy','units'=>100,'sl'=>3990,'tp'=>4020]);
Engine::mark($a,$q);check($a['equity']===$a['initial']-2500,'Spread mark');
$q['bid']=402000;$q['ask']=402025;$q['ts']++;
Engine::mark($a,$q);check(!$a['positions']&&$a['journal'][0]['reason']==='take-profit','TP');
check($a['balance']===$a['initial']+197500,'Long P&L');
$id=Engine::open($a,$q,['side'=>'sell','units'=>100,'sl'=>4030]);
$q['bid']=403000;$q['ask']=403025;$q['ts']++;
Engine::mark($a,$q);check(!$a['positions']&&$a['journal'][1]['reason']==='stop-loss','Short stop');
$b=Engine::account(5000);Engine::mark($b,$q);$failed=false;
try{Engine::open($b,$q,['side'=>'buy','units'=>1000]);}catch(InvalidArgumentException){$failed=true;}check($failed,'Margin rejection');
$c=Engine::account(100000);Engine::mark($c,$q);Engine::open($c,$q,['side'=>'buy','units'=>500]);$q['bid']-=2000;$q['ask']-=2000;$q['ts']++;Engine::mark($c,$q);check($c['status']==='failed'&&!$c['positions'],'Risk liquidation');
$d=Engine::account(100000);$d['balance']=$d['initial']+800000;$d['days']=array_fill_keys(['a','b','c','d','e'],true);Engine::mark($d,$q);check($d['phase']===2&&$d['balance']===$d['initial'],'Phase progression');
$failed=false;try{Engine::quote(['bid'=>1,'ask'=>2,'timestamp'=>1,'stale'=>false]);}catch(RuntimeException){$failed=true;}check($failed,'Stale quotes rejected');
$dir=sys_get_temp_dir().'/aurum-test-'.bin2hex(random_bytes(8));$store=new Aurum\JsonStorage($dir);$store->transaction(function(array &$s){$s['x']=42;});check($store->transaction(fn(array &$s)=>$s['x'])===42,'Persistence');
try{$store->transaction(function(array &$s){$s['x']=99;throw new RuntimeException('abort');});}catch(RuntimeException){}
check($store->transaction(fn(array &$s)=>$s['x'])===42,'Rollback');
unlink($dir.'/state.json');unlink($dir.'/state.lock');rmdir($dir);
echo "Engine and JSON storage tests passed\n";
