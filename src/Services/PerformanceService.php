<?php

declare(strict_types=1);

namespace HouzzHunt\Services;

use DateTimeImmutable;
use HouzzHunt\Repositories\ActivityRepository;
use HouzzHunt\Repositories\LeadRepository;
use HouzzHunt\Support\DateRange;
use HouzzHunt\Support\LeadStageClassifier;

final class PerformanceService
{
    private LeadRepository $leadRepository;
    private ActivityRepository $activityRepository;

    public function __construct(LeadRepository $leadRepository, ActivityRepository $activityRepository)
    {
        $this->leadRepository = $leadRepository;
        $this->activityRepository = $activityRepository;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     */
    public function performance(DateRange $range, array $context): array
    {
        $leads = $this->leadRepository->fetchLeads($range, $context);
        $firstActivities = $this->indexByLeadId(
            $this->activityRepository->firstActivityForLeads($range, $context),
            'lead_id',
            'first_activity'
        );
        $lastActivities = $this->indexByLeadId(
            $this->activityRepository->lastActivityForLeads($range, $context),
            'lead_id',
            'last_activity'
        );
        $engagements = $this->indexByLeadId(
            $this->activityRepository->engagementCounts($range, $context),
            'lead_id',
            'total'
        );

        $totalLeads = count($leads);
        $responseIntervals = [];
        $dealVelocity = [];
        $engagedCount = 0;
        $closedCount = 0;

        foreach ($leads as $lead) {
            $leadId = (int) ($lead['id'] ?? 0);
            $createdAt = isset($lead['created_at']) ? new DateTimeImmutable((string) $lead['created_at']) : null;
            if ($createdAt === null) {
                continue;
            }

            $firstActivity = $firstActivities[$leadId] ?? null;
            if ($firstActivity) {
                $firstActivityTime = new DateTimeImmutable($firstActivity);
                $hours = $this->hoursBetween($createdAt, $firstActivityTime);
                if ($hours !== null) {
                    $responseIntervals[] = $hours;
                }
            }

            $engagementTotal = isset($engagements[$leadId]) ? (int) $engagements[$leadId] : 0;
            if ($engagementTotal > 0) {
                $engagedCount++;
            }

            if (LeadStageClassifier::isClosed($lead['stage'] ?? null)) {
                $closedCount++;
                $closingActivity = $lastActivities[$leadId] ?? null;
                if ($closingActivity) {
                    $closingTime = new DateTimeImmutable($closingActivity);
                    $days = $this->daysBetween($createdAt, $closingTime);
                    if ($days !== null) {
                        $dealVelocity[] = $days;
                    }
                }
            }
        }

        $avgResponse = $responseIntervals ? round(array_sum($responseIntervals) / count($responseIntervals), 2) : null;
        $avgDealVelocity = $dealVelocity ? round(array_sum($dealVelocity) / count($dealVelocity), 2) : null;

        $conversionRate = $totalLeads > 0 ? round(($closedCount / $totalLeads) * 100, 2) : null;
        $closingDenominator = $engagedCount > 0 ? $engagedCount : $totalLeads;
        $closingRatio = $closingDenominator > 0 ? round(($closedCount / $closingDenominator) * 100, 2) : null;

        return [
            'target_achievement' => [
                'value' => $conversionRate,
                'unit' => 'pct',
                'status' => $conversionRate !== null ? 'ok' : 'no_data',
            ],
            'avg_response_time_hours' => [
                'value' => $avgResponse,
                'unit' => 'hours',
                'status' => $avgResponse !== null ? 'ok' : 'no_data',
            ],
            'lead_engagement_pct' => [
                'value' => $closingRatio,
                'unit' => 'pct',
                'status' => $closingRatio !== null ? 'ok' : 'no_data',
            ],
            'deal_velocity_days' => [
                'value' => $avgDealVelocity,
                'unit' => 'days',
                'status' => $avgDealVelocity !== null ? 'ok' : 'no_data',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, mixed>
     */
    private function indexByLeadId(array $rows, string $keyColumn, string $valueColumn): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $leadId = (int) ($row[$keyColumn] ?? 0);
            $indexed[$leadId] = $row[$valueColumn] ?? null;
        }

        return $indexed;
    }

    private function hoursBetween(DateTimeImmutable $start, DateTimeImmutable $end): ?float
    {
        if ($end < $start) {
            return null;
        }

        $diff = $start->diff($end);
        $hours = ($diff->days * 24) + $diff->h + ($diff->i / 60) + ($diff->s / 3600);

        return round($hours, 2);
    }

    private function daysBetween(DateTimeImmutable $start, DateTimeImmutable $end): ?float
    {
        if ($end < $start) {
            return null;
        }

        $diff = $start->diff($end);
        $days = $diff->days + ($diff->h / 24) + ($diff->i / (60 * 24));

        return round($days, 2);
    }
}
