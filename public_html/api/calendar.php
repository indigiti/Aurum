<?php
declare(strict_types=1);
require_once __DIR__.'/_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);exit;}
try{$c=\Aurum\NewsCalendar::current(aurum_storage());echo json_encode(['source'=>'administrator-maintained','validUntil'=>$c['validUntil'],'events'=>$c['events']],JSON_THROW_ON_ERROR);}catch(Throwable $e){http_response_code(503);echo '{"error":"Calendar unavailable"}';}
