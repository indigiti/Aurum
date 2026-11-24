<?php
declare(strict_types=1);
use Aurum\Backtester;
use Aurum\MarketData\OkxFeed;
use Aurum\Accounts;
require_once __DIR__.'/_bootstrap.php';
require_once rtrim($private,'/').'/backend/Backtester.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>dirname(dirname($_SERVER['SCRIPT_NAME'])) ?: '/']);session_start();
function backtest_send(array $d,int $c=200):never{http_response_code($c);echo json_encode($d,JSON_THROW_ON_ERROR);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')backtest_send(['error'=>'Method not allowed'],405);
if(!isset($_SESSION['uid']))backtest_send(['error'=>'Sign in required'],401);
if(!hash_equals((string)($_SESSION['csrf']??''),$_SERVER['HTTP_X_CSRF_TOKEN']??''))backtest_send(['error'=>'CSRF rejected'],403);
if((int)($_SERVER['CONTENT_LENGTH']??0)>8192)backtest_send(['error'=>'Request too large'],413);
$uid=(string)$_SESSION['uid'];session_write_close();
try{
    $body=json_decode((string)file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);
    if(!is_array($body))throw new InvalidArgumentException();
    $bar=$body['bar']??'15m';if(!in_array($bar,['1m','5m','15m','30m','1H','4H','1Dutc'],true))throw new InvalidArgumentException();
    $fast=filter_var($body['fast']??9,FILTER_VALIDATE_INT);$slow=filter_var($body['slow']??21,FILTER_VALIDATE_INT);$units=filter_var($body['units']??10,FILTER_VALIDATE_INT);
    $spread=filter_var($body['spread']??0.30,FILTER_VALIDATE_FLOAT);$slippage=filter_var($body['slippageBps']??1,FILTER_VALIDATE_FLOAT);$fee=filter_var($body['feePerLot']??0,FILTER_VALIDATE_FLOAT);
    if($fast===false||$slow===false||$units===false||$spread===false||$slippage===false||$fee===false)throw new InvalidArgumentException();
    $store=aurum_storage();Accounts::authorize($store,$uid);$accountId=Accounts::requestedId();
    $method=$body['method']??'SMA';$mode=$body['mode']??'long';if(!is_string($method)||!is_string($mode))throw new InvalidArgumentException('Invalid strategy');
    $candles=(new OkxFeed())->candles($bar,300);
    if(($body['research']??false)===true){backtest_send(['research'=>Backtester::optimize($candles,$units,$spread,$slippage,$fee,$method,$mode)]);}
    $result=Backtester::run($candles,$fast,$slow,$units,$spread,$slippage,$fee,$method,$mode);
    $id=bin2hex(random_bytes(10));
    $store->transaction(Accounts::scope($uid,$accountId),function(array &$s)use($id,$result,$bar){if(!is_array($s['account']??null))throw new RuntimeException();$s['account']['backtests'][]=['id'=>$id,'bar'=>$bar,'createdAt'=>time(),'result'=>$result];if(count($s['account']['backtests'])>20)$s['account']['backtests']=array_slice($s['account']['backtests'],-20);});
    backtest_send(['id'=>$id,'bar'=>$bar,'result'=>$result]);
}catch(InvalidArgumentException|JsonException $e){backtest_send(['error'=>$e->getMessage()?:'Invalid backtest request'],400);}
catch(Throwable $e){error_log('Aurum backtest: '.get_class($e));backtest_send(['error'=>'Backtest data unavailable'],503);}
