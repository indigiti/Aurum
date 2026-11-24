<?php
declare(strict_types=1);
$root=dirname(__DIR__);
function checkLayer(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
foreach(glob($root.'/public_html/api/*.php') as $path){
    if(basename($path)==='_bootstrap.php')continue;
    $code=file_get_contents($path);
    $route=basename($path);
    checkLayer($code==="<?php\ndeclare(strict_types=1);\nrequire_once __DIR__.'/_bootstrap.php';\nrequire \$private.'/backend/Http/".$route."';\n",'Public route must only dispatch: '.$route);
    checkLayer(is_file($root.'/backend/Http/'.$route),'Private controller missing: '.$route);
}
foreach(glob($root.'/backend/Domain/*.php') as $path){
    $code=file_get_contents($path);
    checkLayer(!preg_match('/\b(?:Storage|JsonStorage|MariaDbStorage|PDO|OkxFeed|curl_init|file_get_contents|file_put_contents|fopen|mail|aurum_config|aurum_storage)\b|\$_(?:SESSION|SERVER|POST|GET|COOKIE)/',$code),'Domain I/O dependency: '.basename($path));
}
foreach(glob($root.'/backend/Application/*.php') as $path){
    $code=file_get_contents($path);
    checkLayer(!preg_match('/\b(?:JsonStorage|MariaDbStorage|PDO|OkxFeed|curl_init|file_get_contents|file_put_contents|fopen|mail|aurum_config|aurum_storage|aurum_admin_emails)\b|\$_(?:SESSION|SERVER|POST|GET|COOKIE)/',$code),'Application concrete I/O dependency: '.basename($path));
}
foreach(glob($root.'/frontend/application/*.ts') as $path){
    $code=file_get_contents($path);
    checkLayer(!preg_match('/(?:from\s+[\'"][^\'"]*(?:infrastructure|presentation|lightweight-charts|lucide-react)|\bfetch\s*\(|new\s+WebSocket\s*\()/',$code),'Frontend application bypasses ports: '.basename($path));
}
foreach(array_merge(glob($root.'/frontend/presentation/*.ts'),glob($root.'/frontend/presentation/*.tsx')) as $path){
    $code=file_get_contents($path);
    checkLayer(!preg_match('/(?:from\s+[\'"][^\'"]*infrastructure|\bfetch\s*\(|new\s+WebSocket\s*\()/',$code),'Presentation bypasses API port: '.basename($path));
}
foreach(glob($root.'/frontend/domain/*.ts') as $path){
    checkLayer(!preg_match('/\bimport\b|\bfetch\s*\(|new\s+WebSocket\s*\(/',file_get_contents($path)),'Frontend domain must not depend on framework or transport: '.basename($path));
}
echo "Layer boundary checks passed\n";
