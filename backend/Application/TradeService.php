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
        if($q!==null){try{Engine::mark($a,$q);}catch(RuntimeException $e){$q=null;}}
        if($action!=='state'){
            $key=$body['requestId']??'';
            if(!is_string($key)||!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$key))$error='Invalid request ID';
            elseif(array_key_exists($key,$a['requests']))$id=$a['requests'][$key];
            elseif($q===null&&!in_array($action,['cancel-pending','request-simulated-payout'],true))$error='Feed unavailable or stale; order blocked';
            else{
                try{
                    if($action==='open')$id=Engine::open($a,$q,$body);
                    elseif($action==='close'){ $id=(string)($body['id']??'');$units=array_key_exists('units',$body)?filter_var($body['units'],FILTER_VALIDATE_INT):null;if($units===false)throw new InvalidArgumentException('Invalid closing size');Engine::close($a,$id,$q,'manual',$units); }
                    elseif($action==='modify-protection'){$id=(string)($body['id']??'');Engine::modifyProtection($a,$id,$q,$body);}
                    elseif($action==='modify-pending'){$id=Engine::modifyPending($a,(string)($body['id']??''),$q,$body);}
                    elseif($action==='request-simulated-payout'){$gross=filter_var($body['grossCents']??null,FILTER_VALIDATE_INT);if($gross===false||$gross>100000000)throw new InvalidArgumentException('Invalid virtual amount');$id=SimulatedPayout::request($a,$gross,(int)(microtime(true)*1000));}
                    elseif($action==='pending')$id=Engine::placePending($a,$q,$body);
                    else{$id=(string)($body['id']??'');Engine::cancelPending($a,$id);}
                    $a['requests'][$key]=$id;
                    if($q!==null)Engine::mark($a,$q);
                }catch(InvalidArgumentException $e){$error=$e->getMessage();}
            }
        }
        $view=$a;unset($view['requests']);$view['margin']=Engine::margin($a);
        return ['account'=>$view,'feed'=>$q===null?'offline':'live','newsStatus'=>$q['newsStatus']??'unknown','error'=>$error,'id'=>$id];
}
}
