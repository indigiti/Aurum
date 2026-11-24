import {test} from 'node:test';
import assert from 'node:assert/strict';
import {indicators} from '../domain/indicators.ts';
import {riskSize} from '../domain/riskSizing.ts';
test('risk size respects stop budget, exposure and margin',()=>{
 const v=riskSize({equity:10000000,initial:10000000,dayBase:10000000,margin:0,used:990,maxUnits:1000,leverage:30,dailyLossPct:5,maxLossPct:10,entry:400025,stop:399000,side:'buy',riskPct:1});
 assert.equal(v.units,10);assert.equal(v.estimatedLoss,10250);
 assert.equal(riskSize({equity:10000000,initial:10000000,dayBase:10000000,margin:0,used:0,maxUnits:1000,leverage:30,dailyLossPct:5,maxLossPct:10,entry:400025,stop:401000,side:'buy',riskPct:1}),null);
});
test('indicators handle constant prices and real volume without inventing missing data',()=>{
 const rows=Array.from({length:60},(_,i)=>({time:1700000000+i*60,open:4000,high:4001,low:3999,close:4000,volume:10}));
 const d=indicators(rows);assert.equal(d.rsi.at(-1).value,50);assert.equal(d.atr.at(-1).value,2);assert.equal(d.upper.at(-1).value,4000);assert.equal(d.lower.at(-1).value,4000);assert.equal(d.vwap.at(-1).value,4000);assert.equal(d.macd.at(-1).value,0);
 assert.equal(indicators(rows.map(({volume,...r})=>r)).vwap.length,0);
});
