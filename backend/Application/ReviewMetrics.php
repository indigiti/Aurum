<?php
declare(strict_types=1);
namespace Aurum;
final class ReviewMetrics {
    public static function metrics(array $a): array {
        $summary=Analytics::summarize($a);
        return array_intersect_key($summary,array_flip(['closedTrades','wins','losses','winRatePct','grossProfit','grossLoss','netPnl','profitFactor','expectancy','maxObservedDrawdownPct','tradingDays']));
    }
}
