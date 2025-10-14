<?php

declare(strict_types=1);

namespace HouzzHunt\Services;

use HouzzHunt\Repositories\LeadRepository;
use HouzzHunt\Repositories\UserRepository;
use HouzzHunt\Support\DateRange;
use HouzzHunt\Support\LeadStageClassifier;

final class AgentPerformanceService
{
    private LeadRepository $leadRepository;
    private UserRepository $userRepository;

    public function __construct(LeadRepository $leadRepository, UserRepository $userRepository)
    {
        $this->leadRepository = $leadRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array<string, mixed>>
     */
    public function topAgents(DateRange $range, array $context, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        $leads = $this->leadRepository->fetchLeads($range, $context);
        $agents = $this->userRepository->listUsers(['agent', 'manager']);

        $agentByName = [];
        foreach ($agents as $agent) {
            $name = strtolower(trim((string) ($agent['full_name'] ?? $agent['name'] ?? '')));
            if ($name !== '') {
                $agentByName[$name] = $agent;
            }
        }

        $aggregate = [];
        foreach ($leads as $lead) {
            $assigned = trim((string) ($lead['assigned_to'] ?? ''));
            if ($assigned === '') {
                continue;
            }

            $key = strtolower($assigned);
            if (!isset($aggregate[$key])) {
                $user = $agentByName[$key] ?? null;
                $aggregate[$key] = [
                    'agent_name' => $assigned,
                    'user_id' => $user['id'] ?? null,
                    'role' => $user['role'] ?? null,
                    'total_leads' => 0,
                    'closed_leads' => 0,
                ];
            }

            $aggregate[$key]['total_leads']++;
            if (LeadStageClassifier::isClosed($lead['stage'] ?? null)) {
                $aggregate[$key]['closed_leads']++;
            }
        }

        $agentsList = array_values($aggregate);

        usort($agentsList, static function (array $a, array $b): int {
            if ($a['closed_leads'] === $b['closed_leads']) {
                return $b['total_leads'] <=> $a['total_leads'];
            }

            return $b['closed_leads'] <=> $a['closed_leads'];
        });

        $agentsList = array_slice($agentsList, 0, $limit);

        return array_map(static function (array $agent) {
            $conversion = $agent['total_leads'] > 0
                ? round(($agent['closed_leads'] / $agent['total_leads']) * 100, 2)
                : 0.0;

            return [
                'user_id' => $agent['user_id'],
                'agent_name' => $agent['agent_name'],
                'role' => $agent['role'],
                'total_leads' => $agent['total_leads'],
                'closed_leads' => $agent['closed_leads'],
                'conversion_rate' => $conversion,
            ];
        }, $agentsList);
    }
}
