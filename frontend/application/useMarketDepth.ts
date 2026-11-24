'use client';
import {useEffect,useState} from 'react';
import {apiPath,type ApiClient} from './ports';
type Depth={timestamp:number;bids:{price:number;contracts:number}[];asks:{price:number;contracts:number}[]};
export function useMarketDepth(client:ApiClient,enabled:boolean){
 const [depth,setDepth]=useState<Depth>();const [error,setError]=useState(false);
 useEffect(()=>{if(!enabled){setDepth(undefined);return}let active=true;let busy=false;const load=async()=>{if(busy)return;busy=true;try{const r=await client.request(apiPath('market')+'?action=depth');const d=r.data.data;if(!r.ok||!Array.isArray(d?.bids)||!Array.isArray(d?.asks)||Math.abs(Date.now()-d.timestamp)>10000)throw Error();if(active){setDepth(d);setError(false)}}catch{if(active){setDepth(undefined);setError(true)}}finally{busy=false}};void load();const id=setInterval(()=>void load(),2000);return()=>{active=false;clearInterval(id)}},[client,enabled]);
 return {depth,error};
}
