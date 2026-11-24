<?php
declare(strict_types=1);
namespace Aurum;
final class Backtester {
    public static function run(array $candles,int $fast,int $slow,int $units,float $spread,float $slippageBps,float $feePerLot): array {
        if($fast<2||$fast>50||$slow<5||$slow>200||$fast>=$slow||$units<1||$units>1000||$spread<0||$spread>10||$slippageBps<0||$slippageBps>100||$feePerLot<0||$feePerLot>50)throw new \InvalidArgumentException('Backtest parameters outside limits');
        $rows=array_values(array_filter($candles,static fn($c)=>($c['confirmed']??false)===true));
        if(count($rows)<$slow+3)throw new \InvalidArgumentException('Not enough confirmed candles for selected slow period');
        $closes=array_map(static fn($c)=>(float)$c['close'],$rows);
        $sma=static fn(array $v,int $end,int $period):float=>array_sum(array_slice($v,$end-$period+1,$period))/$period;
        $open=null;$trades=[];$equity=0;$peak=0;$maxDd=0;$wins=0;$grossWin=0;$grossLoss=0;
        $spreadCost=$spread/2;$feeCents=(int)round($feePerLot*($units/100)*100);
        for($i=$slow;$i<count($rows)-1;$i++){
            $f0=$sma($closes,$i-1,$fast);$s0=$sma($closes,$i-1,$slow);$f1=$sma($closes,$i,$fast);$s1=$sma($closes,$i,$slow);
            $enter=$f0<=$s0&&$f1>$s1;$exit=$f0>=$s0&&$f1<$s1;$next=(float)$rows[$i+1]['open'];$slip=$next*$slippageBps/10000;
            if($open===null&&$enter){$open=$next+$spreadCost+$slip;$openedAt=(int)$rows[$i+1]['time'];}
            elseif($open!==null&&$exit){
                $out=$next-$spreadCost-$slip;$pnl=(int)round(($out-$open)*$units*100)-$feeCents;$equity+=$pnl;$peak=max($peak,$equity);$maxDd=max($maxDd,$peak-$equity);
                $trades[]=['entry'=>$open,'exit'=>$out,'units'=>$units,'pnl'=>$pnl,'openedAt'=>$openedAt,'closedAt'=>(int)$rows[$i+1]['time'],'reason'=>'sma-cross-down'];
                if($pnl>0){$wins++;$grossWin+=$pnl;}else $grossLoss+=abs($pnl);$open=null;
            }
        }
        if($open!==null){$last=(float)$rows[array_key_last($rows)]['close'];$out=$last-$spreadCost-$last*$slippageBps/10000;$pnl=(int)round(($out-$open)*$units*100)-$feeCents;$equity+=$pnl;$peak=max($peak,$equity);$maxDd=max($maxDd,$peak-$equity);
            $trades[]=['entry'=>$open,'exit'=>$out,'units'=>$units,'pnl'=>$pnl,'openedAt'=>$openedAt,'closedAt'=>(int)$rows[array_key_last($rows)]['time'],'reason'=>'end-of-data'];
            if($pnl>0){$wins++;$grossWin+=$pnl;}else $grossLoss+=abs($pnl);}
        $count=count($trades);
        return ['strategy'=>'SMA crossover long-only','fast'=>$fast,'slow'=>$slow,'units'=>$units,'barCount'=>count($rows),
            'firstTimestamp'=>(int)$rows[0]['time'],'lastTimestamp'=>(int)$rows[array_key_last($rows)]['time'],
            'spreadAssumption'=>$spread,'slippageBps'=>$slippageBps,'roundTripFeePerLot'=>$feePerLot,'trades'=>$count,'wins'=>$wins,'losses'=>$count-$wins,
            'winRatePct'=>$count?$wins/$count*100:0,'netPnl'=>$equity,'grossProfit'=>$grossWin,'grossLoss'=>$grossLoss,
            'profitFactor'=>$grossLoss?$grossWin/$grossLoss:null,'maxClosedTradeDrawdown'=>$maxDd,'tradeList'=>array_slice($trades,-100)];
    }
}
