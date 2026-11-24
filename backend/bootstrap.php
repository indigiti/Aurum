<?php
declare(strict_types=1);
require_once __DIR__.'/Storage.php';
require_once __DIR__.'/Engine.php';
require_once __DIR__.'/MarketData/OkxFeed.php';
function aurum_storage(): \Aurum\Storage {
    $driver=getenv('STORAGE_DRIVER')?:'json';
    if($driver==='json'){
        if(getenv('APP_ENV')==='production')throw new RuntimeException('JSON is staging only');
        return new \Aurum\JsonStorage(dirname(__DIR__).'/private-storage');
    }
    if($driver!=='mariadb')throw new RuntimeException('Unknown storage driver');
    $dsn=getenv('DB_DSN');if(!$dsn||!str_starts_with($dsn,'mysql:'))throw new RuntimeException('MariaDB DSN required');
    return new \Aurum\MariaDbStorage(new PDO($dsn,getenv('DB_USER')?:'',getenv('DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]));
}
