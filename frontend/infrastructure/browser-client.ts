import type {ApiClient,QuoteStream} from '../application/ports';
import {subscribeOkx} from '../lib/okx-market';
export const browserApi:ApiClient={
 async request(path,body,csrf='',accountId='primary'){
  const headers:Record<string,string>={'X-Aurum-Account':accountId};
  if(body){headers['Content-Type']='application/json';headers['X-CSRF-Token']=csrf;}
  const r=await fetch(path,{method:body?'POST':'GET',credentials:'same-origin',headers,body:body?JSON.stringify(body):undefined,cache:'no-store'});
  return {ok:r.ok,data:await r.json()};
 }
};
export const quoteStream:QuoteStream={subscribe:subscribeOkx};
