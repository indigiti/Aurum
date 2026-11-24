import {indicators} from '../domain/indicators';
type Data=ReturnType<typeof indicators>;
export default function IndicatorPanel({data,oscillator}:{data:Data;oscillator:string}){
 if(oscillator==='none')return null;
 const points=oscillator==='RSI'?data.rsi:oscillator==='ATR'?data.atr:data.macd;
 const other=oscillator==='MACD'?data.signal:[];const all=[...points,...other];const vals=all.map(p=>p.value);
 const lo=oscillator==='RSI'?0:Math.min(0,...vals),hi=oscillator==='RSI'?100:Math.max(1e-9,...vals);
 const start=points[0]?.time??0,end=points[points.length-1]?.time??start;
 const x=(t:number)=>(t-start)/Math.max(1,end-start)*600;const y=(v:number)=>62-(v-lo)/Math.max(1e-9,hi-lo)*56;
 const path=(p:{time:number;value:number}[])=>p.map(v=>x(v.time)+','+y(v.value)).join(' ');
 const last=points[points.length-1]?.value;
 return <div className="oscillatorPanel"><small>{oscillator==='RSI'?'RSI 14':oscillator==='ATR'?'ATR 14':'MACD 12/26/9'} · {last===undefined?'Insufficient bars':last.toFixed(2)}{oscillator==='MACD'&&other.length?' · Signal '+other[other.length-1].value.toFixed(2):''}</small><svg viewBox="0 0 600 68" preserveAspectRatio="none" aria-label={oscillator+' indicator history'}>{oscillator==='RSI'&&[30,70].map(v=><line key={v} x1="0" x2="600" y1={y(v)} y2={y(v)} stroke="#8c929b" strokeDasharray="3 3"/>)}<polyline points={path(points)} fill="none" stroke="#a991e6" strokeWidth="1.5" vectorEffect="non-scaling-stroke"/>{other.length>0&&<polyline points={path(other)} fill="none" stroke="#d9b56c" strokeWidth="1.5" vectorEffect="non-scaling-stroke"/>}</svg></div>
}
