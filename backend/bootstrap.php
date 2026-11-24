<?php
declare(strict_types=1);
require_once __DIR__.'/Storage.php';
require_once __DIR__.'/Engine.php';
require_once __DIR__.'/Domain/Exposure.php';
require_once __DIR__.'/Domain/TradingTools.php';
require_once __DIR__.'/Identity.php';
require_once __DIR__.'/Accounts.php';
require_once __DIR__.'/Demo.php';
require_once __DIR__.'/NewsCalendar.php';
require_once __DIR__.'/SimulatedPayout.php';
require_once __DIR__.'/Challenges.php';
require_once __DIR__.'/Analytics.php';
require_once __DIR__.'/Backtester.php';
require_once __DIR__.'/MarketData/OkxFeed.php';
require_once __DIR__.'/Application/ChallengeSettings.php';
require_once __DIR__.'/Application/CalendarSettings.php';
require_once __DIR__.'/Application/MarketService.php';
require_once __DIR__.'/Application/TradeService.php';
require_once __DIR__.'/Application/IdentityPolicy.php';
require_once __DIR__.'/Application/AuthService.php';
require_once __DIR__.'/Application/AdminService.php';
require_once __DIR__.'/Application/ReviewMetrics.php';
require_once __DIR__.'/Infrastructure/VerificationMailer.php';
require_once __DIR__.'/Infrastructure/AiReview.php';
require_once __DIR__.'/Http/RequestContext.php';
function aurum_private_root(): string {
    $root=getenv('AURUM_PRIVATE_PATH')?:dirname(__DIR__);
    $real=realpath($root);
    if($real===false)throw new RuntimeException('Private application directory unavailable');
    return $real;
}
function aurum_config(string $key,mixed $default=null): mixed {
    static $local=null;
    if($local===null){
        $path=aurum_private_root().'/config/local.php';
        $local=is_file($path)?require $path:[];
        if(!is_array($local))throw new RuntimeException('Invalid private configuration');
    }
    $value=getenv($key);
    return $value!==false&&$value!==''?$value:($local[$key]??$default);
}
function aurum_storage(): \Aurum\Storage {
    $driver=(string)aurum_config('STORAGE_DRIVER','json');
    if($driver==='json'){
        if(aurum_config('APP_ENV','staging')==='production')throw new RuntimeException('JSON is staging only');
        return new \Aurum\JsonStorage(aurum_private_root().'/private-storage');
    }
    if($driver!=='mariadb')throw new RuntimeException('Unknown storage driver');
    $dsn=aurum_config('DB_DSN');
    if(!$dsn||!str_starts_with((string)$dsn,'mysql:'))throw new RuntimeException('MariaDB DSN required');
    return new \Aurum\MariaDbStorage(new PDO((string)$dsn,(string)aurum_config('DB_USER',''),(string)aurum_config('DB_PASSWORD',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]));
}
function aurum_scope(string $uid): string { return 'user-'.$uid; }
function aurum_admin_emails(): array {
    return array_values(array_filter(array_map(static fn($v)=>strtolower(trim($v)),explode(',',(string)aurum_config('AURUM_ADMIN_EMAILS','')))));
}

function aurum_market(): \Aurum\MarketService {return new \Aurum\MarketService(new \Aurum\MarketData\OkxFeed());}
function aurum_send_verification(string $email,string $code): bool {return \Aurum\VerificationMailer::send($email,$code);}
function aurum_review_enabled(): bool {return \Aurum\AiReview::enabled();}
function aurum_review(array $metrics): string {return \Aurum\AiReview::review($metrics);}

function aurum_identity_policy(): \Aurum\IdentityPolicy {
    return new \Aurum\IdentityPolicy((string)aurum_config('APP_ENV','staging'),filter_var(aurum_config('REQUIRE_EMAIL_VERIFICATION',false),FILTER_VALIDATE_BOOLEAN),aurum_admin_emails());
}
