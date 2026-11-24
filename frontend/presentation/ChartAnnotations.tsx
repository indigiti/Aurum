import type {Drawing,Anchor} from './useTradingChart';
type Props={drawings:Drawing[];anchor:Anchor|null;coordinate:(p:Anchor)=>{x:number;y:number}|null};
export default function ChartAnnotations({drawings,anchor,coordinate}:Props){
 const p=anchor?coordinate(anchor):null;
 return <svg className="chartAnnotations" aria-hidden="true">
 {drawings.map((d,i)=>{const a=coordinate(d.a),b=coordinate(d.b);if(!a||!b)return null;const left=Math.min(a.x,b.x),width=Math.max(1,Math.abs(a.x-b.x)),top=Math.min(a.y,b.y),height=Math.max(1,Math.abs(a.y-b.y));
  if(d.kind==='trend')return <line key={i} x1={a.x} y1={a.y} x2={b.x} y2={b.y} stroke="#c5a7ef" strokeWidth="1.5"/>;
  if(d.kind==='rectangle')return <rect key={i} x={left} y={top} width={width} height={height} fill="#c5a7ef22" stroke="#c5a7ef"/>;
  if(d.kind==='fib')return <g key={i}>{[0,.236,.382,.5,.618,.786,1].map(f=>{const y=a.y+(b.y-a.y)*f;return <g key={f}><line x1={left} y1={y} x2={left+width} y2={y} stroke="#a9a1e8" strokeDasharray="4 3"/><text x={left+4} y={y-3} fill="#a9a1e8" fontSize="10">{f}</text></g>})}</g>;
  const target=coordinate({time:d.b.time,price:d.a.price+2*(d.a.price-d.b.price)});if(!target)return null;
  return <g key={i}><rect x={left} y={top} width={width} height={height} fill="#eb5f6a30" stroke="#eb5f6a"/><rect x={left} y={Math.min(a.y,target.y)} width={width} height={Math.max(1,Math.abs(a.y-target.y))} fill="#2cb99a30" stroke="#2cb99a"/><line x1={left} x2={left+width} y1={a.y} y2={a.y} stroke="#d9b56c"/><text x={left+3} y={a.y-5} fill="#d9b56c" fontSize="10">2R</text></g>
 })}
 {p&&<circle cx={p.x} cy={p.y} r="4" fill="#d9b56c"/>}
 </svg>
}
