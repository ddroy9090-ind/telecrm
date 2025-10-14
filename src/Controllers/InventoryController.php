<?php

declare(strict_types=1);

namespace HouzzHunt\Controllers;

use HouzzHunt\Services\InventoryService;
use HouzzHunt\Support\DateRange;

final class InventoryController
{
    private InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function projects(string $rangeParam): array
    {
        $range = DateRange::fromPreset($rangeParam);
        $summary = $this->inventoryService->summary($range);

        return [
            'data' => $summary['projects'],
            'meta' => [
                'totals' => $summary['totals'],
                'range' => $range->getLabel(),
                'generated_at' => gmdate(DATE_ATOM),
            ],
        ];
    }
}
