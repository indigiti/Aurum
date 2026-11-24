<?php
declare(strict_types=1);
namespace Aurum;
final class Challenges {
    public static function defaults(): array {
        return ['version'=>1,'accountSizes'=>[5000,10000,25000,50000,100000,200000],'phaseTargets'=>[8,5],'dailyLossPct'=>5.0,'maxLossPct'=>10.0,'minTradingDays'=>5,'leverage'=>30,'maxUnits'=>1000,'maxPending'=>50,'profitSplitPct'=>80,'newsRestricted'=>false,'consistencyPct'=>0];
    }
    public static function validate(array $r): array {
        $d=self::defaults();$sizes=$r['accountSizes']??$d['accountSizes'];
        if(!is_array($sizes)||!$sizes||count($sizes)>12)throw new \InvalidArgumentException('Invalid account sizes');
        $sizes=array_values(array_unique(array_map('intval',$sizes)));sort($sizes);
        foreach($sizes as $v)if($v<1000||$v>1000000)throw new \InvalidArgumentException('Account size outside allowed range');
        $targets=$r['phaseTargets']??$d['phaseTargets'];
        if(!is_array($targets)||count($targets)!==2)throw new \InvalidArgumentException('Two phase targets required');
        $targets=array_map('floatval',array_values($targets));
        foreach($targets as $v)if($v<1||$v>20)throw new \InvalidArgumentException('Invalid target');
        $out=['version'=>(int)($r['version']??1),'accountSizes'=>$sizes,'phaseTargets'=>$targets,
            'dailyLossPct'=>(float)($r['dailyLossPct']??$d['dailyLossPct']),
            'maxLossPct'=>(float)($r['maxLossPct']??$d['maxLossPct']),
            'minTradingDays'=>(int)($r['minTradingDays']??$d['minTradingDays']),
            'leverage'=>(int)($r['leverage']??$d['leverage']),
            'maxUnits'=>(int)($r['maxUnits']??$d['maxUnits']),
            'maxPending'=>(int)($r['maxPending']??$d['maxPending']),
            'profitSplitPct'=>(float)($r['profitSplitPct']??$d['profitSplitPct']),
            'newsRestricted'=>filter_var($r['newsRestricted']??false,FILTER_VALIDATE_BOOLEAN),
            'consistencyPct'=>(float)($r['consistencyPct']??0)];
        if($out['dailyLossPct']<1||$out['dailyLossPct']>10||$out['maxLossPct']<2||$out['maxLossPct']>20||$out['maxLossPct']<=$out['dailyLossPct']||$out['minTradingDays']<1||$out['minTradingDays']>30||$out['leverage']<1||$out['leverage']>100||$out['maxUnits']<1||$out['maxUnits']>10000||$out['maxPending']<0||$out['maxPending']>100||$out['profitSplitPct']<0||$out['profitSplitPct']>100||$out['consistencyPct']<0||$out['consistencyPct']>100)throw new \InvalidArgumentException('Challenge rule outside allowed range');
        return $out;
    }
    public static function current(Storage $store): array {
        return $store->transaction('config-challenges',function(array &$s){$s['rules']??=self::defaults();return self::validate($s['rules']);});
    }
}
