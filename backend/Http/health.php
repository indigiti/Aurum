<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);header('Allow: GET');echo '{"status":"method-not-allowed"}';exit;}
try{
    $storage=aurum_storage();
    $storage->transaction('health-probe',static fn(array &$s)=>true);
    $manifestFile=aurum_private_root().'/build/release.json';
    $manifest=is_file($manifestFile)?json_decode((string)file_get_contents($manifestFile),true,16,JSON_THROW_ON_ERROR):[];
    echo json_encode(['status'=>'ok','app'=>'Aurum','sourceSha'=>$manifest['sourceSha']??null,'storageDriver'=>aurum_config('STORAGE_DRIVER','json')],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(503);
    error_log('Aurum health: '.get_class($e));
    echo '{"status":"unavailable","app":"Aurum"}';
}
