<?php
declare(strict_types=1);
namespace Aurum;
final class NewsCalendar {
    public static function validate(array $calendar): array {
        $validUntil=filter_var($calendar['validUntil']??null,FILTER_VALIDATE_INT);
        $events=$calendar['events']??null;
        if($validUntil===false||$validUntil<time()||$validUntil>time()+86400*90||!is_array($events)||count($events)>300)throw new \InvalidArgumentException('Calendar needs a validity deadline within 90 days and at most 300 events');
        $out=[];
        foreach($events as $e){
            if(!is_array($e))throw new \InvalidArgumentException('Invalid calendar event');
            $title=$e['title']??null;$ts=filter_var($e['timestamp']??null,FILTER_VALIDATE_INT);
            $before=filter_var($e['beforeMinutes']??5,FILTER_VALIDATE_INT);$after=filter_var($e['afterMinutes']??5,FILTER_VALIDATE_INT);
            if(!is_string($title)||trim($title)===''||strlen($title)>150||$ts===false||$ts<=0||$ts>$validUntil||$before===false||$after===false||$before<0||$before>120||$after<0||$after>120)throw new \InvalidArgumentException('Invalid event or restriction window');
            $out[]=['title'=>trim($title),'timestamp'=>$ts,'beforeMinutes'=>$before,'afterMinutes'=>$after];
        }
        usort($out,static fn($a,$b)=>$a['timestamp']<=>$b['timestamp']);
        return ['validUntil'=>$validUntil,'events'=>$out,'updatedAt'=>time()];
    }
    public static function decorate(array $q,array $calendar): array {
        $ts=intdiv($q['ts'],1000);$q['newsStatus']='clear';
        if(($calendar['validUntil']??0)<$ts){$q['newsStatus']='unconfigured-or-expired';return $q;}
        foreach($calendar['events']??[] as $e)if($ts>=$e['timestamp']-$e['beforeMinutes']*60&&$ts<=$e['timestamp']+$e['afterMinutes']*60){$q['newsStatus']='restricted';break;}
        return $q;
    }
}
