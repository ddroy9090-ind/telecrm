<?php

declare(strict_types=1);

namespace HouzzHunt\Services;

use HouzzHunt\Repositories\ChannelPartnerRepository;
use HouzzHunt\Repositories\LeadRepository;
use HouzzHunt\Repositories\UserRepository;
use HouzzHunt\Support\DateRange;
use HouzzHunt\Support\LeadStageClassifier;

final class LeadStatsService
{
    private LeadRepository $leadRepository;
    private UserRepository $userRepository;
    private ChannelPartnerRepository $channelPartnerRepository;

    public function __construct(
        LeadRepository $leadRepository,
        UserRepository $userRepository,
        ChannelPartnerRepository $channelPartnerRepository
    )
    {
        $this->leadRepository = $leadRepository;
        $this->userRepository = $userRepository;
        $this->channelPartnerRepository = $channelPartnerRepository;
    }

    /**
     * Compute lead counter statistics for the dashboard cards.
     *
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     */
    public function leadCounters(DateRange $range, array $context): array
    {
        $currentLeads = $this->leadRepository->fetchLeads($range, $context);
        $previousLeads = $this->leadRepository->fetchLeadsBetween(
            $range->getPreviousStart()->format('Y-m-d H:i:s'),
            $range->getPreviousEnd()->format('Y-m-d H:i:s'),
            $context
        );

        $currentCounts = $this->countLeadsByCategory($currentLeads);
        $previousCounts = $this->countLeadsByCategory($previousLeads);

        $currentPartners = $this->channelPartnerRepository->countInRange($range);
        $previousPartners = $this->channelPartnerRepository->countBetween(
            $range->getPreviousStart()->format('Y-m-d H:i:s'),
            $range->getPreviousEnd()->format('Y-m-d H:i:s')
        );

        return [
            'total_leads' => $this->formatCounter($currentCounts['total'], $previousCounts['total']),
            'hot_active' => $this->formatCounter($currentCounts['hot_active'], $previousCounts['hot_active']),
            'closed_leads' => $this->formatCounter($currentCounts['closed'], $previousCounts['closed']),
            'channel_partners' => $this->formatCounter($currentPartners, $previousPartners),
        ];
    }

    /**
     * Group leads by source for the donut chart.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array{source:string,count:int,percentage:float}>
     */
    public function leadSources(DateRange $range, array $context): array
    {
        $rows = $this->leadRepository->aggregateSources($range, $context);
        $total = array_sum(array_map(static fn ($row) => (int) ($row['total'] ?? 0), $rows));

        if ($total === 0) {
            return [];
        }

        return array_map(static function (array $row) use ($total) {
            $source = trim((string) ($row['source'] ?? ''));
            if ($source === '') {
                $source = 'Unknown';
            }

            $count = (int) ($row['total'] ?? 0);
            $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0.0;

            return [
                'source' => $source,
                'count' => $count,
                'percentage' => $percentage,
            ];
        }, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $leads
     * @return array{total:int,hot_active:int,closed:int}
     */
    private function countLeadsByCategory(array $leads): array
    {
        $totals = [
            'total' => 0,
            'hot_active' => 0,
            'closed' => 0,
        ];

        foreach ($leads as $lead) {
            $totals['total']++;
            $stage = $lead['stage'] ?? null;
            $rating = $lead['rating'] ?? null;

            if (LeadStageClassifier::isActive($stage, $rating)) {
                $totals['hot_active']++;
            }

            if (LeadStageClassifier::isClosed($stage)) {
                $totals['closed']++;
            }
        }

        return $totals;
    }

    private function formatCounter(int $current, int $previous): array
    {
        $change = null;
        if ($previous > 0) {
            $change = round((($current - $previous) / $previous) * 100, 2);
        } elseif ($current > 0) {
            $change = null;
        }

        return [
            'value' => $current,
            'change_pct' => $change,
            'previous_value' => $previous,
        ];
    }
}
