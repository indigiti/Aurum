<?php
declare(strict_types=1);
namespace Aurum;
final class Engine {
    // Money and price in cents; one unit = one ounce = 0.01 simulated lots.
    public static function account(int $dollars): array {
        if(!in_array($dollars,[5000,10000,25000,50000,100000,200000],true))throw new \InvalidArgumentException('Invalid challenge');
        $base=$dollars*100;
        return ['initial'=>$base,'balance'=>$base,'equity'=>$base,'dayBase'=>$base,'day'=>gmdate('Y-m-d'),'phase'=>1,'status'=>'active','positions'=>[],'journal'=>[],'requests'=>[],'days'=>[],'quoteTs'=>0];
    }
    public static function quote(array $q): array {
        $bid=(int)round(($q['bid']??0)*100);$ask=(int)round(($q['ask']??0)*100);$ts=(int)($q['timestamp']??0);
        if(($q['stale']??true)||$bid<=0||$ask<$bid||$ts<=0||abs((int)(microtime(true)*1000)-$ts)>10000)throw new \RuntimeException('Fresh server quote required');
        return ['bid'=>$bid,'ask'=>$ask,'ts'=>$ts];
    }
    private static function pnl(array $p,array $q): int {
        return ($p['side']==='buy'?$q['bid']-$p['entry']:$p['entry']-$q['ask'])*$p['units'];
    }
    public static function margin(array $a): int {
        $m=0;foreach($a['positions'] as $p)$m+=(int)ceil($p['entry']*$p['units']/30);return $m;
    }
    public static function close(array &$a,string $id,array $q,string $reason='manual'): void {
        if(!isset($a['positions'][$id]))throw new \InvalidArgumentException('Position not found');
        $p=$a['positions'][$id];$pnl=self::pnl($p,$q);$a['balance']+=$pnl;
        $a['journal'][]=$p+['exit'=>$p['side']==='buy'?$q['bid']:$q['ask'],'pnl'=>$pnl,'closedAt'=>$q['ts'],'reason'=>$reason];
        unset($a['positions'][$id]);
        $a['days'][$a['day']]=true;
    }
    public static function mark(array &$a,array $q): void {
        if($q['ts']<$a['quoteTs'])throw new \RuntimeException('Out of order quote');
        $a['quoteTs']=$q['ts'];$day=gmdate('Y-m-d',intdiv($q['ts'],1000));
        // Snapshot at first processed quote of UTC day; staging only.
        $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
        if($day!==$a['day']){$a['day']=$day;$a['dayBase']=max($a['balance'],$a['equity']);}
        foreach($a['positions'] as $id=>$p){
            $x=$p['side']==='buy'?$q['bid']:$q['ask'];
            $sl=$p['sl']!==null&&($p['side']==='buy'?$x<=$p['sl']:$x>=$p['sl']);
            $tp=$p['tp']!==null&&($p['side']==='buy'?$x>=$p['tp']:$x<=$p['tp']);
            if($sl||$tp)self::close($a,$id,$q,$sl?'stop-loss':'take-profit');
        }
        $a['equity']=$a['balance'];foreach($a['positions'] as $p)$a['equity']+=self::pnl($p,$q);
        if($a['status']==='active'&&($a['equity']<=$a['initial']*.90||$a['equity']<=$a['dayBase']-$a['initial']*.05)){
            foreach(array_keys($a['positions']) as $id)self::close($a,$id,$q,'risk-breach');
            $a['equity']=$a['balance'];$a['status']='failed';
        }
        if($a['status']==='active'&&$a['phase']<3&&!$a['positions']&&count($a['days'])>=5&&$a['balance']>=$a['initial']*($a['phase']===1?1.08:1.05)){
            $a['phase']++;$a['balance']=$a['equity']=$a['dayBase']=$a['initial'];$a['days']=[];
        }
    }
    public static function open(array &$a,array $q,array $r): string {
        if($a['status']!=='active')throw new \InvalidArgumentException('Account locked');
        $side=$r['side']??'';$units=filter_var($r['units']??null,FILTER_VALIDATE_INT);
        if(!in_array($side,['buy','sell'],true)||$units===false||$units<1||$units>1000)throw new \InvalidArgumentException('Invalid order');
        $entry=$side==='buy'?$q['ask']:$q['bid'];
        $levels=[];foreach(['sl','tp'] as $key){
            $v=$r[$key]??null;if($v===null||$v===''){$levels[$key]=null;continue;}
            if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<=0||(float)$v>1000000)throw new \InvalidArgumentException('Invalid protection');
            $x=(int)round((float)$v*100);$below=($side==='buy')===($key==='sl');
            if($below?$x>=$entry:$x<=$entry)throw new \InvalidArgumentException('Invalid protection direction');
            $levels[$key]=$x;
        }
        $used=0;foreach($a['positions'] as $p)$used+=$p['units'];
        if($used+$units>1000||self::margin($a)+ceil($entry*$units/30)>$a['equity'])throw new \InvalidArgumentException('Exposure or margin limit');
        $id=bin2hex(random_bytes(12));$a['positions'][$id]=['id'=>$id,'side'=>$side,'units'=>$units,'entry'=>$entry,'openedAt'=>$q['ts']]+$levels;
        return $id;
    }
}
