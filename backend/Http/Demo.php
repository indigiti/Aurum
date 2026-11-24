<?php
declare(strict_types=1);
namespace Aurum;
final class Demo {
    public static function enabled(): bool {
        return filter_var(aurum_config('DEMO_ENABLED',aurum_config('APP_ENV','staging')!=='production'),FILTER_VALIDATE_BOOLEAN);
    }
    public static function active(): bool {
        if(isset($_SESSION['uid'])||!self::enabled()||!is_array($_SESSION['demo']['account']??null)||($_SESSION['demo']['expires']??0)<=time()){
            unset($_SESSION['demo']);return false;
        }
        return true;
    }
    public static function start(): void {
        $_SESSION['demo']=['expires'=>time()+7200,'account'=>Engine::account(100000)];
    }
    public static function listing(): array {
        $a=$_SESSION['demo']['account'];return [['id'=>'primary','initial'=>$a['initial'],'equity'=>$a['equity'],'phase'=>$a['phase'],'status'=>$a['status']]];
    }
    public static function guardAccount(): void {
        if(RequestContext::requestedId()!=='primary')throw new \InvalidArgumentException('Demo has one account');
    }
}
