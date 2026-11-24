'use client';
import {useEffect,useRef,useState,type SetStateAction} from 'react';
import {createChart,type ISeriesApi,type IPriceLine,type IChartApi,type UTCTimestamp} from 'lightweight-charts';
import type {Account,Candle as DomainCandle} from '../domain/models';
import type {Quote} from '../domain/quote';
import {indicators} from '../domain/indicators';
import {apiPath,type ApiClient} from '../application/ports';
import {intervals} from './format';
import {chartTheme} from './chartTheme';
import type {Theme} from './useAppearance';
type Candle=Omit<DomainCandle,'time'>&{time:UTCTimestamp};
export type Anchor={time:number;price:number};
export type DrawingKind='none'|'horizontal'|'trend'|'rectangle'|'fib'|'risk';
export type Drawing={kind:Exclude<DrawingKind,'none'|'horizontal'>;a:Anchor;b:Anchor};
type Saved={levels:number[];drawings:Drawing[]};
const empty:Saved={levels:[],drawings:[]};
const kinds=['trend','rectangle','fib','risk'];
function validAnchor(p:any):p is Anchor{return !!p&&Number.isFinite(p.time)&&p.time>0&&Number.isFinite(p.price)&&p.price>0}
export function useTradingChart(client:ApiClient,q:Quote|undefined,stream:string,bar:string,tab:string,account:Account|undefined,setNotice:(s:string)=>void,theme:Theme,storageKey='primary'){
 const chartRef=useRef<IChartApi|null>(null);const host=useRef<HTMLDivElement>(null);const series=useRef<ISeriesApi<'Candlestick'>|undefined>(undefined);
 const rowsRef=useRef<Candle[]>([]);const candle=useRef<Candle|undefined>(undefined);const lines=useRef<Record<string,ISeriesApi<'Line'>>>({});const priceLines=useRef<IPriceLine[]>([]);
 const [showSma,setShowSma]=useState(true);const [showEma,setShowEma]=useState(false);const [showBands,setShowBands]=useState(false);const [showVwap,setShowVwap]=useState(false);const [oscillator,setOscillator]=useState('none');
 const [drawingKind,setDrawingKind]=useState<DrawingKind>('none');const kindRef=useRef<DrawingKind>('none');const anchorRef=useRef<Anchor|null>(null);const [anchor,setAnchor]=useState<Anchor|null>(null);
 const [saved,setSaved]=useState<Record<string,Saved>>({});const [loaded,setLoaded]=useState(false);const [generation,setGeneration]=useState(0);const [,setViewRevision]=useState(0);const [indicatorData,setIndicatorData]=useState(()=>indicators([]));
 const state=saved[bar]??empty;const levels=state.levels;const drawings=state.drawings;
 function setLevels(value:SetStateAction<number[]>){setSaved(old=>{const s=old[bar]??empty;return {...old,[bar]:{...s,levels:typeof value==='function'?value(s.levels):value}}})}
 function setDrawing(v:SetStateAction<boolean>){setDrawingKind(old=>{const next=typeof v==='function'?v(old!=='none'):v;return next?'horizontal':'none'})}
 useEffect(()=>{try{const raw=JSON.parse(localStorage.getItem('aurum-chart-v4-'+storageKey)||'{}');const safe:Record<string,Saved>={};for(const key of Object.keys(intervals)){const v=raw.saved?.[key];if(v)safe[key]={levels:Array.isArray(v.levels)?v.levels.filter((x:any)=>Number.isFinite(x)&&x>0).slice(-20):[],drawings:Array.isArray(v.drawings)?v.drawings.filter((d:any)=>d&&kinds.includes(d.kind)&&validAnchor(d.a)&&validAnchor(d.b)).slice(-20):[]}}setSaved(safe);if(typeof raw.sma==='boolean')setShowSma(raw.sma);setShowEma(raw.ema===true);setShowBands(raw.bands===true);setShowVwap(raw.vwap===true);if(['none','RSI','MACD','ATR'].includes(raw.oscillator))setOscillator(raw.oscillator)}catch{}setLoaded(true)},[storageKey]);
 useEffect(()=>{if(loaded)try{localStorage.setItem('aurum-chart-v4-'+storageKey,JSON.stringify({saved,sma:showSma,ema:showEma,bands:showBands,vwap:showVwap,oscillator}))}catch{}},[loaded,saved,showSma,showEma,showBands,showVwap,oscillator,storageKey]);
 useEffect(()=>{kindRef.current=drawingKind;anchorRef.current=null;setAnchor(null)},[drawingKind,bar,tab]);
 const lineData=(name:string,points:{time:number;value:number}[])=>lines.current[name]?.setData(points.map(p=>({time:p.time as UTCTimestamp,value:p.value})));
 function refreshIndicators(){const rows=rowsRef.current;for(const period of [20,50])lineData('sma'+period,rows.slice(period-1).map((r,i)=>({time:r.time,value:rows.slice(i,i+period).reduce((n,c)=>n+c.close,0)/period})));let ema=0;lineData('ema',rows.map((r,i)=>{ema=i===0?r.close:r.close*2/21+ema*19/21;return {time:r.time,value:ema}}));const d=indicators(rows);lineData('upper',d.upper);lineData('lower',d.lower);lineData('vwap',d.vwap);setIndicatorData(d);setViewRevision(v=>v+1)}
 useEffect(()=>{
  if(tab!=='Terminal'||!host.current)return;const el=host.current;
  const chart=createChart(el,{width:el.clientWidth,height:Math.max(280,el.clientHeight),...chartTheme(theme)});chartRef.current=chart;
  const s=chart.addCandlestickSeries({upColor:'#2ccca0',downColor:'#ef6b77',borderVisible:false,wickUpColor:'#2ccca0',wickDownColor:'#ef6b77'});series.current=s;
  for(const [name,color] of Object.entries({sma20:'#e3b861',sma50:'#6ca6ef',ema:'#b18bea',upper:'#6b8eb7',lower:'#6b8eb7',vwap:'#f09d62'}))lines.current[name]=chart.addLineSeries({color,lineWidth:1,priceLineVisible:false,lastValueVisible:false,visible:false});
  rowsRef.current=[];priceLines.current=[];candle.current=undefined;setGeneration(v=>v+1);
  const redraw=()=>setViewRevision(v=>v+1);chart.timeScale().subscribeVisibleLogicalRangeChange(redraw);chart.subscribeCrosshairMove(redraw);
  chart.subscribeClick(p=>{const kind=kindRef.current;if(kind==='none'||!p.point)return;const raw=s.coordinateToPrice(p.point.y);if(raw===null||raw<=0)return;const price=Math.round(raw*100)/100;
   if(kind==='horizontal'){setSaved(old=>{const d=old[bar]??empty;return {...old,[bar]:{...d,levels:[...d.levels.slice(-19),price]}}});return}
   if(typeof p.time!=='number')return;const point={time:p.time,price};
   if(!anchorRef.current){anchorRef.current=point;setAnchor(point);return}
   const a=anchorRef.current;if(a.time===point.time)return;
   setSaved(old=>{const d=old[bar]??empty;return {...old,[bar]:{...d,drawings:[...d.drawings.slice(-19),{kind,a,b:point}]}}});anchorRef.current=null;setAnchor(null);
  });
  let active=true,initial=true;let loading=false;
  async function load(){if(loading)return;loading=true;try{const r=await client.request(apiPath('market')+'?action=candles&bar='+bar+'&limit=150');if(!r.ok||!Array.isArray(r.data.data))throw Error();if(active){const rows:Candle[]=r.data.data;const last=rows[rows.length-1];if(candle.current&&last&&candle.current.time>=last.time){if(candle.current.time===last.time){rows[rows.length-1]={...last,high:Math.max(last.high,candle.current.high),low:Math.min(last.low,candle.current.low),close:candle.current.close}}else rows.push(candle.current)}s.setData(rows);rowsRef.current=rows;refreshIndicators();candle.current=rows[rows.length-1];if(initial){chart.timeScale().fitContent();initial=false}}}catch{if(active)setNotice('Historical candles unavailable. Check OKX access and instrument availability.')}finally{loading=false}}void load();
  const timer=setInterval(()=>void load(),15000);const ro=new ResizeObserver(()=>{chart.applyOptions({width:el.clientWidth,height:Math.max(280,el.clientHeight)});redraw()});ro.observe(el);
  return()=>{active=false;clearInterval(timer);ro.disconnect();chart.timeScale().unsubscribeVisibleLogicalRangeChange(redraw);chart.unsubscribeCrosshairMove(redraw);chart.remove();chartRef.current=null;series.current=undefined;lines.current={};priceLines.current=[];candle.current=undefined};
 },[bar,tab,client]);
 useEffect(()=>{if(!q||!series.current||stream!=='live'||!candle.current)return;const t=(Math.floor(q.timestamp/1000/intervals[bar])*intervals[bar]) as UTCTimestamp;const old=candle.current;if(t<old.time)return;const next=t===old.time?{...old,high:Math.max(old.high,q.last),low:Math.min(old.low,q.last),close:q.last}:{time:t,open:q.last,high:q.last,low:q.last,close:q.last};series.current.update(next);const rows=rowsRef.current;if(rows.length&&rows[rows.length-1].time===t)rows[rows.length-1]=next;else rows.push(next);rowsRef.current=rows.slice(-300);refreshIndicators();candle.current=next},[q,stream,bar]);
 useEffect(()=>{chartRef.current?.applyOptions(chartTheme(theme))},[theme,generation]);
 useEffect(()=>{for(const [key,visible] of Object.entries({sma20:showSma,sma50:showSma,ema:showEma,upper:showBands,lower:showBands,vwap:showVwap}))lines.current[key]?.applyOptions({visible})},[showSma,showEma,showBands,showVwap,generation]);
 useEffect(()=>{const s=series.current;if(!s)return;for(const l of priceLines.current)s.removePriceLine(l);priceLines.current=[];for(const level of levels)priceLines.current.push(s.createPriceLine({price:level,color:'#c4a4fa',lineWidth:1,axisLabelVisible:true,title:'Level'}));for(const p of Object.values(account?.positions||{})){priceLines.current.push(s.createPriceLine({price:p.entry/100,color:'#55b7ce',lineWidth:1,axisLabelVisible:true,title:p.side.toUpperCase()+' entry'}));if(p.sl!==null)priceLines.current.push(s.createPriceLine({price:p.sl/100,color:'#ef6b77',lineWidth:1,axisLabelVisible:true,title:'SL'}));if(p.tp!==null)priceLines.current.push(s.createPriceLine({price:p.tp/100,color:'#2ccca0',lineWidth:1,axisLabelVisible:true,title:'TP'}))}},[levels,account,generation]);
 function coordinate(p:Anchor){const x=chartRef.current?.timeScale().timeToCoordinate(p.time as UTCTimestamp),y=series.current?.priceToCoordinate(p.price);return x==null||y==null?null:{x:Number(x),y:Number(y)}}
 return {host,showSma,setShowSma,showEma,setShowEma,showBands,setShowBands,showVwap,setShowVwap,oscillator,setOscillator,indicatorData,drawing:drawingKind!=='none',setDrawing,drawingKind,setDrawingKind,anchor,drawings,levels,setLevels,coordinate,clearDrawings:()=>setSaved(old=>({...old,[bar]:empty})),priceToY:(price:number)=>series.current?.priceToCoordinate(price)??null,yToPrice:(y:number)=>series.current?.coordinateToPrice(y)??null,fitChart:()=>chartRef.current?.timeScale().fitContent()};
}
