import {ColorType} from 'lightweight-charts';
import type {Theme} from './useAppearance';
export function chartTheme(theme:Theme){
 const light=theme==='light';
 const grid=light?'#e9ebef':'#23272e',border=light?'#dce0e6':'#2c3139';
 return {layout:{background:{type:ColorType.Solid,color:light?'#ffffff':'#101216'},textColor:light?'#606975':'#929ba9',fontFamily:'Inter, system-ui, sans-serif',fontSize:11},grid:{vertLines:{color:grid},horzLines:{color:grid}},rightPriceScale:{borderColor:border},timeScale:{borderColor:border,timeVisible:true,secondsVisible:false}};
}
