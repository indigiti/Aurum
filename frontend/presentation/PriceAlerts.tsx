'use client';
import {useState} from 'react';
import type {Account} from '../domain/models';
import {money} from './format';
export default function PriceAlerts({account,busy,action,demo}:{account?:Account;busy:boolean;action:(body:Record<string,unknown>)=>Promise<void>;demo:boolean}){
 const [direction,setDirection]=useState('above');const [price,setPrice]=useState('');
 const alerts=Object.values(account?.alerts||{}).slice().reverse();
 return <section><h2>Price alerts</h2><p>One-time observed bid/ask thresholds. {demo?'Demo alerts are observed while the session is active.':'Unattended observation requires the configured PHP risk worker.'} No push or email delivery is implied.</p><div className="toolGrid"><label>Alert condition<select value={direction} onChange={e=>setDirection(e.target.value)}><option value="above">Bid at or above</option><option value="below">Ask at or below</option></select></label><label>Alert price<input type="number" min=".01" step=".01" value={price} onChange={e=>setPrice(e.target.value)}/></label></div><button className="gold" disabled={busy||!account||!(Number(price)>0)} onClick={()=>void action({action:'create-alert',direction,price,requestId:crypto.randomUUID()})}>Create price alert</button>{alerts.map(a=><div className="alertRow" key={a.id}><strong>{a.direction} {money(a.price)}</strong><span>{a.status}{a.triggeredAt?' · '+new Date(a.triggeredAt).toLocaleString():''}</span>{a.status==='armed'&&<button disabled={busy} onClick={()=>void action({action:'cancel-alert',id:a.id,requestId:crypto.randomUUID()})}>Cancel alert</button>}</div>)}</section>
}
