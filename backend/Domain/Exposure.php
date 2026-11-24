<?php
declare(strict_types=1);
namespace Aurum;
final class Exposure {
    public static function snapshot(array $a): string {
        $p=$a['positions'];$o=$a['pending'];ksort($p);ksort($o);
        return hash('sha256',json_encode([$a['attemptId']??null,$a['phase'],$p,$o],JSON_THROW_ON_ERROR));
    }
    private static function pnl(array $p,array $q,int $units): int {
        return ($p['side']==='buy'?$q['bid']-$p['entry']:$p['entry']-$q['ask'])*$units;
    }
    public static function plan(array $a,string $mode,array $ids=[]): array {
        $p=$a['positions'];
        if($mode==='selected'){
            if(!$ids||count($ids)!==count(array_unique($ids,SORT_STRING)))throw new \InvalidArgumentException('Select distinct positions');
            foreach($ids as $id)if(!is_string($id)||!isset($p[$id]))throw new \InvalidArgumentException('Position selection changed');
            return array_map(static fn($id)=>['id'=>$id,'units'=>$p[$id]['units']],$ids);
        }
        uasort($p,static fn($x,$y)=>($x['openedAt']<=>$y['openedAt'])?:strcmp($x['id'],$y['id']));
        if($mode==='matched'){
            $buy=0;$sell=0;foreach($p as $v){if($v['side']==='buy')$buy+=$v['units'];else $sell+=$v['units'];}
            $remaining=['buy'=>min($buy,$sell),'sell'=>min($buy,$sell)];$out=[];
            foreach($p as $v){$units=min($v['units'],$remaining[$v['side']]);if($units>0){$out[]=['id'=>$v['id'],'units'=>$units];$remaining[$v['side']]-=$units;}}
            return $out;
        }
        if(!in_array($mode,['all','buy','sell','flatten'],true))throw new \InvalidArgumentException('Invalid close mode');
        $out=[];foreach($p as $v)if(in_array($mode,['all','flatten'],true)||$v['side']===$mode)$out[]=['id'=>$v['id'],'units'=>$v['units']];
        return $out;
    }
    public static function summarize(array $a,?array $q): array {
        $buy=0;$sell=0;$buyValue=0;$sellValue=0;$buyCount=0;$sellCount=0;$marks=[];$pnl=$q===null?null:0;
        foreach($a['positions'] as $id=>$p){
            if($p['side']==='buy'){$buy+=$p['units'];$buyValue+=$p['units']*$p['entry'];$buyCount++;}
            else{$sell+=$p['units'];$sellValue+=$p['units']*$p['entry'];$sellCount++;}
            if($q!==null){$v=self::pnl($p,$q,$p['units']);$pnl+=$v;$marks[$id]=['price'=>$p['side']==='buy'?$q['bid']:$q['ask'],'pnl'=>$v];}
        }
        $matched=min($buy,$sell);$plan=self::plan($a,'matched');$matchedPnl=$q===null?null:0;
        if($q!==null)foreach($plan as $v)$matchedPnl+=self::pnl($a['positions'][$v['id']],$q,$v['units']);
        $pb=0;$ps=0;foreach($a['pending'] as $p){if($p['side']==='buy')$pb+=$p['units'];else $ps+=$p['units'];}
        return ['snapshot'=>self::snapshot($a),'buyUnits'=>$buy,'sellUnits'=>$sell,'buyCount'=>$buyCount,'sellCount'=>$sellCount,'grossUnits'=>$buy+$sell,'netUnits'=>$buy-$sell,'matchedUnits'=>$matched,'buyAverage'=>$buy?round($buyValue/$buy):null,'sellAverage'=>$sell?round($sellValue/$sell):null,'pendingBuyUnits'=>$pb,'pendingSellUnits'=>$ps,'pnl'=>$pnl,'marks'=>$marks,'matchedPnl'=>$matchedPnl,'matchedPlan'=>$plan,'quoteTs'=>$q['ts']??null,'bid'=>$q['bid']??null,'ask'=>$q['ask']??null];
    }
    public static function close(array &$a,array $q,string $mode,array $ids,string $snapshot): void {
        if(!hash_equals(self::snapshot($a),$snapshot))throw new \InvalidArgumentException('Positions or pending orders changed. Review the refreshed book and confirm again.');
        $plan=self::plan($a,$mode,$ids);
        if(!$plan&&($mode!=='flatten'||!$a['pending']))throw new \InvalidArgumentException('No positions to close');
        // All legs close using the same observed quote inside one account transaction.
        // No artificial midpoint or exchange close-by fill; each side pays its own spread.
        $copy=$a;
        if($mode==='flatten')$copy['pending']=[];
        foreach($plan as $leg)Engine::close($copy,$leg['id'],$q,$mode==='matched'?'matched-close':'bulk-close',$leg['units']);
        $a=$copy;
    }
    public static function cancelAll(array &$a,string $snapshot): void {
        if(!hash_equals(self::snapshot($a),$snapshot))throw new \InvalidArgumentException('Order book changed. Review and confirm again.');
        if(!$a['pending'])throw new \InvalidArgumentException('No pending orders');
        $a['pending']=[];
    }
}
