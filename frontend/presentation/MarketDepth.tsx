'use client';
import {useState} from 'react';
import type {ApiClient} from '../application/ports';
import {useMarketDepth} from '../application/useMarketDepth';
export default function MarketDepth({client}:{client:ApiClient}){
 const [open,setOpen]=useState(false);const {depth,error}=useMarketDepth(client,open);
 return <details className="toolDetails depthPanel" onToggle={e=>setOpen(e.currentTarget.open)}><summary>Depth of market</summary><small>OKX XAU-USDT-SWAP order book · contract counts, not Aurum positions</small>{depth?<div className="depthGrid">{(['bids','asks'] as const).map(side=><div key={side}><h3>{side==='bids'?'Bids':'Asks'}</h3>{depth[side].slice(0,10).map((r,i)=><div key={i} className={side==='bids'?'positive':'negative'}><span>{r.price.toFixed(2)}</span><span>{r.contracts.toLocaleString()}</span></div>)}</div>)}</div>:<p>{error?'Depth unavailable or stale.':'Loading depth…'}</p>}{depth&&<small>Observed {new Date(depth.timestamp).toLocaleTimeString()}</small>}</details>
}
