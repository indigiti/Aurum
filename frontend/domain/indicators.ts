type Bar={time:number;open:number;high:number;low:number;close:number;volume?:number};
type Point={time:number;value:number};
export function indicators(rows:Bar[]){
 const upper:Point[]=[],lower:Point[]=[],vwap:Point[]=[],rsi:Point[]=[],atr:Point[]=[],macd:Point[]=[],signal:Point[]=[];
 let gain=0,loss=0,range=0,ema12=0,ema26=0,ema9=0,volume=0,pv=0,day=-1;
 rows.forEach((r,i)=>{
  const prev=rows[i-1];const tr=Math.max(r.high-r.low,prev?Math.abs(r.high-prev.close):0,prev?Math.abs(r.low-prev.close):0);
  if(i<14)range+=tr;else range=(range*13+tr)/14;
  if(i===13)range/=14;if(i>=13)atr.push({time:r.time,value:range});
  if(prev){const change=r.close-prev.close,g=Math.max(change,0),l=Math.max(-change,0);
   if(i<=14){gain+=g;loss+=l;if(i===14){gain/=14;loss/=14}}else{gain=(gain*13+g)/14;loss=(loss*13+l)/14}
   if(i>=14)rsi.push({time:r.time,value:gain===0&&loss===0?50:loss===0?100:100-100/(1+gain/loss)});
  }
  ema12=i===0?r.close:r.close*2/13+ema12*11/13;ema26=i===0?r.close:r.close*2/27+ema26*25/27;
  const diff=ema12-ema26;ema9=i===0?diff:diff*.2+ema9*.8;
  if(i>=25){macd.push({time:r.time,value:diff});signal.push({time:r.time,value:ema9})}
  if(i>=19){const sample=rows.slice(i-19,i+1);const mean=sample.reduce((s,x)=>s+x.close,0)/20;const sd=Math.sqrt(sample.reduce((s,x)=>s+(x.close-mean)**2,0)/20);upper.push({time:r.time,value:mean+2*sd});lower.push({time:r.time,value:mean-2*sd})}
  const utcDay=Math.floor(r.time/86400);if(day!==utcDay){day=utcDay;volume=0;pv=0}
  if(typeof r.volume==='number'&&Number.isFinite(r.volume)&&r.volume>0){volume+=r.volume;pv+=(r.high+r.low+r.close)/3*r.volume;if(volume>0)vwap.push({time:r.time,value:pv/volume})}
 });
 return {upper,lower,vwap,rsi,atr,macd,signal};
}
