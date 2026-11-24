<?php
declare(strict_types=1);
namespace Aurum;
final class Engine {
    // Prices and money are integer cents. One unit is one troy ounce (0.01 standard lots).
    public static function account(int $dollars, array $rules=[]): array {
        $rules=Challenges::validate($rules?:Challenges::defaults());
        if(!in_array($dollars,$rules['accountSizes'],true))throw new \InvalidArgumentException('Invalid challenge');
        $base=$dollars*100;
        return ['initial'=>$base,'balance'=>$base,'equity'=>$base,'dayBase'=>$base,'day'=>gmdate('Y-m-d'),'phase'=>1,'status'=>'active','rules'=>$rules,'positions'=>[],'pending'=>[],'journal'=>[],'requests'=>[],'days'=>[],'quoteTs'=>0,'equityCurve'=>[],'riskEvents'=>[],'backtests'=>[],'createdAt'=>time()];
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
    public static function close(array &$a,string $id,array $q,string $reason='manual'): void {
        if(!isset($a['positions'][$id]))throw new \InvalidArgumentException('Position not found');
        $p=$a['positions'][$id];$pnl=self::pnl($p,$q);$a['balance']+=$pnl;
        $a['journal'][]=$p+['exit'=>$p['side']==='buy'?$q['bid']:$q['ask'],'pnl'=>$pnl,'closedAt'=>$q['ts'],'reason'=>$reason];
        unset($a['positions'][$id]);$a['days'][$a['day']]=true;
    }
    private static function openAt(array &$a,array $q,array $r,int $entry): string {
        if($a['status']!=='active')throw new \InvalidArgumentException('Account locked');
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
        $type=$r['type']??'';$side=str_starts_with((string)$type,'buy-')?'buy':'sell';
        if(!in_array($type,['buy-limit','sell-limit','buy-stop','sell-stop'],true))throw new \InvalidArgumentException('Invalid pending type');
        $units=filter_var($r['units']??null,FILTER_VALIDATE_INT);$price=$r['price']??null;
        if($units===false||$units<1||$units>1000||!is_numeric($price)||!is_finite((float)$price)||(float)$price<=0)throw new \InvalidArgumentException('Invalid pending order');
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
        $a['quoteTs']=$q['ts'];$day=gmdate('Y-m-d',intdiv($q['ts'],1000));
        $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
        if($day!==$a['day']){$a['day']=$day;$a['dayBase']=$a['equity'];}
        foreach($a['pending'] as $id=>$p){
            $trigger=match($p['type']){
                'buy-limit'=>$q['ask']<=$p['price'],
                'sell-limit'=>$q['bid']>=$p['price'],
                'buy-stop'=>$q['ask']>=$p['price'],
                'sell-stop'=>$q['bid']<=$p['price'],
                default=>false
            };
            if($trigger){
                unset($a['pending'][$id]);
                try{self::openAt($a,$q,['side'=>$p['side'],'units'=>$p['units'],'sl'=>$p['sl'],'tp'=>$p['tp']],$p['side']==='buy'?$q['ask']:$q['bid']);}
                catch(\InvalidArgumentException $e){$a['riskEvents'][]=['type'=>'pending-rejected','id'=>$id,'message'=>$e->getMessage(),'timestamp'=>$q['ts']];}
            }
        }
        foreach($a['positions'] as $id=>$p){
            $x=$p['side']==='buy'?$q['bid']:$q['ask'];
            $sl=$p['sl']!==null&&($p['side']==='buy'?$x<=$p['sl']:$x>=$p['sl']);
            $tp=$p['tp']!==null&&($p['side']==='buy'?$x>=$p['tp']:$x<=$p['tp']);
            if($sl||$tp)self::close($a,$id,$q,$sl?'stop-loss':'take-profit');
        }
        $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
        $event=null;
        $rules=$a['rules']??Challenges::defaults();
        if(in_array($a['status'],['active','locked'],true)&&$a['equity']<=$a['initial']*(1-$rules['maxLossPct']/100))$event='maximum-drawdown';
        elseif(in_array($a['status'],['active','locked'],true)&&$a['equity']<=$a['dayBase']-$a['initial']*$rules['dailyLossPct']/100)$event='daily-loss';
        if($event!==null){
            foreach(array_keys($a['positions']) as $id)self::close($a,$id,$q,'risk-breach');
            $a['pending']=[];$a['equity']=$a['balance'];$a['status']='failed';
            $a['riskEvents'][]=['type'=>$event,'equity'=>$a['equity'],'timestamp'=>$q['ts']];
        }
        $point=['timestamp'=>$q['ts'],'balance'=>$a['balance'],'equity'=>$a['equity']];
        $lastIndex=array_key_last($a['equityCurve']);
        if($lastIndex!==null&&$a['equityCurve'][$lastIndex]['timestamp']===$q['ts'])$a['equityCurve'][$lastIndex]=$point;
        else $a['equityCurve'][]=$point;
        if(count($a['equityCurve'])>1000)$a['equityCurve']=array_slice($a['equityCurve'],-1000);
        if(count($a['riskEvents'])>500)$a['riskEvents']=array_slice($a['riskEvents'],-500);
        if($a['status']==='active'&&$a['phase']<3&&!$a['positions']&&!$a['pending']&&count($a['days'])>=(int)$rules['minTradingDays']&&$a['balance']>=$a['initial']*(1+(float)$rules['phaseTargets'][$a['phase']-1]/100)){
            $a['phase']++;$a['balance']=$a['equity']=$a['dayBase']=$a['initial'];$a['days']=[];$a['equityCurve']=[];
        }
    }
}
