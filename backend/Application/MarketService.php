<?php
declare(strict_types=1);
namespace Aurum;
final class MarketService {
    public function __construct(private MarketFeed $feed){}
    public function quote(Storage $store): array {return NewsCalendar::decorate(Engine::quote($this->feed->ticker()),CalendarSettings::current($store));}
    public function depth(): array{return $this->feed->depth();}
    public function ticker(): array{return $this->feed->ticker();}
    public function instrument(): array{return $this->feed->instrument();}
    public function candles(string $bar,int $limit): array{return $this->feed->candles($bar,$limit);}
    public const SYMBOL='XAUUSD';
    public const INSTRUMENT='XAU-USDT-SWAP';
}
