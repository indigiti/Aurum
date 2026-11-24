'use client';
import type {ApiClient} from '../application/ports';
import type {Quote} from '../domain/quote';
import type {Theme} from './useAppearance';
import {useTradingChart} from './useTradingChart';
import {intervals} from './format';
export default function SecondaryChart({client,q,stream,bar,setBar,theme,onNotice}:{client:ApiClient;q?:Quote;stream:string;bar:string;setBar:(v:string)=>void;theme:Theme;onNotice:(v:string)=>void}){
 const c=useTradingChart(client,q,stream,bar,'Terminal',undefined,onNotice,theme,'secondary');
 return <div className="secondaryChart"><div className="sectionhead"><h3>XAUUSD · secondary timeframe</h3><select aria-label="Secondary timeframe" value={bar} onChange={e=>setBar(e.target.value)}>{Object.keys(intervals).map(v=><option key={v}>{v}</option>)}</select><button onClick={c.fitChart}>Fit</button></div><div className="chartHost secondaryHost" ref={c.host}/></div>
}
