<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/Storage.php';
require_once __DIR__.'/../backend/Challenges.php';
require_once __DIR__.'/../backend/Engine.php';
require_once __DIR__.'/../backend/Identity.php';
require_once __DIR__.'/../backend/RiskRunner.php';
require_once __DIR__.'/../backend/Accounts.php';
require_once __DIR__.'/../backend/NewsCalendar.php';
require_once __DIR__.'/../backend/SimulatedPayout.php';
require_once __DIR__.'/../backend/Backtester.php';
require_once __DIR__.'/../backend/AiReview.php';
require_once __DIR__.'/../backend/Domain/Exposure.php';
require_once __DIR__.'/../backend/Domain/TradingTools.php';
require_once __DIR__.'/../backend/Application/TradeService.php';
function ck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$user=[];$code=\Aurum\Identity::issue($user,1000);
ck(!\Aurum\Identity::verify($user,str_repeat('a',32),1001),'Wrong code rejected');
ck(!\Aurum\Identity::verify($user,$code,2000),'Expired code rejected');
ck(\Aurum\Identity::verify($user,$code,1001),'Valid email code');
ck(!\Aurum\Identity::verify($user,$code,1002),'Code cannot be replayed');
$dir=sys_get_temp_dir().'/aurum-v2-'.bin2hex(random_bytes(8));
$store=new \Aurum\JsonStorage($dir);$q=['bid'=>400000,'ask'=>400025,'ts'=>(int)(microtime(true)*1000)];
$store->transaction('user-risk',function(array &$s)use($q){$s['account']=\Aurum\Engine::account(100000);\Aurum\Engine::mark($s['account'],$q);\Aurum\Engine::open($s['account'],$q,['side'=>'buy','units'=>500]);});
$q['bid']-=2000;$q['ask']-=2000;$q['ts']++;
$r=\Aurum\RiskRunner::sweep($store,$q);ck($r['failed']===1,'Background worker fails breached account');
ck($store->transaction('user-risk',static fn(array &$s)=>$s['account']['status'])==='failed','Worker state persisted');
$uid=str_repeat('a',64);$other=str_repeat('b',64);
$store->transaction(\Aurum\Accounts::scope($uid),static function(array &$s){$s['account']=\Aurum\Engine::account(5000);});
$id=\Aurum\Accounts::create($store,$uid,'trader@example.test',10000,\Aurum\Challenges::defaults());ck($id==='a1','Second challenge created');
ck(count(\Aurum\Accounts::listing($store,$uid))===2,'Independent account listing');
ck(count(\Aurum\Accounts::listing($store,$other))===0,'Other trader isolation');
$now=time();$calendar=\Aurum\NewsCalendar::validate(['validUntil'=>$now+3600,'events'=>[['title'=>'Test event','timestamp'=>$now,'beforeMinutes'=>5,'afterMinutes'=>5]]]);
$q=\Aurum\NewsCalendar::decorate(['bid'=>400000,'ask'=>400025,'ts'=>$now*1000],$calendar);
ck($q['newsStatus']==='restricted','News window');
$restricted=\Aurum\Engine::account(100000,array_merge(\Aurum\Challenges::defaults(),['newsRestricted'=>true]));
$blocked=false;try{\Aurum\Engine::open($restricted,$q,['side'=>'buy','units'=>1]);}catch(InvalidArgumentException){$blocked=true;}
ck($blocked,'Restricted account cannot open exposure');
$funded=\Aurum\Engine::account(100000);$funded['phase']=3;\Aurum\Engine::book($funded,100000,'test-profit',$now*1000);$funded['equity']=$funded['balance'];
$pid=\Aurum\SimulatedPayout::request($funded,50000,$now*1000);\Aurum\SimulatedPayout::review($funded,$pid,true,$now*1000);
ck($funded['balance']===10050000&&$funded['payouts'][0]['traderShare']===40000,'Virtual payout ledger and split');
ck(\Aurum\AiReview::extract(['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Example review']]]]])==='Example review','AI response parsing');
$rows=[];for($i=0;$i<300;$i++){$v=4000+sin($i/7)*30;$rows[]=['time'=>1700000000+$i*60,'open'=>$v,'high'=>$v+1,'low'=>$v-1,'close'=>$v,'confirmed'=>true];}
$research=\Aurum\Backtester::optimize($rows,10,.3,1,0,'EMA','both');ck(count($research['candidates'])===5&&$research['holdoutBars']===105,'Chronological research split');
foreach(glob($dir.'/*')?:[] as $p)unlink($p);rmdir($dir);
echo "Identity and background risk tests passed\n";

