<?php
declare(strict_types=1);
namespace Aurum;
final class CalendarSettings {
    public static function current(Storage $store): array {
        return $store->transaction('config-calendar',static fn(array &$s)=>$s['calendar']??['validUntil'=>0,'events'=>[]]);
    }
}
