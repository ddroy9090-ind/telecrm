<?php

declare(strict_types=1);

namespace HouzzHunt\Controllers;

use HouzzHunt\Services\ActivityService;

final class ActivitiesController
{
    private ActivityService $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function recent(array $context, int $limit): array
    {
        $activities = $this->activityService->recentActivities($context, $limit);

        return [
            'data' => $activities,
            'meta' => [
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }
}
