<?php
declare(strict_types=1);
namespace Aurum\MarketData;

final class OkxFeed {
    public const SYMBOL = 'XAUUSD';
    public const INSTRUMENT = 'XAU-USDT-SWAP';
    private const BASE = 'https://www.okx.com/api/v5/market/';
    private function cached(string $key,int $ttl,callable $fetch): array {
        $root=getenv('AURUM_PRIVATE_PATH')?:dirname(__DIR__,2);
        $dir=rtrim($root,'/').'/private-storage/market-cache';
        if(!is_dir($dir)&&!@mkdir($dir,0700,true)&&!is_dir($dir))return $fetch();
        $file=$dir.'/'.$key.'.json';$lock=fopen($file.'.lock','c');
        if(!$lock)return $fetch();
        try{
            if(!flock($lock,LOCK_EX))return $fetch();
            if(is_file($file)){
                $saved=json_decode((string)file_get_contents($file),true);
                if(is_array($saved)&&isset($saved['cachedAt'],$saved['data'])&&microtime(true)-(float)$saved['cachedAt']<=$ttl&&is_array($saved['data']))return $saved['data'];
            }
            $data=$fetch();$tmp=tempnam($dir,'feed-');
            if($tmp!==false){
                if(file_put_contents($tmp,json_encode(['cachedAt'=>microtime(true),'data'=>$data],JSON_THROW_ON_ERROR),LOCK_EX)!==false){chmod($tmp,0600);rename($tmp,$file);}
                if(is_file($tmp))unlink($tmp);
            }
            return $data;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private function request(string $path, array $params): array {
        $curl = curl_init(self::BASE . $path . '?' . http_build_query(['instId'=>self::INSTRUMENT]+$params));
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>false]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if (!is_string($body) || $status!==200) throw new \RuntimeException('OKX feed unavailable');
        $json=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if (($json['code']??null)!=='0' || !is_array($json['data']??null) || !$json['data']) throw new \RuntimeException('OKX data unavailable');
        return $json['data'];
    }
    private function publicRequest(string $path, array $params): array {
        $url='https://www.okx.com/api/v5/public/'.$path.'?'.http_build_query($params);
        $curl=curl_init($url);
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
        if(!is_string($body)||$status!==200)throw new \RuntimeException('OKX instrument metadata unavailable');
        $json=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if(($json['code']??null)!=='0'||!is_array($json['data']??null)||!$json['data'])throw new \RuntimeException('OKX instrument metadata unavailable');
        return $json['data'];
    }
    public function instrument(): array {
        return $this->cached('instrument',3600,function(){
        $row=$this->publicRequest('instruments',['instType'=>'SWAP','instId'=>self::INSTRUMENT])[0];
        $ctVal=(float)($row['ctVal']??0);$lotSz=(float)($row['lotSz']??0);$tickSz=(float)($row['tickSz']??0);
        if(($row['instId']??'')!==self::INSTRUMENT||($row['state']??'')!=='live'||($row['ctValCcy']??'')!=='XAU'||($row['settleCcy']??'')!=='USDT'||$ctVal<=0||$lotSz<=0||$tickSz<=0)throw new \RuntimeException('Unexpected OKX contract specification');
        return ['provider'=>'OKX','instrument'=>self::INSTRUMENT,'state'=>$row['state'],'contractType'=>$row['ctType']??null,
            'contractValue'=>$ctVal,'contractValueCurrency'=>$row['ctValCcy'],'lotSize'=>$lotSz,'minimumContracts'=>(float)($row['minSz']??$lotSz),
            'tickSize'=>$tickSz,'settlementCurrency'=>$row['settleCcy'],'contractsPerOunce'=>1/$ctVal,
            'simulatedLotOunces'=>100,'contractsPerSimulatedLot'=>100/$ctVal,
            'note'=>'Aurum simulated position sizes are ounces, not OKX contract counts.'];
        });
    }
    public function ticker(): array {
        return $this->cached('ticker',1,function(){
        $row=$this->request('ticker',[])[0];
        $bid=(float)($row['bidPx']??0); $ask=(float)($row['askPx']??0); $last=(float)($row['last']??0); $ts=(int)($row['ts']??0);
        if (($row['instId']??'')!==self::INSTRUMENT || $bid<=0 || $ask<$bid || $last<=0 || $ts<=0) throw new \RuntimeException('Invalid OKX quote');
        $age=(int)(microtime(true)*1000)-$ts;
        return ['symbol'=>self::SYMBOL,'instrument'=>self::INSTRUMENT,'provider'=>'OKX','quoteCurrency'=>'USDT','proxy'=>true,'bid'=>$bid,'ask'=>$ask,'last'=>$last,'timestamp'=>$ts,'stale'=>$age>10000||$age< -5000];
        });
    }
    public function candles(string $bar='1m', int $limit=100): array {
        if (!in_array($bar,['1m','5m','15m','30m','1H','4H','1Dutc'],true) || $limit<1 || $limit>300) throw new \InvalidArgumentException('Invalid candle parameters');
        return $this->cached('candles-'.$bar.'-'.$limit,15,function()use($bar,$limit){
            $rows=$this->request('candles',['bar'=>$bar,'limit'=>$limit]);
            $out=[];
            foreach(array_reverse($rows) as $r) {
                if(count($r)<9 || (int)$r[0]<=0) throw new \RuntimeException('Invalid candle');
                $out[]=['time'=>intdiv((int)$r[0],1000),'open'=>(float)$r[1],'high'=>(float)$r[2],'low'=>(float)$r[3],'close'=>(float)$r[4],'confirmed'=>$r[8]==='1'];
            }
            return $out;
        });
    }
}
