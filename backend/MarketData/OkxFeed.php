<?php
declare(strict_types=1);
namespace Aurum\MarketData;

final class OkxFeed {
    public const SYMBOL = 'XAUUSD';
    public const INSTRUMENT = 'XAU-USDT-SWAP';
    private const BASE = 'https://www.okx.com/api/v5/market/';
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
    public function ticker(): array {
        $row=$this->request('ticker',[])[0];
        $bid=(float)($row['bidPx']??0); $ask=(float)($row['askPx']??0); $last=(float)($row['last']??0); $ts=(int)($row['ts']??0);
        if (($row['instId']??'')!==self::INSTRUMENT || $bid<=0 || $ask<$bid || $last<=0 || $ts<=0) throw new \RuntimeException('Invalid OKX quote');
        $age=(int)(microtime(true)*1000)-$ts;
        return ['symbol'=>self::SYMBOL,'instrument'=>self::INSTRUMENT,'provider'=>'OKX','quoteCurrency'=>'USDT','proxy'=>true,'bid'=>$bid,'ask'=>$ask,'last'=>$last,'timestamp'=>$ts,'stale'=>$age>10000||$age< -5000];
    }
    public function candles(string $bar='1m', int $limit=100): array {
        if (!in_array($bar,['1m','5m','15m','30m','1H','4H','1Dutc'],true) || $limit<1 || $limit>300) throw new \InvalidArgumentException('Invalid candle parameters');
        $rows=$this->request('candles',['bar'=>$bar,'limit'=>$limit]);
        $out=[];
        foreach(array_reverse($rows) as $r) {
            if(count($r)<9 || (int)$r[0]<=0) throw new \RuntimeException('Invalid candle');
            $out[]=['time'=>intdiv((int)$r[0],1000),'open'=>(float)$r[1],'high'=>(float)$r[2],'low'=>(float)$r[3],'close'=>(float)$r[4],'confirmed'=>$r[8]==='1'];
        }
        return $out;
    }
}
