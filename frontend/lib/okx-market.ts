export const SYMBOL = 'XAUUSD';
export const INSTRUMENT = 'XAU-USDT-SWAP';
export type Quote = {symbol:string; instrument:string; quoteCurrency:'USDT'; proxy:true; bid:number; ask:number; last:number; timestamp:number};
export function subscribeOkx(onQuote:(q:Quote)=>void,onStatus:(s:'connecting'|'live'|'stale'|'offline')=>void):()=>void {
  let socket:WebSocket|undefined, retry:ReturnType<typeof setTimeout>|undefined;
  let stopped=false, delay=1000, last=0, received=0;
  const connect=()=>{
    if(stopped)return;
    onStatus('connecting');
    socket=new WebSocket('wss://ws.okx.com/ws/v5/public');
    socket.onopen=()=>socket?.send(JSON.stringify({op:'subscribe',args:[{channel:'tickers',instId:INSTRUMENT}]}));
    socket.onmessage=(e)=>{
      received=Date.now();
      if(e.data==='pong')return;
      try {
        const m=JSON.parse(e.data); 
        if(m.event==='error'){socket?.close();return;}
        const r=m.data?.[0];
        if(r?.instId!==INSTRUMENT)return;
        const bid=Number(r.bidPx),ask=Number(r.askPx),price=Number(r.last),timestamp=Number(r.ts);
        if(![bid,ask,price,timestamp].every(Number.isFinite)||bid<=0||ask<bid||price<=0||timestamp<last||Math.abs(Date.now()-timestamp)>10000)return;
        last=timestamp;delay=1000;onStatus('live');
        onQuote({symbol:SYMBOL,instrument:INSTRUMENT,quoteCurrency:'USDT',proxy:true,bid,ask,last:price,timestamp});
      }catch{ /* Ignore malformed messages. */ }
    };
    socket.onerror=()=>socket?.close();
    socket.onclose=()=>{if(stopped)return;onStatus('offline');retry=setTimeout(connect,delay);delay=Math.min(30000,delay*2);};
    received=Date.now();
  };
  const heartbeat=setInterval(()=>{
    if(Date.now()-last>10000)onStatus('stale');
    if(socket?.readyState===WebSocket.OPEN && Date.now()-received>20000)socket.send('ping');
    if(socket?.readyState===WebSocket.OPEN && Date.now()-received>35000)socket.close();
  },5000);
  connect();
  return ()=>{stopped=true;clearInterval(heartbeat);if(retry)clearTimeout(retry);socket?.close();};
}
