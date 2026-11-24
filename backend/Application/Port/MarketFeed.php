<?php
declare(strict_types=1);
namespace Aurum;
interface MarketFeed {
    public function ticker(): array;
    public function instrument(): array;
    public function candles(string $bar='1m',int $limit=100): array;
}
