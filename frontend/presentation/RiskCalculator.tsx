'use client';
import {useState} from 'react';
import type {Account} from '../domain/models';
import {riskSize} from '../domain/riskSizing';
import {money} from './format';
export default function RiskCalculator({account,feed,sl,orderType,pendingPrice,setUnits}:{account:Account;feed:string;sl:string;orderType:string;pendingPrice:string;setUnits:(n:number)=>void}){
 const [pct,setPct]=useState('0.5');const [direction,setDirection]=useState<'buy'|'sell'>('buy');
 const side=orderType==='market'?direction:orderType.startsWith('buy')?'buy':'sell';
 const e=account.exposure;const entry=orderType==='market'?(side==='buy'?e?.ask:e?.bid):Math.round(Number(pendingPrice)*100);
 const stop=Math.round(Number(sl)*100);
 const mark=side==='buy'?e?.bid:e?.ask;const stopValid=orderType!=='market'||(mark!=null&&(side==='buy'?stop<mark:stop>mark));
 const result=feed==='live'&&e&&e.quoteTs!=null&&entry&&stopValid?riskSize({equity:account.equity,initial:account.initial,dayBase:account.dayBase,margin:account.margin,used:e.grossUnits+e.pendingBuyUnits+e.pendingSellUnits,maxUnits:Number(account.rules.maxUnits??1000),leverage:Number(account.rules.leverage??30),dailyLossPct:Number(account.rules.dailyLossPct??5),maxLossPct:Number(account.rules.maxLossPct??10),entry,stop,side,riskPct:Number(pct)}):null;
 return <details className="toolDetails"><summary>Position-size calculator</summary><div className="toolGrid"><label>Risk budget (% equity)<input type="number" min=".01" max="100" step=".1" value={pct} onChange={ev=>setPct(ev.target.value)}/></label>{orderType==='market'&&<label>Risk calculation side<select value={direction} onChange={ev=>setDirection(ev.target.value as 'buy'|'sell')}><option value="buy">Buy</option><option value="sell">Sell</option></select></label>}</div>{result?<><p>Size: <strong>{(result.units/100).toFixed(2)} lots · {result.units} oz</strong> · Planned stop loss {money(result.estimatedLoss)} · Margin {money(result.margin)}</p><button disabled={!result.units} onClick={()=>setUnits(result.units)}>Use calculated size</button></>:<p>Enter a valid stop loss and wait for a fresh server quote.</p>}<small>Estimate only; gaps, spreads, other positions and risk rules can change actual losses. Order size is still validated by the server.</small></details>
}
