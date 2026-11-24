<?php
declare(strict_types=1);
namespace Aurum;
final class TradingTools {
    public static function breakEven(array &$a,array $q,array $ids): void {
        if(!$ids||count($ids)>1000||count(array_unique($ids,SORT_STRING))!==count($ids))throw new \InvalidArgumentException('Select distinct positions');
        $copy=$a;
        foreach($ids as $id){
            if(!is_string($id)||!isset($copy['positions'][$id]))throw new \InvalidArgumentException('Position changed');
            $p=$copy['positions'][$id];$mark=$p['side']==='buy'?$q['bid']:$q['ask'];
            if($p['sl']!==null&&($p['side']==='buy'?$p['sl']>=$p['entry']:$p['sl']<=$p['entry']))continue;
            if($p['side']==='buy'?$mark<=$p['entry']:$mark>=$p['entry'])throw new \InvalidArgumentException('All selected positions must be beyond entry at their exit quote');
            Engine::modifyProtection($copy,$id,$q,['sl'=>$p['entry']/100]);
        }
        $a=$copy;
    }
    public static function basket(array &$a,array $q,array $ids,array $body): void {
        if(!$ids||count($ids)>1000||count(array_unique($ids,SORT_STRING))!==count($ids))throw new \InvalidArgumentException('Select distinct positions');
        $copy=$a;$side=null;
        if(!array_key_exists('sl',$body)&&!array_key_exists('tp',$body))throw new \InvalidArgumentException('Protection required');
        foreach($ids as $id){
            if(!is_string($id)||!isset($copy['positions'][$id]))throw new \InvalidArgumentException('Position changed');
            $p=$copy['positions'][$id];$side??=$p['side'];if($side!==$p['side'])throw new \InvalidArgumentException('Basket protection requires positions on one side');
            Engine::modifyProtection($copy,$id,$q,$body);
        }
        $a=$copy;
    }
    public static function saveBasket(array &$a,array $body): string {
        $name=$body['name']??null;$ids=$body['ids']??null;$snapshot=$body['snapshot']??null;
        if(!is_string($name)||trim($name)===''||strlen($name)>60||preg_match('/[\x00-\x1f\x7f]/',$name)||!is_array($ids)||!$ids||count($ids)>1000||!is_string($snapshot)||!hash_equals(Exposure::snapshot($a),$snapshot))throw new \InvalidArgumentException('Invalid basket or changed positions');
        foreach($ids as $id)if(!is_string($id)||!isset($a['positions'][$id]))throw new \InvalidArgumentException('Position changed');
        if(count(array_unique($ids,SORT_STRING))!==count($ids))throw new \InvalidArgumentException('Select distinct positions');
        $a['baskets']??=[];if(count($a['baskets'])>=20)throw new \InvalidArgumentException('At most 20 saved baskets');
        $id=bin2hex(random_bytes(12));$a['baskets'][$id]=['id'=>$id,'name'=>trim($name),'ids'=>array_values($ids)];return $id;
    }
    public static function deleteBasket(array &$a,string $id): void {
        if(!isset($a['baskets'][$id]))throw new \InvalidArgumentException('Basket not found');
        unset($a['baskets'][$id]);
    }
    public static function hasArmedAlerts(array $a): bool {
        foreach($a['alerts']??[] as $alert)if($alert['status']==='armed')return true;return false;
    }
    public static function alert(array &$a,array $body,?array $q): string {
        $direction=$body['direction']??null;$price=$body['price']??null;
        if(!in_array($direction,['above','below'],true)||!is_numeric($price)||!is_finite((float)$price)||$price<=0||$price>1000000)throw new \InvalidArgumentException('Invalid price alert');
        $a['alerts']??=[];
        if(count(array_filter($a['alerts'],static fn($v)=>$v['status']==='armed'))>=20)throw new \InvalidArgumentException('At most 20 armed alerts');
        if(count($a['alerts'])>=100){foreach($a['alerts'] as $id=>$v){if($v['status']!=='armed'){unset($a['alerts'][$id]);break;}}}
        $id=bin2hex(random_bytes(12));$a['alerts'][$id]=['id'=>$id,'direction'=>$direction,'price'=>(int)round((float)$price*100),'status'=>'armed','createdAt'=>$q['ts']??time()*1000];
        return $id;
    }
    public static function cancelAlert(array &$a,string $id): void {
        if(($a['alerts'][$id]['status']??null)!=='armed')throw new \InvalidArgumentException('Alert is not armed');
        $a['alerts'][$id]['status']='cancelled';
    }
    public static function observeAlerts(array &$a,array $q): void {
        if($q['ts']<($a['lastAlertQuoteTs']??0)||!self::hasArmedAlerts($a))return;
        $a['lastAlertQuoteTs']=$q['ts'];
        foreach($a['alerts']??[] as $id=>$alert){
            if($alert['status']!=='armed')continue;
            // Above uses executable bid, below uses executable ask, not midpoint.
            $mark=$alert['direction']==='above'?$q['bid']:$q['ask'];
            if($alert['direction']==='above'?$mark>=$alert['price']:$mark<=$alert['price']){
                $a['alerts'][$id]['status']='triggered';$a['alerts'][$id]['triggeredAt']=$q['ts'];$a['alerts'][$id]['observedPrice']=$mark;
            }
        }
    }
}
