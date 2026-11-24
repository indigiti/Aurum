<?php
declare(strict_types=1);
namespace Aurum;
final class ChallengeSettings {
    public static function current(Storage $store): array {
        return $store->transaction('config-challenges',function(array &$s){$s['rules']??=Challenges::defaults();return Challenges::validate($s['rules']);});
    }
}
