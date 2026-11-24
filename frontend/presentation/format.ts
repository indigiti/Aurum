export const money=(v:number)=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:2}).format(v/100);
export const price=(v?:number)=>v===undefined?'—':v.toFixed(2);
export const intervals:Record<string,number>={'1m':60,'5m':300,'15m':900,'30m':1800,'1H':3600,'4H':14400,'1Dutc':86400};
export const equityLine=(data:any[],key:'equity'|'balance')=>{if(!data.length)return '';const values=data.map(p=>Number(p[key]));const domain=data.flatMap(p=>[Number(p.equity),Number(p.balance)]);const min=Math.min(...domain),max=Math.max(...domain),span=Math.max(1,max-min);return values.map((v,i)=>`${20+i*760/Math.max(1,values.length-1)},${150-(v-min)/span*120}`).join(' ')};
