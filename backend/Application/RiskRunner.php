<?php
declare(strict_types=1);
namespace Aurum;
final class RiskRunner {
    public static function sweep(Storage $store,array $q): array {
        $processed=0;$failed=0;$errors=0;
        foreach($store->all('user-') as $scope=>$snapshot){
            $a=$snapshot['account']??null;
            if(!is_array($a)||(!in_array($a['status'],['active','locked'],true)))continue;
            // Flat accounts also need day-boundary observations for challenge risk.
            try{
                $result=$store->transaction($scope,static function(array &$s)use($q){
                    $a=&$s['account'];
                    if(!is_array($a)||!in_array($a['status'],['active','locked'],true)||$q['ts']<=$a['quoteTs'])return null;
                    if(abs((int)(microtime(true)*1000)-$q['ts'])>10000)throw new \RuntimeException('Quote expired during account sweep');
                    Engine::mark($a,$q);return $a['status'];
                });
                if($result!==null){$processed++;if($result==='failed')$failed++;}
            }catch(\Throwable $ex){$errors++;error_log('Aurum risk sweep: '.get_class($ex));}
        }
        return ['processed'=>$processed,'failed'=>$failed,'errors'=>$errors];
    }
}
