<?php
declare(strict_types=1);
require_once __DIR__.'/../backend/Storage.php';
require_once __DIR__.'/../backend/Challenges.php';
require_once __DIR__.'/../backend/Engine.php';
require_once __DIR__.'/../backend/Identity.php';
require_once __DIR__.'/../backend/RiskRunner.php';
require_once __DIR__.'/../backend/Accounts.php';
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
foreach(glob($dir.'/*')?:[] as $p)unlink($p);rmdir($dir);
echo "Identity and background risk tests passed\n";
