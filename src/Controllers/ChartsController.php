<?php

declare(strict_types=1);

namespace HouzzHunt\Controllers;

use HouzzHunt\Services\ActivityService;
use HouzzHunt\Services\LeadStatsService;
use HouzzHunt\Support\DateRange;

final class ChartsController
{
    private LeadStatsService $leadStatsService;
    private ActivityService $activityService;

    public function __construct(LeadStatsService $leadStatsService, ActivityService $activityService)
    {
        $this->leadStatsService = $leadStatsService;
        $this->activityService = $activityService;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function leadSources(string $rangeParam, array $context, ?string $startDate = null, ?string $endDate = null): array
    {
        $range = DateRange::fromInput($rangeParam, $startDate, $endDate);
        $sources = $this->leadStatsService->leadSources($range, $context);

        return [
            'data' => $sources,
            'meta' => [
                'range' => $range->getLabel(),
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function activityHeatmap(string $rangeParam, array $context, ?string $startDate = null, ?string $endDate = null): array
    {
        $range = DateRange::fromInput($rangeParam, $startDate, $endDate);
        $heatmap = $this->activityService->heatmap($range, $context);

        return [
            'data' => $heatmap,
            'meta' => [
                'range' => $range->getLabel(),
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }
}
