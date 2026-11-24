import type {Account} from '../domain/models';
import {money} from './format';
export default function RiskSummary({account}:{account:Account}){
 const dailyLimit=account.initial*(account.rules?.dailyLossPct??5)/100;
 const totalLimit=account.initial*(account.rules?.maxLossPct??10)/100;
 const target=account.phase<3?account.initial*(account.rules?.phaseTargets?.[account.phase-1]??(account.phase===1?8:5))/100:0;
 const daily=Math.max(0,account.dayBase-account.equity),total=Math.max(0,account.initial-account.equity),profit=Math.max(0,account.balance-account.initial);
 const rows=[{name:'Daily loss used',value:daily,limit:dailyLimit,risk:true},{name:'Total loss used',value:total,limit:totalLimit,risk:true},...(target?[{name:'Realized profit target',value:profit,limit:target,risk:false}]:[])];
 return <div className="riskSummary"><h3>Challenge monitor <span>Phase {account.phase}</span></h3>{rows.map(r=>{const pct=r.limit>0?Math.max(0,Math.min(100,r.value/r.limit*100)):0;return <div className="riskMetric" key={r.name}><div><small>{r.name}</small><strong>{money(r.value)} / {money(r.limit)}</strong></div><progress aria-label={r.name} className={r.risk&&pct>=80?'riskWarning':''} value={pct} max={100}/></div>})}<small>Based on observed server equity and configured rules.</small></div>
}
