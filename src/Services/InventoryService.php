<?php

declare(strict_types=1);

namespace HouzzHunt\Services;

use HouzzHunt\Repositories\ProjectRepository;
use HouzzHunt\Support\DateRange;

final class InventoryService
{
    private ProjectRepository $projectRepository;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projectRepository = $projectRepository;
    }

    public function summary(DateRange $range): array
    {
        $projects = $this->projectRepository->inventorySummary($range);

        $totalValue = 0.0;
        $valueSamples = 0;
        $progressSum = 0.0;
        $progressSamples = 0;

        foreach ($projects as &$project) {
            $totalUnits = $project['total_units'] ?? null;
            $soldUnits = $project['sold_units'] ?? null;
            $avgPrice = $project['avg_price'] ?? null;
            $progressPct = $project['progress_pct'];

            if ($progressPct === null && $totalUnits) {
                if ($soldUnits !== null) {
                    $progressPct = $totalUnits > 0 ? round(($soldUnits / $totalUnits) * 100, 2) : null;
                    $project['progress_pct'] = $progressPct;
                }
            }

            if ($avgPrice !== null && $totalUnits !== null) {
                $totalValue += $avgPrice * max($totalUnits, 0);
                $valueSamples++;
            }

            if ($project['progress_pct'] !== null) {
                $progressSum += (float) $project['progress_pct'];
                $progressSamples++;
            }
        }
        unset($project);

        $averageProgress = $progressSamples > 0 ? round($progressSum / $progressSamples, 2) : null;
        $computedValue = $valueSamples > 0 ? $totalValue : null;

        return [
            'projects' => $projects,
            'totals' => [
                'inventory_value' => $computedValue,
                'avg_sold_pct' => $averageProgress,
            ],
        ];
    }
}
