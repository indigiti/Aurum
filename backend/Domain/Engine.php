<?php
declare(strict_types=1);
namespace Aurum;
final class Engine {
    // Prices and money are integer cents. One unit is one troy ounce (0.01 standard lots).
    public static function account(int $dollars, array $rules=[]): array {
        $rules=Challenges::validate($rules?:Challenges::defaults());
        if(!in_array($dollars,$rules['accountSizes'],true))throw new \InvalidArgumentException('Invalid challenge');
        $base=$dollars*100;
        return ['initial'=>$base,'balance'=>$base,'equity'=>$base,'dayBase'=>$base,'day'=>gmdate('Y-m-d'),'phase'=>1,'status'=>'active','attemptId'=>bin2hex(random_bytes(16)),'rules'=>$rules,'positions'=>[],'pending'=>[],'journal'=>[],'requests'=>[],'days'=>[],'quoteTs'=>0,'equityCurve'=>[],'phaseHistory'=>[],'riskEvents'=>[],'backtests'=>[],'ledger'=>[['id'=>bin2hex(random_bytes(16)),'type'=>'opening-capital','delta'=>$base,'balance'=>$base,'timestamp'=>time()*1000,'phase'=>1]],'createdAt'=>time()];
    }
    public static function quote(array $q): array {
        $bid=(int)round((float)($q['bid']??0)*100);$ask=(int)round((float)($q['ask']??0)*100);$ts=(int)($q['timestamp']??0);
        if(($q['stale']??true)||$bid<=0||$ask<$bid||$ts<=0||abs((int)(microtime(true)*1000)-$ts)>10000)throw new \RuntimeException('Fresh server quote required');
        return ['bid'=>$bid,'ask'=>$ask,'ts'=>$ts];
    }
    private static function pnl(array $p,array $q): int {
        return ($p['side']==='buy'?$q['bid']-$p['entry']:$p['entry']-$q['ask'])*$p['units'];
    }
    public static function margin(array $a): int {
        $m=0;$leverage=max(1,(int)($a['rules']['leverage']??30));
        foreach($a['positions'] as $p)$m+=(int)ceil($p['entry']*$p['units']/$leverage);
        foreach($a['pending'] as $p)$m+=(int)ceil($p['price']*$p['units']/$leverage);
        return $m;
    }
    public static function ensureLedger(array &$a): void {
        if(!isset($a['ledger']))$a['ledger']=[['id'=>bin2hex(random_bytes(16)),'type'=>'legacy-opening-balance','delta'=>$a['balance'],'balance'=>$a['balance'],'timestamp'=>time()*1000,'phase'=>$a['phase']]];
    }
    public static function book(array &$a,int $delta,string $type,int $ts,?string $reference=null): void {
        self::ensureLedger($a);
        $a['balance']+=$delta;
        $a['ledger'][]=['id'=>bin2hex(random_bytes(16)),'type'=>$type,'delta'=>$delta,'balance'=>$a['balance'],'timestamp'=>$ts,'phase'=>$a['phase'],'reference'=>$reference];
    }
    public static function close(array &$a,string $id,array $q,string $reason='manual',?int $units=null): void {
        if(!isset($a['positions'][$id]))throw new \InvalidArgumentException('Position not found');
        $p=$a['positions'][$id];$units??=$p['units'];
        if($units<1||$units>$p['units'])throw new \InvalidArgumentException('Invalid closing size');
        $p['units']=$units;$pnl=self::pnl($p,$q);$fillId=bin2hex(random_bytes(16));
        self::book($a,$pnl,'realized-pnl',$q['ts'],$fillId);
        $a['journal'][]=$p+['fillId'=>$fillId,'attemptId'=>$a['attemptId']??null,'phase'=>$a['phase'],'exit'=>$p['side']==='buy'?$q['bid']:$q['ask'],'pnl'=>$pnl,'closedAt'=>$q['ts'],'reason'=>$reason];
        $a['positions'][$id]['units']-=$units;
        if($a['positions'][$id]['units']===0)unset($a['positions'][$id]);
        $a['days'][$a['day']]=true;
    }
    public static function modifyProtection(array &$a,string $id,array $q,array $r): void {
        if($a['status']!=='active'||!isset($a['positions'][$id]))throw new \InvalidArgumentException('Active position required');
        $p=$a['positions'][$id];$mark=$p['side']==='buy'?$q['bid']:$q['ask'];
        foreach(['sl','tp'] as $key){
            if(!array_key_exists($key,$r))continue;
            $v=$r[$key];if($v===null||$v===''){$p[$key]=null;continue;}
            if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<=0||(float)$v>1000000)throw new \InvalidArgumentException('Invalid protection');
            $x=(int)round((float)$v*100);$below=($p['side']==='buy')===($key==='sl');
            if($below?$x>=$mark:$x<=$mark)throw new \InvalidArgumentException('Protection must be on correct side of current exit quote');
            $p[$key]=$x;
        }
        $a['positions'][$id]=$p;
    }
    public static function modifyPending(array &$a,string $id,array $q,array $r): string {
        if(!isset($a['pending'][$id]))throw new \InvalidArgumentException('Pending order not found');
        $old=$a['pending'][$id];$copy=$a;unset($copy['pending'][$id]);
        $newId=self::placePending($copy,$q,['type'=>$old['type'],'units'=>$r['units']??$old['units'],'price'=>$r['price']??($old['price']/100),'sl'=>array_key_exists('sl',$r)?$r['sl']:$old['sl'],'tp'=>array_key_exists('tp',$r)?$r['tp']:$old['tp']]);
        $a=$copy;return $newId;
    }
    private static function openAt(array &$a,array $q,array $r,int $entry): string {
        if($a['status']!=='active')throw new \InvalidArgumentException('Account locked');
        if(($a['rules']['newsRestricted']??false)&&($q['newsStatus']??'unconfigured-or-expired')!=='clear')throw new \InvalidArgumentException('New exposure blocked by economic calendar');
        $side=$r['side']??'';$units=filter_var($r['units']??null,FILTER_VALIDATE_INT);
        if(!in_array($side,['buy','sell'],true)||$units===false||$units<1||$units>1000)throw new \InvalidArgumentException('Invalid order size');
        $levels=[];
        foreach(['sl','tp'] as $key){
            $v=$r[$key]??null;if($v===null||$v===''){$levels[$key]=null;continue;}
            if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<=0||(float)$v>1000000)throw new \InvalidArgumentException('Invalid protection');
            $x=(int)round((float)$v*100);$below=($side==='buy')===($key==='sl');
            if($below?$x>=$entry:$x<=$entry)throw new \InvalidArgumentException('Invalid protection direction');
            $levels[$key]=$x;
        }
        $used=0;foreach($a['positions'] as $p)$used+=$p['units'];foreach($a['pending'] as $p)$used+=$p['units'];
        $maxUnits=(int)($a['rules']['maxUnits']??1000);$leverage=max(1,(int)($a['rules']['leverage']??30));
        if($used+$units>$maxUnits||self::margin($a)+(int)ceil($entry*$units/$leverage)>$a['equity'])throw new \InvalidArgumentException('Exposure or margin limit');
        $id=bin2hex(random_bytes(12));
        $a['positions'][$id]=['id'=>$id,'side'=>$side,'units'=>$units,'entry'=>$entry,'openedAt'=>$q['ts']]+$levels;
        $a['days'][$a['day']]=true;
        return $id;
    }
    public static function open(array &$a,array $q,array $r): string {
        return self::openAt($a,$q,$r,($r['side']??'')==='buy'?$q['ask']:$q['bid']);
    }
    public static function placePending(array &$a,array $q,array $r): string {
        if($a['status']!=='active')throw new \InvalidArgumentException('Account locked');
        if(($a['rules']['newsRestricted']??false)&&($q['newsStatus']??'unconfigured-or-expired')!=='clear')throw new \InvalidArgumentException('New exposure blocked by economic calendar');
        $type=$r['type']??'';$side=str_starts_with((string)$type,'buy-')?'buy':'sell';
        if(!in_array($type,['buy-limit','sell-limit','buy-stop','sell-stop'],true))throw new \InvalidArgumentException('Invalid pending type');
        $units=filter_var($r['units']??null,FILTER_VALIDATE_INT);$price=$r['price']??null;
        if($units===false||$units<1||$units>1000||!is_numeric($price)||!is_finite((float)$price)||(float)$price<=0||(float)$price>1000000)throw new \InvalidArgumentException('Invalid pending order');
        if(count($a['pending'])>=(int)($a['rules']['maxPending']??50))throw new \InvalidArgumentException('Pending order limit');
        $entry=(int)round((float)$price*100);
        $isLimit=str_ends_with($type,'limit');
        if($isLimit?(($side==='buy'&&$entry>=$q['ask'])||($side==='sell'&&$entry<=$q['bid'])):(($side==='buy'&&$entry<=$q['ask'])||($side==='sell'&&$entry>=$q['bid'])))throw new \InvalidArgumentException('Pending price is on the wrong side of market');
        $levels=[];
        foreach(['sl','tp'] as $key){
            $v=$r[$key]??null;
            if($v===null||$v===''){$levels[$key]=null;continue;}
            if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<=0||(float)$v>1000000)throw new \InvalidArgumentException('Invalid pending protection');
            $x=(int)round((float)$v*100);$below=($side==='buy')===($key==='sl');
            if($below?$x>=$entry:$x<=$entry)throw new \InvalidArgumentException('Invalid pending protection direction');
            $levels[$key]=(float)$v;
        }
        $used=0;foreach($a['positions'] as $p)$used+=$p['units'];foreach($a['pending'] as $p)$used+=$p['units'];
        $maxUnits=(int)($a['rules']['maxUnits']??1000);$leverage=max(1,(int)($a['rules']['leverage']??30));
        if($used+$units>$maxUnits||self::margin($a)+(int)ceil($entry*$units/$leverage)>$a['equity'])throw new \InvalidArgumentException('Exposure or margin limit');
        $id=bin2hex(random_bytes(12));$a['pending'][$id]=['id'=>$id,'type'=>$type,'side'=>$side,'units'=>$units,'price'=>$entry]+$levels+['createdAt'=>$q['ts']];
        return $id;
    }
    public static function cancelPending(array &$a,string $id): void {
        if(!isset($a['pending'][$id]))throw new \InvalidArgumentException('Pending order not found');
        unset($a['pending'][$id]);
    }
    public static function mark(array &$a,array $q): void {
        if($q['ts']<$a['quoteTs'])throw new \RuntimeException('Out of order quote');
        self::ensureLedger($a);
        $previousEquity=$a['equity'];$a['quoteTs']=$q['ts'];$day=gmdate('Y-m-d',intdiv($q['ts'],1000));
        $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
        // Preserve overnight gap losses instead of resetting the baseline to the first post-gap quote.
        if($day!==$a['day']){$a['day']=$day;$a['dayBase']=$previousEquity;}
        self::enforceRisk($a,$q);
        foreach($a['pending'] as $id=>$p){
            $trigger=match($p['type']){
                'buy-limit'=>$q['ask']<=$p['price'],
                'sell-limit'=>$q['bid']>=$p['price'],
                'buy-stop'=>$q['ask']>=$p['price'],
                'sell-stop'=>$q['bid']<=$p['price'],
                default=>false
            };
            if($trigger){
                if(($a['rules']['newsRestricted']??false)&&($q['newsStatus']??'unconfigured-or-expired')!=='clear')continue;
                unset($a['pending'][$id]);
                try{self::openAt($a,$q,['side'=>$p['side'],'units'=>$p['units'],'sl'=>$p['sl'],'tp'=>$p['tp']],$p['side']==='buy'?$q['ask']:$q['bid']);}
                catch(\InvalidArgumentException $e){$a['riskEvents'][]=['type'=>'pending-rejected','id'=>$id,'message'=>$e->getMessage(),'timestamp'=>$q['ts']];}
                $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
                if(self::enforceRisk($a,$q))break;
            }
        }
        foreach($a['positions'] as $id=>$p){
            $x=$p['side']==='buy'?$q['bid']:$q['ask'];
            $sl=$p['sl']!==null&&($p['side']==='buy'?$x<=$p['sl']:$x>=$p['sl']);
            $tp=$p['tp']!==null&&($p['side']==='buy'?$x>=$p['tp']:$x<=$p['tp']);
            if($sl||$tp)self::close($a,$id,$q,$sl?'stop-loss':'take-profit');
        }
        $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
        self::enforceRisk($a,$q);
        $rules=$a['rules']??Challenges::defaults();
        $point=['timestamp'=>$q['ts'],'phase'=>$a['phase'],'balance'=>$a['balance'],'equity'=>$a['equity']];
        $lastIndex=array_key_last($a['equityCurve']);
        if($lastIndex!==null&&$a['equityCurve'][$lastIndex]['timestamp']===$q['ts'])$a['equityCurve'][$lastIndex]=$point;
        else $a['equityCurve'][]=$point;
        if(count($a['equityCurve'])>1000)$a['equityCurve']=array_slice($a['equityCurve'],-1000);
        if(count($a['riskEvents'])>500)$a['riskEvents']=array_slice($a['riskEvents'],-500);
        if($a['status']==='active'&&$a['phase']<3&&!$a['positions']&&!$a['pending']&&self::consistencyMet($a)&&count($a['days'])>=(int)$rules['minTradingDays']&&$a['balance']>=$a['initial']*(1+(float)$rules['phaseTargets'][$a['phase']-1]/100)){
            $a['phaseHistory'][]=['phase'=>$a['phase'],'completedAt'=>$q['ts'],'profit'=>$a['balance']-$a['initial']];self::book($a,$a['initial']-$a['balance'],'phase-capital-reset',$q['ts']);$a['phase']++;$a['equity']=$a['dayBase']=$a['initial'];$a['days']=[];
        }
    }
    private static function enforceRisk(array &$a,array $q): bool {
        $event=null;
        $rules=$a['rules']??Challenges::defaults();
        if(in_array($a['status'],['active','locked'],true)&&$a['equity']<=$a['initial']*(1-$rules['maxLossPct']/100))$event='maximum-drawdown';
        elseif(in_array($a['status'],['active','locked'],true)&&$a['equity']<=$a['dayBase']-$a['initial']*$rules['dailyLossPct']/100)$event='daily-loss';
        if($event!==null){
            foreach(array_keys($a['positions']) as $id)self::close($a,$id,$q,'risk-breach');
            $a['pending']=[];$a['equity']=$a['balance'];$a['status']='failed';
            $a['riskEvents'][]=['type'=>$event,'equity'=>$a['equity'],'timestamp'=>$q['ts']];
            return true;
        }
        return false;
    }
    public static function consistencyMet(array $a): bool {
        $limit=(float)($a['rules']['consistencyPct']??0);
        if($limit<=0)return true;
        $daily=[];
        foreach($a['journal']??[] as $t){
            if(($t['phase']??0)!==$a['phase']||($t['attemptId']??null)!==($a['attemptId']??null))continue;
            $day=gmdate('Y-m-d',intdiv($t['closedAt'],1000));$daily[$day]=($daily[$day]??0)+$t['pnl'];
        }
        $net=$a['balance']-$a['initial'];
        return $net>0&&$daily&&max($daily)*100<=$net*$limit;
    }
}
