<?php
declare(strict_types=1);
namespace Aurum;
final class SimulatedPayout {
    private static function eligible(array $a,int $gross): bool {
        return $gross>0&&$a['phase']===3&&$a['status']==='active'&&!$a['positions']&&!$a['pending']&&$a['balance']-$gross>=$a['initial']&&$a['balance']-$gross>$a['dayBase']-$a['initial']*($a['rules']['dailyLossPct']??5)/100;
    }
    public static function request(array &$a,int $gross,int $ts): string {
        if(!self::eligible($a,$gross))throw new \InvalidArgumentException('Not eligible: funded stage, flat account and available virtual profits required');
        foreach($a['payouts']??[] as $p)if($p['status']==='requested')throw new \InvalidArgumentException('Existing simulated payout request pending');
        $id=bin2hex(random_bytes(16));$share=(int)floor($gross*($a['rules']['profitSplitPct']??80)/100);
        if($share<1)throw new \InvalidArgumentException('Virtual payout share must be positive');
        $a['payouts'][]=['id'=>$id,'gross'=>$gross,'traderShare'=>$share,'status'=>'requested','createdAt'=>$ts,'simulation'=>true];return $id;
    }
    public static function review(array &$a,string $id,bool $approve,int $ts): void {
        foreach($a['payouts']??[] as $i=>$p){
            if($p['id']!==$id||$p['status']!=='requested')continue;
            if($approve){
                if(!self::eligible($a,$p['gross']))throw new \InvalidArgumentException('Virtual profit or account eligibility changed');
                Engine::book($a,-$p['gross'],'simulated-payout',$ts,$id);$a['equity']=$a['balance'];
            }
            $a['payouts'][$i]['status']=$approve?'simulated-approved':'rejected';$a['payouts'][$i]['reviewedAt']=$ts;return;
        }
        throw new \InvalidArgumentException('Pending simulated request not found');
    }
}
