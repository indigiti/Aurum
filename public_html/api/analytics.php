<?php
declare(strict_types=1);
use Aurum\Analytics;
$private=getenv('AURUM_PRIVATE_PATH');
if(!$private){
    $candidate=dirname(__DIR__,2);
    if(is_file($candidate.'/backend/bootstrap.php'))$private=$candidate;
    else{$parent=dirname($candidate);$private=is_dir($parent.'/aurum-private')?$parent.'/aurum-private':$candidate.'/aurum-private';}
}
require_once rtrim($private,'/').'/backend/bootstrap.php';
require_once rtrim($private,'/').'/backend/Analytics.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode','1');session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict','path'=>dirname(dirname($_SERVER['SCRIPT_NAME'])) ?: '/']);session_start();
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo '{"error":"Method not allowed"}';exit;}
if(!isset($_SESSION['uid'])){http_response_code(401);echo '{"error":"Sign in required"}';exit;}
$uid=(string)$_SESSION['uid'];session_write_close();
try{$store=aurum_storage();$data=$store->transaction(aurum_scope($uid),function(array &$s){if(!is_array($s['account']??null))throw new RuntimeException();return ['analytics'=>Analytics::summarize($s['account']),'backtests'=>array_slice(array_reverse($s['account']['backtests']??[]),0,10)];});echo json_encode($data,JSON_THROW_ON_ERROR);}
catch(Throwable $e){http_response_code(503);echo '{"error":"Analytics unavailable"}';}
