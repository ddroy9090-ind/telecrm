<?php

declare(strict_types=1);

namespace HouzzHunt\Repositories;

use HouzzHunt\Support\DateRange;
use HouzzHunt\Support\LeadVisibility;
use PDO;

final class LeadRepository
{
    private PDO $pdo;
    private array $map;

    public function __construct(PDO $pdo, array $datamap)
    {
        $this->pdo = $pdo;
        $this->map = $datamap['leads'] ?? [];
    }

    private function table(): string
    {
        return $this->map['table'] ?? 'all_leads';
    }

    /**
     * Fetch leads created within the provided range and respecting visibility filters.
     *
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     * @return array<int, array<string, mixed>>
     */
    public function fetchLeads(DateRange $range, array $context): array
    {
        return $this->fetchLeadsBetween(
            $range->getStart()->format('Y-m-d H:i:s'),
            $range->getEnd()->format('Y-m-d H:i:s'),
            $context
        );
    }

    /**
     * @param string $start inclusive datetime string
     * @param string $end inclusive datetime string
     * @param array{role:string,user_id:int,user_name:?string,agent_filter_id?:int,agent_filter_name?:?string} $context
     * @return array<int, array<string, mixed>>
     */
    public function fetchLeadsBetween(string $start, string $end, array $context): array
    {
        $table = $this->table();
        $columns = $this->map['columns'] ?? [];
        $createdAtColumn = $columns['created_at'] ?? 'created_at';

        $selectColumns = [
            $columns['id'] ?? 'id',
            $columns['stage'] ?? 'stage',
            $columns['rating'] ?? 'rating',
            $columns['assigned_to'] ?? 'assigned_to',
            $columns['source'] ?? 'source',
            $createdAtColumn,
            $columns['created_by_id'] ?? 'created_by',
            $columns['created_by_name'] ?? 'created_by_name',
        ];

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s BETWEEN :start AND :end',
            implode(', ', array_unique($selectColumns)),
            $table,
            $createdAtColumn
        );

        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        if ($sourceFilter !== '') {
            $sql .= sprintf(' AND %s = :source_filter', $columns['source'] ?? 'source');
        }

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $columns);

        if ($visibilityClause !== '') {
            $sql .= ' AND ' . $visibilityClause;
        }

        $sql .= sprintf(' ORDER BY %s DESC, %s DESC', $createdAtColumn, $columns['id'] ?? 'id');

        $params = array_merge(
            [
                ':start' => $start,
                ':end'   => $end,
            ],
            $visibilityParams
        );

        if ($sourceFilter !== '') {
            $params[':source_filter'] = $sourceFilter;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch aggregated lead counts grouped by source.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<array{source:?string,count:int}>
     */
    public function aggregateSources(DateRange $range, array $context): array
    {
        $table = $this->map['sources_table'] ?? $this->table();
        $columns = $this->map['columns'] ?? [];
        $createdAtColumn = $columns['created_at'] ?? 'created_at';
        $sourceColumn = $columns['source'] ?? 'source';

        $sql = sprintf(
            'SELECT %s AS source, COUNT(*) AS total FROM %s WHERE %s BETWEEN :start AND :end',
            $sourceColumn,
            $table,
            $createdAtColumn
        );

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $columns);
        if ($visibilityClause !== '') {
            $sql .= ' AND ' . $visibilityClause;
        }

        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        if ($sourceFilter !== '') {
            $sql .= ' AND ' . $sourceColumn . ' = :source_filter';
        }

        $sql .= sprintf(' GROUP BY %s ORDER BY total DESC', $sourceColumn);

        $params = array_merge(
            [
                ':start' => $range->getStart()->format('Y-m-d H:i:s'),
                ':end'   => $range->getEnd()->format('Y-m-d H:i:s'),
            ],
            $visibilityParams
        );

        if ($sourceFilter !== '') {
            $params[':source_filter'] = $sourceFilter;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
