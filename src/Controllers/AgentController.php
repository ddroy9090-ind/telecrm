<?php

declare(strict_types=1);

namespace HouzzHunt\Controllers;

use HouzzHunt\Services\AgentPerformanceService;
use HouzzHunt\Support\DateRange;

final class AgentController
{
    private AgentPerformanceService $agentService;

    public function __construct(AgentPerformanceService $agentService)
    {
        $this->agentService = $agentService;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function topAgents(string $rangeParam, array $context, int $limit, ?string $startDate = null, ?string $endDate = null): array
    {
        $range = DateRange::fromInput($rangeParam, $startDate, $endDate);
        $agents = $this->agentService->topAgents($range, $context, $limit);

        return [
            'data' => $agents,
            'meta' => [
                'range' => $range->getLabel(),
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }
}