$book=\Aurum\Engine::account(100000);$q=['bid'=>400000,'ask'=>400025,'ts'=>(int)(microtime(true)*1000)];
\Aurum\Engine::mark($book,$q);
$b1=\Aurum\Engine::open($book,$q,['side'=>'buy','units'=>10]);$q['ts']++;
$b2=\Aurum\Engine::open($book,$q,['side'=>'buy','units'=>20]);$q['ts']++;
$s1=\Aurum\Engine::open($book,$q,['side'=>'sell','units'=>25]);\Aurum\Engine::mark($book,$q);
$ex=\Aurum\Exposure::summarize($book,$q);ck($ex['buyUnits']===30&&$ex['sellUnits']===25&&$ex['netUnits']===5&&$ex['grossUnits']===55,'Exposure math');
ck($ex['pnl']===$book['equity']-$book['balance']&&$ex['matchedPnl']===-1250,'Bid ask P&L');
$old=$book;$bad=false;try{\Aurum\Exposure::close($book,$q,'matched',[],str_repeat('0',64));}catch(InvalidArgumentException){$bad=true;}
ck($bad&&$book===$old,'Stale plan rejected without partial mutation');
$state=['account'=>$book];$body=['mode'=>'matched','snapshot'=>$ex['snapshot'],'requestId'=>'matched-close-test-1234'];
$r=\Aurum\TradeService::execute($state,$q,'bulk-close',$body);ck($r['error']===null,'Matched close API service');
$book=$state['account'];ck(count($book['positions'])===1&&$book['positions'][$b2]['units']===5,'FIFO partial matching retains net');
ck($book['balance']===10000000-1250&&array_sum(array_column($book['ledger'],'delta'))===$book['balance'],'Matched ledger reconciliation');
ck($r['account']['exposure']['netUnits']===5&&$r['account']['exposure']['matchedUnits']===0,'Refreshed exposure');
$r2=\Aurum\TradeService::execute($state,$q,'bulk-close',$body);ck($r2['error']===null&&$state['account']['balance']===$book['balance'],'Idempotent matched close');
\Aurum\Engine::placePending($state['account'],$q,['type'=>'buy-limit','price'=>3990,'units'=>1]);
$snap=\Aurum\Exposure::snapshot($state['account']);$r=\Aurum\TradeService::execute($state,$q,'bulk-close',['mode'=>'flatten','snapshot'=>$snap,'requestId'=>'flatten-test-12345678']);
ck($r['error']===null&&!$state['account']['positions']&&!$state['account']['pending'],'Flatten closes positions and cancels pending');
echo "Exposure, matched closing, stale snapshot and flatten tests passed\n";

require_once __DIR__.'/../backend/Domain/TradingTools.php';
$toolsBook=\Aurum\Engine::account(100000);$q=['bid'=>400000,'ask'=>400025,'ts'=>(int)(microtime(true)*1000)];
\Aurum\Engine::mark($toolsBook,$q);$pid=\Aurum\Engine::open($toolsBook,$q,['side'=>'buy','units'=>3]);$q['bid']+=100;$q['ask']+=100;
\Aurum\TradingTools::breakEven($toolsBook,$q,[$pid]);ck($toolsBook['positions'][$pid]['sl']===400025,'Break even at entry');
\Aurum\TradingTools::basket($toolsBook,$q,[$pid],['tp'=>4010]);ck($toolsBook['positions'][$pid]['tp']===401000,'Basket target');
$alert=\Aurum\TradingTools::alert($toolsBook,['direction'=>'above','price'=>4001],$q);\Aurum\TradingTools::observeAlerts($toolsBook,$q);ck($toolsBook['alerts'][$alert]['status']==='triggered','Alert observes executable bid');
echo "Trading tools tests passed\n";

$bid=\Aurum\TradingTools::saveBasket($toolsBook,['name'=>'Test basket','ids'=>[$pid],'snapshot'=>\Aurum\Exposure::snapshot($toolsBook)]);
ck($toolsBook['baskets'][$bid]['ids']===[$pid],'Saved basket retains selected positions');\Aurum\TradingTools::deleteBasket($toolsBook,$bid);ck(empty($toolsBook['baskets']),'Deleting basket preserves positions');ck(isset($toolsBook['positions'][$pid]),'Basket delete does not close trade');
$wide=array_map(static function($r){$r['high']+=100;$r['low']-=100;return $r;},$rows);
$stops=\Aurum\Backtester::run($wide,5,15,1,0,0,0,'SMA','both',1,1);
ck($stops['trades']>0&&count(array_filter($stops['tradeList'],static fn($r)=>$r['reason']==='stop-distance'))>0&&!in_array('target-distance',array_column($stops['tradeList'],'reason'),true),'Ambiguous intrabar stop first');
$dir2=sys_get_temp_dir().'/aurum-alert-'.bin2hex(random_bytes(8));$workerStore=new \Aurum\JsonStorage($dir2);
$workerStore->transaction('user-alert',static function(array &$s)use($q){$s['account']=\Aurum\Engine::account(5000);$s['account']['status']='failed';\Aurum\TradingTools::alert($s['account'],['direction'=>'above','price'=>3999],$q);});
$q['ts']++;$sweep=\Aurum\RiskRunner::sweep($workerStore,$q);
ck($sweep['errors']===0&&$workerStore->transaction('user-alert',static fn(array &$s)=>array_values($s['account']['alerts'])[0]['status'])==='triggered','Worker observes registered alerts independently of browser and challenge state');
foreach(glob($dir2.'/*')?:[] as $f)unlink($f);rmdir($dir2);
echo "Saved baskets, strategy protection and unattended alert tests passed\n";
