<?php
declare(strict_types=1);
namespace Aurum;
use InvalidArgumentException;
final class AdminService {
    public static function execute(Storage $store,string $email,array $body): array {
    $action=$body['action']??'';
    if($action==='update-calendar'){
        if(!is_array($body['calendar']??null))throw new InvalidArgumentException('Calendar required');
        $calendar=NewsCalendar::validate($body['calendar']);
        $store->transaction('config-calendar',static function(array &$s)use($calendar){$s['calendar']=$calendar;});
        return ['ok'=>true,'calendar'=>$calendar];
    }
    if($action==='update-rules'){
        $rules=Challenges::validate($body['rules']??[]);
        $store->transaction('config-challenges',function(array &$s)use($rules){$rules['version']=(int)($s['rules']['version']??0)+1;$s['rules']=$rules;});
        return ['ok'=>true,'rules'=>$rules];
    }
    $target=$body['userId']??'';if(!is_string($target)||!preg_match('/^[a-f0-9]{64}(?:-a[1-4])?$/D',$target))throw new InvalidArgumentException();
    $result=$store->transaction('user-'.$target,function(array &$s)use($action,$email,$body){
        if(!is_array($s['account']??null))throw new InvalidArgumentException('Account not found');
        $a=&$s['account'];$before=$a['status'];
        if($action==='lock'&&$a['status']==='active'){$a['status']='locked';$a['pending']=[];}
        elseif($action==='unlock'&&$a['status']==='locked')$a['status']='active';
        elseif(in_array($action,['approve-simulated-payout','reject-simulated-payout'],true)){
            SimulatedPayout::review($a,(string)($body['payoutId']??''),$action==='approve-simulated-payout',(int)(microtime(true)*1000));
        }
        elseif($action==='reset'){
            if($a['positions']||$a['pending']||array_filter($a['payouts']??[],static fn($p)=>$p['status']==='requested'))throw new InvalidArgumentException('Close positions, cancel orders and resolve payout requests before reset');
            Engine::ensureLedger($a);Engine::book($a,$a['initial']-$a['balance'],'admin-capital-reset',(int)(microtime(true)*1000));
            $old=$a;$dollars=(int)($a['initial']/100);$rules=$a['rules']??Challenges::defaults();$a=Engine::account($dollars,$rules);
            foreach(['ledger','journal','riskEvents','phaseHistory','requests','payouts'] as $key)$a[$key]=$old[$key]??[];
        }else throw new InvalidArgumentException('Action not permitted for current status');
        $a['riskEvents'][]=['type'=>'admin-'.$action,'admin'=>$email,'previousStatus'=>$before,'timestamp'=>(int)(microtime(true)*1000)];
        return ['status'=>$a['status']];
    });
    return ['ok'=>true]+$result;
    }
}
