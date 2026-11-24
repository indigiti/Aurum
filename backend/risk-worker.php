<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/RiskRunner.php';
$dir=aurum_private_root().'/private-storage';
if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))exit(1);
$lock=fopen($dir.'/risk-worker.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(0);
$loop=in_array('--loop',$argv,true);
$store=aurum_storage();
do{
    $status=['timestamp'=>time(),'mode'=>$loop?'polling-loop':'cron','status'=>'offline'];
    try{
        $q=\Aurum\Engine::quote((new \Aurum\MarketData\OkxFeed())->ticker());
        $status=array_merge($status,\Aurum\RiskRunner::sweep($store,$q),['quoteTs'=>$q['ts'],'status'=>'observed']);
    }catch(Throwable $e){error_log('Aurum worker: '.get_class($e));}
    $tmp=tempnam($dir,'worker-');
    if($tmp!==false){file_put_contents($tmp,json_encode($status,JSON_THROW_ON_ERROR),LOCK_EX);chmod($tmp,0600);rename($tmp,$dir.'/risk-worker-health.json');}
    if($loop)sleep(1);
}while($loop);
flock($lock,LOCK_UN);fclose($lock);
exit(($status['status']==='observed'&&($status['errors']??1)===0)?0:1);
