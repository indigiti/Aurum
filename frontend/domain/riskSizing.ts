// All money and prices supplied in cents; position size is integer troy ounces.
export function riskSize(input:{equity:number;initial:number;dayBase:number;margin:number;used:number;maxUnits:number;leverage:number;dailyLossPct:number;maxLossPct:number;entry:number;stop:number;side:'buy'|'sell';riskPct:number}){
 const i=input;
 if(Object.values(i).some(v=>typeof v==='number'&&!Number.isFinite(v))||i.entry<=0||i.stop<=0||i.riskPct<=0||i.riskPct>100||i.leverage<=0||i.equity<=0)return null;
 const distance=i.side==='buy'?i.entry-i.stop:i.stop-i.entry;if(distance<=0)return null;
 const headroom=Math.max(0,Math.min(i.equity-(i.dayBase-i.initial*i.dailyLossPct/100),i.equity-(i.initial-i.initial*i.maxLossPct/100)));
 const budget=Math.max(0,Math.min(i.equity*i.riskPct/100,headroom));
 const units=Math.max(0,Math.floor(Math.min(budget/distance,(i.equity-i.margin)*i.leverage/i.entry,i.maxUnits-i.used,1000)));
 return {units,budget,estimatedLoss:units*distance,margin:Math.ceil(i.entry*units/i.leverage),distance};
}
