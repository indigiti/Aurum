<?php
declare(strict_types=1);
// DigiOps installs the public API at public_html/aurum/api/ and private
// code at private_html/aurum/. No shell or environment override required.
$override=getenv('AURUM_PRIVATE_PATH');
$appHome=dirname(__DIR__,3);
$candidates=$override!==false&&$override!==''?[$override]:[
    $appHome.'/private_html/aurum',
    $appHome.'/aurum-private',
    dirname(__DIR__,2), // repository checkout for local development only
];
$private=null;
foreach($candidates as $candidate){
    $resolved=realpath($candidate);
    if($resolved!==false&&is_file($resolved.'/backend/bootstrap.php')){
        $private=$resolved;break;
    }
}
if($private===null){
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo '{"error":"Aurum private runtime unavailable"}';
    exit;
}
require_once $private.'/backend/bootstrap.php';
