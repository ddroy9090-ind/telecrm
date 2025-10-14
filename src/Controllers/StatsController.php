<?php

declare(strict_types=1);

namespace HouzzHunt\Controllers;

use HouzzHunt\Services\LeadStatsService;
use HouzzHunt\Services\PerformanceService;
use HouzzHunt\Support\DateRange;

final class StatsController
{
    private LeadStatsService $leadStatsService;
    private PerformanceService $performanceService;

    public function __construct(LeadStatsService $leadStatsService, PerformanceService $performanceService)
    {
        $this->leadStatsService = $leadStatsService;
        $this->performanceService = $performanceService;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     */
    public function leadCounters(string $rangeParam, array $context): array
    {
        $range = DateRange::fromPreset($rangeParam);
        $counters = $this->leadStatsService->leadCounters($range, $context);

        return [
            'data' => $counters,
            'meta' => [
                'range' => $range->getLabel(),
                'periods' => $range->getPeriodSummaries(),
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     */
    public function performance(string $rangeParam, array $context): array
    {
        $range = DateRange::fromPreset($rangeParam);
        $stats = $this->performanceService->performance($range, $context);

        return [
            'data' => $stats,
            'meta' => [
                'range' => $range->getLabel(),
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }
}
