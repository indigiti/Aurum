<?php
declare(strict_types=1);
namespace Aurum;
final class Backtester {
    public static function confirmed(array $candles): array {
        $rows=array_values(array_filter($candles,static fn($c)=>($c['confirmed']??false)===true));
        $last=0;
        foreach($rows as $r){if((int)$r['time']<=$last||!is_numeric($r['close'])||!is_numeric($r['open'])||(float)$r['open']<=0||(float)$r['close']<=0)throw new \InvalidArgumentException('Invalid candle history');$last=(int)$r['time'];}
        return $rows;
    }
    private static function ma(array $v,int $period,string $method): array {
        $out=[];$sum=0.0;$ema=null;$alpha=2/($period+1);
        foreach($v as $i=>$x){
            if($method==='EMA'){$ema=$ema===null?$x:$x*$alpha+$ema*(1-$alpha);$out[$i]=$ema;}
            else{$sum+=$x;if($i>=$period)$sum-=$v[$i-$period];$out[$i]=$i>=$period-1?$sum/$period:null;}
        }
        return $out;
    }
    public static function run(array $candles,int $fast,int $slow,int $units,float $spread,float $slippageBps,float $feePerLot,string $method='SMA',string $mode='long',float $stopDistance=0,float $targetDistance=0): array {
        if($fast<2||$fast>50||$slow<5||$slow>200||$fast>=$slow||$units<1||$units>1000||$spread<0||$spread>10||$slippageBps<0||$slippageBps>100||$feePerLot<0||$feePerLot>50||!in_array($method,['SMA','EMA'],true)||!in_array($mode,['long','short','both'],true))throw new \InvalidArgumentException('Backtest parameters outside limits');
        if(!is_finite($stopDistance)||!is_finite($targetDistance)||$stopDistance<0||$targetDistance<0||$stopDistance>10000||$targetDistance>10000)throw new \InvalidArgumentException('Invalid strategy distances');
        $rows=self::confirmed($candles);
        if(count($rows)<$slow+3)throw new \InvalidArgumentException('Not enough confirmed candles for selected slow period');
        $closes=array_map(static fn($c)=>(float)$c['close'],$rows);$f=self::ma($closes,$fast,$method);$s=self::ma($closes,$slow,$method);
        $position=null;$trades=[];$equity=0;$peak=0;$maxDd=0;$wins=0;$losses=0;$grossWin=0;$grossLoss=0;
        $feeCents=(int)round($feePerLot*($units/100)*100);
        $fill=static fn(float $price,int $side):float=>$price+$side*($spread/2+$price*$slippageBps/10000);
        $close=function(float $price,int $time,string $reason)use(&$position,&$trades,&$equity,&$peak,&$maxDd,&$wins,&$losses,&$grossWin,&$grossLoss,$units,$feeCents,$fill):void{
            $exit=$fill($price,-$position['direction']);$pnl=(int)round(($exit-$position['entry'])*$position['direction']*$units*100)-$feeCents;
            $equity+=$pnl;$peak=max($peak,$equity);$maxDd=max($maxDd,$peak-$equity);
            $trades[]=['side'=>$position['direction']===1?'long':'short','entry'=>$position['entry'],'exit'=>$exit,'units'=>$units,'pnl'=>$pnl,'openedAt'=>$position['time'],'closedAt'=>$time,'reason'=>$reason];
            if($pnl>0){$wins++;$grossWin+=$pnl;}elseif($pnl<0){$losses++;$grossLoss+=abs($pnl);}$position=null;
        };
        for($i=$slow;$i<count($rows)-1;$i++){
            if($position!==null&&($stopDistance>0||$targetDistance>0)){
                $dir=$position['direction'];$entry=$position['entry'];$stop=$entry-$dir*$stopDistance;$target=$entry+$dir*$targetDistance;
                $row=$rows[$i];$open=(float)$row['open'];$low=(float)$row['low'];$high=(float)$row['high'];
                $hitStop=$stopDistance>0&&($dir===1?$low<=$stop:$high>=$stop);
                $hitTarget=$targetDistance>0&&($dir===1?$high>=$target:$low<=$target);
                // OHLC cannot establish intrabar sequence; assume stop first and adverse gap fills.
                if($hitStop)$close($dir===1?min($open,$stop):max($open,$stop),(int)$row['time'],'stop-distance');
                elseif($hitTarget)$close($target,(int)$row['time'],'target-distance');
            }
            $up=$f[$i-1]<=$s[$i-1]&&$f[$i]>$s[$i];$down=$f[$i-1]>=$s[$i-1]&&$f[$i]<$s[$i];
            if(!$up&&!$down)continue;
            $direction=$up?1:-1;$next=(float)$rows[$i+1]['open'];$time=(int)$rows[$i+1]['time'];
            if($position!==null&&$position['direction']!==$direction)$close($next,$time,'opposite-cross');
            if($position===null&&($mode==='both'||($mode==='long'&&$direction===1)||($mode==='short'&&$direction===-1)))$position=['direction'=>$direction,'entry'=>$fill($next,$direction),'time'=>$time];
        }
        if($position!==null){$last=$rows[array_key_last($rows)];$close((float)$last['close'],(int)$last['time'],'end-of-data');}
        $count=count($trades);
        return ['strategy'=>$method.' crossover '.$mode,'fast'=>$fast,'slow'=>$slow,'units'=>$units,'barCount'=>count($rows),
            'firstTimestamp'=>(int)$rows[0]['time'],'lastTimestamp'=>(int)$rows[array_key_last($rows)]['time'],
            'stopDistance'=>$stopDistance,'targetDistance'=>$targetDistance,'spreadAssumption'=>$spread,'slippageBps'=>$slippageBps,'roundTripFeePerLot'=>$feePerLot,'trades'=>$count,'wins'=>$wins,'losses'=>$losses,'breakeven'=>$count-$wins-$losses,
            'winRatePct'=>$count?$wins/$count*100:0,'netPnl'=>$equity,'grossProfit'=>$grossWin,'grossLoss'=>$grossLoss,
            'profitFactor'=>$grossLoss?$grossWin/$grossLoss:null,'maxClosedTradeDrawdown'=>$maxDd,'tradeList'=>array_slice($trades,-100)];
    }
    public static function optimize(array $candles,int $units,float $spread,float $slippage,float $fee,string $method,string $mode): array {
        $rows=self::confirmed($candles);$split=(int)floor(count($rows)*0.65);
        if($split<60||count($rows)-$split<55)throw new \InvalidArgumentException('At least 160 confirmed candles required for the research split');
        $train=array_slice($rows,0,$split);$holdout=array_slice($rows,$split);$candidates=[];
        foreach([[5,15],[8,21],[10,30],[15,40],[20,50]] as [$f,$s]){
            $r=self::run($train,$f,$s,$units,$spread,$slippage,$fee,$method,$mode);
            $candidates[]=['fast'=>$f,'slow'=>$s,'trainPnl'=>$r['netPnl'],'trades'=>$r['trades'],'drawdown'=>$r['maxClosedTradeDrawdown']];
        }
        usort($candidates,static fn($a,$b)=>($b['trainPnl']<=>$a['trainPnl'])?:($a['drawdown']<=>$b['drawdown']));
        $winner=$candidates[0];$result=self::run($holdout,$winner['fast'],$winner['slow'],$units,$spread,$slippage,$fee,$method,$mode);
        return ['method'=>'chronological 65/35 split; fixed candidate grid; independently warmed holdout','candidates'=>$candidates,'selected'=>$winner,'holdout'=>$result,'trainBars'=>count($train),'holdoutBars'=>count($holdout)];
    }
}
