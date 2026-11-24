import type {Quote} from '../domain/quote';
export type JsonResponse={ok:boolean;data:any};
export interface ApiClient {request(path:string,body?:Record<string,unknown>,csrf?:string,accountId?:string):Promise<JsonResponse>;}
export interface QuoteStream {subscribe(onQuote:(quote:Quote)=>void,onStatus:(status:string)=>void):()=>void;}
export const apiPath=(name:string)=>'./api/'+name+'.php';
