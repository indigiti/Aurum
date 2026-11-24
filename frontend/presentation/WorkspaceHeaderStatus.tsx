'use client';
import {useEffect,useRef} from 'react';
import {CheckCircle2,Info,RotateCcw,TriangleAlert,Wifi,WifiOff,X,FlaskConical} from 'lucide-react';
import HeaderIcon from './HeaderIcon';
type Props={demo:boolean;feed:string;stream:string;notice:string;busy:boolean;onReset:()=>void;onDismiss:()=>void};
export default function WorkspaceHeaderStatus({demo,feed,stream,notice,busy,onReset,onDismiss}:Props){
 const success=['Request completed','Admin action completed','Backtest completed','Simulated account created'].includes(notice);
 const message=useRef<HTMLDetailsElement>(null);
 useEffect(()=>{if(message.current)message.current.open=!!notice&&!success},[notice,success]);
 return <div className="headerContext" aria-label="Workspace status">
  <details className="headerMarket headerPopover"><summary className="headerIcon" title="Market and simulation information" aria-label="Market and simulation information" data-tooltip="Market and simulation information"><Info size={18}/></summary><div className="headerMarketDetails"><strong>Market and simulation information</strong><p>OKX XAU-USDT-SWAP is a USDT-quoted gold perpetual proxy, not spot XAU/USD. Virtual USD ledger assumes USDT/USD = 1. Stops and risk rules process only when the PHP API observes a fresh quote. 100 troy oz = 1 simulated lot.</p>{demo&&<p>Demo workspace starts with $100,000 virtual funds. Temporary private session, up to two hours. No real funds, payouts or saved user account. Orders and risk checks run on observed quotes while you use the app.</p>}</div></details>
  {demo&&<><span className="headerDemoBalance" title="Demo account · $100,000 virtual starting balance, not current equity" aria-label="Demo account · $100,000 virtual starting balance" data-tooltip="Demo account · virtual starting balance" tabIndex={0}><FlaskConical size={16} aria-hidden="true"/>$100K</span><HeaderIcon label="Reset demo" className="headerReset" disabled={busy} onClick={onReset}><RotateCcw size={18}/></HeaderIcon></>}
  {notice&&<><span className="headerSrOnly" role="status">{notice}</span><details ref={message} className={'headerPopover headerMessage '+(success?'positive':'headerWarning')}><summary className="headerIcon" aria-label="Request notification" title={notice} data-tooltip={notice}>{success?<CheckCircle2 size={18}/>:<TriangleAlert size={18}/>}</summary><div className="headerMarketDetails headerMessagePanel"><p>{notice}</p><HeaderIcon label="Dismiss message" onClick={onDismiss}><X size={17}/></HeaderIcon></div></details></>}
  <details className="headerPopover headerConnection"><summary className={'headerIcon status '+(stream==='live'&&feed==='live'?'live':'')} aria-label={'Chart stream: '+stream+'; execution feed: '+feed} title={'Chart: '+stream+' · Execution: '+feed} data-tooltip={'Chart: '+stream+' · Execution: '+feed}>{stream==='live'&&feed==='live'?<Wifi size={18}/>:<WifiOff size={18}/>}</summary><div className="headerMarketDetails"><strong>Market connectivity</strong><p>Browser chart stream: {stream}</p><p>Server execution feed: {feed}</p><p>Orders require a fresh server quote.</p></div></details>
 </div>
}
