'use client';
import {CheckCircle2,Info,RotateCcw,X} from 'lucide-react';
type Props={demo:boolean;feed:string;notice:string;busy:boolean;onReset:()=>void;onDismiss:()=>void};
export default function WorkspaceHeaderStatus({demo,feed,notice,busy,onReset,onDismiss}:Props){
 const success=notice==='Request completed';
 return <div className="headerContext" aria-label="Workspace status">
  <details className="headerMarket"><summary title="Market and simulation information"><Info size={14}/><span>Simulation only</span><span className={'executionStatus '+(feed==='live'?'positive':'')}>Execution: {feed}</span></summary><div className="headerMarketDetails"><strong>Market and simulation information</strong><p>OKX XAU-USDT-SWAP is a USDT-quoted gold perpetual proxy, not spot XAU/USD. Virtual USD ledger assumes USDT/USD = 1. Stops and risk rules process only when the PHP API observes a fresh quote. 100 troy oz = 1 simulated lot.</p>{demo&&<p>Demo workspace starts with $100,000 virtual funds. Temporary private session, up to two hours. No real funds, payouts or saved user account. Orders and risk checks run on observed quotes while you use the app.</p>}</div></details>
  {demo&&<><span className="headerDemoBalance" title="Virtual starting balance, not current equity">$100K virtual start</span><button className="headerReset" disabled={busy} onClick={onReset}><RotateCcw size={13}/> Reset demo</button></>}
  {notice&&<div role="status" className={'headerNotice '+(success?'headerNoticeSuccess':'')}><span>{success?<CheckCircle2 size={14}/>:<Info size={14}/>}<span>{notice}</span></span><button title="Dismiss message" aria-label="Dismiss message" onClick={onDismiss}><X size={13}/></button></div>}
 </div>
}
