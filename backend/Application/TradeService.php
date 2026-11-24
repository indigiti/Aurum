<?php
declare(strict_types=1);
namespace Aurum;
use RuntimeException;
use InvalidArgumentException;
final class TradeService {
    public static function execute(array &$s,?array $q,string $action,array $body): array {
        if(!is_array($s['account']??null))throw new RuntimeException('Account unavailable');
        $a=&$s['account'];Engine::ensureLedger($a);$error=null;$id=null;
        if($q!==null&&abs((int)(microtime(true)*1000)-$q['ts'])>10000)$q=null;
        if($q!==null){try{Engine::mark($a,$q);TradingTools::observeAlerts($a,$q);}catch(RuntimeException $e){$q=null;}}
        if($action!=='state'){
            $key=$body['requestId']??'';
            if(!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key))$error='Invalid request ID';
            elseif(array_key_exists($key,$a['requests']))$id=$a['requests'][$key];
            elseif($q===null&&!in_array($action,['cancel-pending','cancel-all-pending','request-simulated-payout','create-alert','cancel-alert','basket-save','basket-delete'],true))$error='Feed unavailable or stale; order blocked';
            else{
                try{
                    if($action==='open')$id=Engine::open($a,$q,$body);
                    elseif($action==='close'){ $id=(string)($body['id']??'');$units=array_key_exists('units',$body)?filter_var($body['units'],FILTER_VALIDATE_INT):null;if($units===false)throw new InvalidArgumentException('Invalid closing size');Engine::close($a,$id,$q,'manual',$units); }
                    elseif($action==='bulk-close'){
                        $mode=$body['mode']??null;$ids=$body['ids']??[];$snapshot=$body['snapshot']??null;
                        if(!is_string($mode)||!is_array($ids)||count($ids)>1000||!is_string($snapshot))throw new InvalidArgumentException('Invalid close request');
                        foreach($ids as $positionId)if(!is_string($positionId))throw new InvalidArgumentException('Invalid position ID');
                        Exposure::close($a,$q,$mode,$ids,$snapshot);$id='bulk-close';
                    }
                    elseif($action==='cancel-all-pending'){
                        $snapshot=$body['snapshot']??null;if(!is_string($snapshot))throw new InvalidArgumentException('Invalid order book');
                        Exposure::cancelAll($a,$snapshot);$id='cancel-all-pending';
                    }
                    elseif(in_array($action,['break-even','basket-protection'],true)){
                        $ids=$body['ids']??null;$snapshot=$body['snapshot']??null;
                        if(!is_array($ids)||!is_string($snapshot)||!hash_equals(Exposure::snapshot($a),$snapshot))throw new InvalidArgumentException('Position book changed; review again');
                        foreach($ids as $pid)if(!is_string($pid))throw new InvalidArgumentException('Invalid position ID');
                        if($action==='break-even')TradingTools::breakEven($a,$q,$ids);else TradingTools::basket($a,$q,$ids,$body);
                        $id=$action;
                    }
                    elseif($action==='basket-save')$id=TradingTools::saveBasket($a,$body);
                    elseif($action==='basket-delete'){$id=(string)($body['id']??'');TradingTools::deleteBasket($a,$id);}
                    elseif($action==='create-alert')$id=TradingTools::alert($a,$body,$q);
                    elseif($action==='cancel-alert'){$id=(string)($body['id']??'');TradingTools::cancelAlert($a,$id);}
                    elseif($action==='modify-protection'){if(isset($body['snapshot'])&&(!is_string($body['snapshot'])||!hash_equals(Exposure::snapshot($a),$body['snapshot'])))throw new InvalidArgumentException('Position book changed; review again');$id=(string)($body['id']??'');Engine::modifyProtection($a,$id,$q,$body);}
                    elseif($action==='modify-pending'){$id=Engine::modifyPending($a,(string)($body['id']??''),$q,$body);}
                    elseif($action==='request-simulated-payout'){$gross=filter_var($body['grossCents']??null,FILTER_VALIDATE_INT);if($gross===false||$gross>100000000)throw new InvalidArgumentException('Invalid virtual amount');$id=SimulatedPayout::request($a,$gross,(int)(microtime(true)*1000));}
                    elseif($action==='pending')$id=Engine::placePending($a,$q,$body);
                    else{$id=(string)($body['id']??'');Engine::cancelPending($a,$id);}
                    $a['requests'][$key]=$id;
                    if($q!==null){Engine::mark($a,$q);TradingTools::observeAlerts($a,$q);}
                }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            }
        }
        $view=$a;unset($view['requests']);$view['margin']=Engine::margin($a);$view['exposure']=Exposure::summarize($a,$q);
        return ['account'=>$view,'feed'=>$q===null?'offline':'live','newsStatus'=>$q['newsStatus']??'unknown','error'=>$error,'id'=>$id];
}
}
