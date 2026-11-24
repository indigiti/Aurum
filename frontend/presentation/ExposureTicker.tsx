'use client';
import {useEffect,useState} from 'react';
import type {Account} from '../domain/models';
import type {Quote} from '../domain/quote';
import {money} from './format';
export default function ExposureTicker({account,q,stream,feed}:{account:Account;q?:Quote;stream:string;feed:string}){
 const [now,setNow]=useState(0);useEffect(()=>{setNow(Date.now());const id=setInterval(()=>setNow(Date.now()),1000);return()=>clearInterval(id)},[]);
 const e=account.exposure;if(!e)return null;
 const fresh=q&&stream==='live'&&Math.abs(now-q.timestamp)<10000;
 const pnl=fresh?Object.values(account.positions).reduce((sum,p)=>sum+(p.side==='buy'?Math.round(q.bid*100)-p.entry:p.entry-Math.round(q.ask*100))*p.units,0):null;
 const live=feed==='live'&&e.quoteTs!=null&&Math.abs(now-e.quoteTs)<15000;
 const sign=(v:number|null)=>v==null?'—':(v>0?'+':'')+money(v);
 return <div className="exposureTicker" aria-label="Exposure ticker"><span title="Gross open lots">Gross <b>{(e.grossUnits/100).toFixed(2)}</b></span><span title="Effective net lots">Net <b className={e.netUnits>0?'positive':e.netUnits<0?'negative':''}>{(e.netUnits/100).toFixed(2)}</b></span><span title="Matched lots per side">Matched <b>{(e.matchedUnits/100).toFixed(2)}</b></span><span title="Authoritative server observed floating P&L">Server P&amp;L <b className={(e.pnl??0)<0?'negative':'positive'}>{sign(live?e.pnl:null)}</b></span><span title="Indicative browser quote only, not execution or risk authority">Live estimate <b className={(pnl??0)<0?'negative':'positive'}>{sign(pnl)}</b></span></div>
}
