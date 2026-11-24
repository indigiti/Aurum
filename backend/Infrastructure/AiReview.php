<?php
declare(strict_types=1);
namespace Aurum;
final class AiReview {
    public static function enabled(): bool {
        return filter_var(aurum_config('AI_REVIEW_ENABLED',false),FILTER_VALIDATE_BOOLEAN)&&(string)aurum_config('AI_API_KEY','')!==''&&(string)aurum_config('AI_MODEL','')!=='';
    }
    public static function extract(array $r): string {
        $text=[];
        foreach($r['output']??[] as $item)if(($item['type']??'')==='message')foreach($item['content']??[] as $part)if(($part['type']??'')==='output_text'&&is_string($part['text']??null))$text[]=$part['text'];
        $out=trim(implode("\n",$text));if($out==='')throw new \RuntimeException('No review output');
        return substr($out,0,12000);
    }
    public static function review(array $metrics): string {
        if(!self::enabled())throw new \RuntimeException('AI review not configured');
        $payload=['model'=>(string)aurum_config('AI_MODEL'),'store'=>false,'max_output_tokens'=>1000,
            'instructions'=>'Review simulated trading performance for educational purposes. Money fields are integer cents. Distinguish observed metrics from inference. Identify data limitations and process improvements. Do not give trading signals, personalized investment recommendations, or promise returns. No tools or order access.',
            'input'=>json_encode($metrics,JSON_THROW_ON_ERROR)];
        $ch=curl_init('https://api.openai.com/v1/responses');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.aurum_config('AI_API_KEY'),'Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false]);
        $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
        if(!is_string($raw)||$status!==200)throw new \RuntimeException('Review provider unavailable');
        return self::extract(json_decode($raw,true,64,JSON_THROW_ON_ERROR));
    }
}
