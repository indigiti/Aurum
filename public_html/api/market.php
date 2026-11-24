<?php
declare(strict_types=1);
use Aurum\MarketData\OkxFeed;
require_once __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=2, stale-while-revalidate=3');
header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo '{"error":"Method not allowed"}';exit;}
try{
    $feed=new OkxFeed();$action=$_GET['action']??'ticker';
    if($action==='ticker')$data=$feed->ticker();
    elseif($action==='instrument')$data=$feed->instrument();
    elseif($action==='candles'){
        $bar=$_GET['bar']??'1m';$limit=filter_var($_GET['limit']??100,FILTER_VALIDATE_INT);
        if(!is_string($bar)||$limit===false)throw new InvalidArgumentException();
        $data=$feed->candles($bar,$limit);
    }else throw new InvalidArgumentException();
    echo json_encode(['symbol'=>OkxFeed::SYMBOL,'instrument'=>OkxFeed::INSTRUMENT,'quoteCurrency'=>'USDT','proxy'=>true,'data'=>$data],JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(400);echo '{"error":"Invalid market request"}';}
catch(Throwable $e){http_response_code(503);echo '{"error":"Market feed unavailable"}';}
