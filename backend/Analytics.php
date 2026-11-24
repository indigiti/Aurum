<?php
declare(strict_types=1);
namespace Aurum;
final class Analytics {
    public static function summarize(array $a): array {
        $trades=$a['journal']??[];$wins=array_values(array_filter($trades,static fn($t)=>(int)$t['pnl']>0));
        $losses=array_values(array_filter($trades,static fn($t)=>(int)$t['pnl']<0));
        $grossProfit=array_sum(array_map(static fn($t)=>(int)$t['pnl'],$wins));
        $grossLoss=abs(array_sum(array_map(static fn($t)=>(int)$t['pnl'],$losses)));
        $net=$grossProfit-$grossLoss;$n=count($trades);$curve=$a['equityCurve']??[];$peak=(int)($a['initial']??0);$maxDd=0;
        foreach($curve as $point){$equity=(int)($point['equity']??0);$peak=max($peak,$equity);if($peak>0)$maxDd=max($maxDd,($peak-$equity)/$peak*100);}
        return ['closedTrades'=>$n,'wins'=>count($wins),'losses'=>count($losses),'winRatePct'=>$n?count($wins)/$n*100:0,
            'grossProfit'=>$grossProfit,'grossLoss'=>$grossLoss,'netPnl'=>$net,'profitFactor'=>$grossLoss>0?$grossProfit/$grossLoss:null,
            'expectancy'=>$n?$net/$n:0,'averageWin'=>count($wins)?$grossProfit/count($wins):0,'averageLoss'=>count($losses)?-$grossLoss/count($losses):0,
            'maxObservedDrawdownPct'=>$maxDd,'tradingDays'=>count($a['days']??[]),'openPositions'=>count($a['positions']??[]),
            'pendingOrders'=>count($a['pending']??[]),'equityCurve'=>$curve,'recentTrades'=>array_slice(array_reverse($trades),0,50)];
    }
}
