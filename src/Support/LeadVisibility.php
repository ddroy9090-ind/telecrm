<?php

declare(strict_types=1);

namespace HouzzHunt\Support;

final class LeadVisibility
{
    /**
     * Build SQL clause & params restricting leads visible to the current user.
     *
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     * @param array<string,string> $columns
     * @param string|null $alias Optional SQL alias prefix (e.g., 'l.')
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    public static function build(array $context, array $columns, ?string $alias = null): array
    {
        $clauses = [];
        $params  = [];

        $aliasPrefix = $alias !== null && $alias !== '' ? rtrim($alias, '.') . '.' : '';
        $assignedColumn = $aliasPrefix . ($columns['assigned_to'] ?? 'assigned_to');
        $createdByColumn = $aliasPrefix . ($columns['created_by_id'] ?? 'created_by');

        if (isset($context['agent_filter_id'], $context['agent_filter_name'])) {
            $clauses[] = sprintf('(%s = :filter_agent_name OR %s = :filter_agent_id)', $assignedColumn, $createdByColumn);
            $params[':filter_agent_name'] = $context['agent_filter_name'];
            $params[':filter_agent_id']   = $context['agent_filter_id'];

            return [implode(' OR ', $clauses), $params];
        }

        $role = $context['role'] ?? 'agent';
        $userId = $context['user_id'] ?? 0;
        $userName = $context['user_name'] ?? null;

        if ($role === 'admin') {
            return ['', []];
        }

        if ($userId <= 0) {
            return ['', []];
        }

        $ownerClause = sprintf('%s = :current_user_id', $createdByColumn);
        $params[':current_user_id'] = $userId;

        if ($userName !== null && trim($userName) !== '') {
            $clauses[] = sprintf('(%s = :current_agent_name OR %s)', $assignedColumn, $ownerClause);
            $params[':current_agent_name'] = $userName;
        } else {
            $clauses[] = $ownerClause;
        }

        return [implode(' OR ', $clauses), $params];
    }
}
