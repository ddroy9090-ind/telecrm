<?php

declare(strict_types=1);

namespace HouzzHunt\Repositories;

use HouzzHunt\Support\DateRange;
use HouzzHunt\Support\LeadVisibility;
use PDO;

final class ActivityRepository
{
    private PDO $pdo;
    private array $activityMap;
    private array $leadMap;

    public function __construct(PDO $pdo, array $datamap)
    {
        $this->pdo        = $pdo;
        $this->activityMap = $datamap['lead_activity'] ?? [];
        $this->leadMap     = $datamap['leads'] ?? [];
    }

    private function activityTable(): string
    {
        return $this->activityMap['table'] ?? 'lead_activity_log';
    }

    private function leadTable(): string
    {
        return $this->leadMap['table'] ?? 'all_leads';
    }

    /**
     * Fetch the most recent activities respecting visibility filters.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit, array $context): array
    {
        $limit = max(1, min($limit, 100));
        $activityTable = $this->activityTable();
        $leadTable     = $this->leadTable();
        $activityCols  = $this->activityMap['columns'] ?? [];
        $leadCols      = $this->leadMap['columns'] ?? [];

        $sql = sprintf(
            'SELECT a.%1$s AS id, a.%2$s AS lead_id, a.%3$s AS activity_type, a.%4$s AS description, a.%5$s AS metadata,' .
            ' a.%6$s AS created_by_name, a.%7$s AS created_at, l.%8$s AS lead_name' .
            ' FROM %9$s a INNER JOIN %10$s l ON a.%2$s = l.%11$s',
            $activityCols['id'] ?? 'id',
            $activityCols['lead_id'] ?? 'lead_id',
            $activityCols['type'] ?? 'activity_type',
            $activityCols['description'] ?? 'description',
            $activityCols['metadata'] ?? 'metadata',
            $activityCols['created_by_name'] ?? 'created_by_name',
            $activityCols['created_at'] ?? 'created_at',
            $leadCols['name'] ?? 'name',
            $activityTable,
            $leadTable,
            $leadCols['id'] ?? 'id'
        );

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $leadCols, 'l');
        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        $conditions = [];
        if ($visibilityClause !== '') {
            $conditions[] = $visibilityClause;
        }
        if ($sourceFilter !== '') {
            $conditions[] = sprintf('l.%s = :source_filter', $leadCols['source'] ?? 'source');
        }

        if (!empty($conditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= sprintf(' ORDER BY a.%s DESC, a.%s DESC LIMIT %d',
            $activityCols['created_at'] ?? 'created_at',
            $activityCols['id'] ?? 'id',
            $limit
        );

        $params = $visibilityParams;
        if ($sourceFilter !== '') {
            $params[':source_filter'] = $sourceFilter;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aggregate activities by weekday and hour.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array<string, mixed>>
     */
    public function heatmap(DateRange $range, array $context): array
    {
        $activityTable = $this->activityTable();
        $leadTable     = $this->leadTable();
        $activityCols  = $this->activityMap['columns'] ?? [];
        $leadCols      = $this->leadMap['columns'] ?? [];
        $createdAt     = $activityCols['created_at'] ?? 'created_at';

        $conditions = ['a.' . $createdAt . ' BETWEEN :start AND :end'];

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $leadCols, 'l');
        if ($visibilityClause !== '') {
            $conditions[] = $visibilityClause;
        }

        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        if ($sourceFilter !== '') {
            $conditions[] = sprintf('l.%s = :source_filter', $leadCols['source'] ?? 'source');
        }

        $sql = sprintf(
            'SELECT DAYOFWEEK(a.%1$s) AS weekday, HOUR(a.%1$s) AS hour_block, COUNT(*) AS total'
            . ' FROM %2$s a INNER JOIN %3$s l ON a.%4$s = l.%5$s'
            . ' WHERE %6$s'
            . ' GROUP BY weekday, hour_block ORDER BY weekday ASC, hour_block ASC',
            $createdAt,
            $activityTable,
            $leadTable,
            $activityCols['lead_id'] ?? 'lead_id',
            $leadCols['id'] ?? 'id',
            implode(' AND ', $conditions)
        );

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

    /**
     * Compute the first activity timestamp for each lead in range.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array{lead_id:int, first_activity:?string}>
     */
    public function firstActivityForLeads(DateRange $range, array $context): array
    {
        $activityTable = $this->activityTable();
        $leadTable     = $this->leadTable();
        $activityCols  = $this->activityMap['columns'] ?? [];
        $leadCols      = $this->leadMap['columns'] ?? [];
        $leadCreatedAt = $leadCols['created_at'] ?? 'created_at';

        $conditions = ['l.' . $leadCreatedAt . ' BETWEEN :start AND :end'];

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $leadCols, 'l');
        if ($visibilityClause !== '') {
            $conditions[] = $visibilityClause;
        }

        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        if ($sourceFilter !== '') {
            $conditions[] = sprintf('l.%s = :source_filter', $leadCols['source'] ?? 'source');
        }

        $sql = sprintf(
            'SELECT l.%1$s AS lead_id, MIN(a.%2$s) AS first_activity'
            . ' FROM %3$s l LEFT JOIN %4$s a ON a.%5$s = l.%1$s'
            . ' WHERE %6$s'
            . ' GROUP BY l.%1$s',
            $leadCols['id'] ?? 'id',
            $activityCols['created_at'] ?? 'created_at',
            $leadTable,
            $activityTable,
            $activityCols['lead_id'] ?? 'lead_id',
            implode(' AND ', $conditions)
        );

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

    /**
     * Determine the most recent activity timestamp for each lead in range.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array{lead_id:int, last_activity:?string}>
     */
    public function lastActivityForLeads(DateRange $range, array $context): array
    {
        $activityTable = $this->activityTable();
        $leadTable     = $this->leadTable();
        $activityCols  = $this->activityMap['columns'] ?? [];
        $leadCols      = $this->leadMap['columns'] ?? [];
        $leadCreatedAt = $leadCols['created_at'] ?? 'created_at';

        $conditions = ['l.' . $leadCreatedAt . ' BETWEEN :start AND :end'];

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $leadCols, 'l');
        if ($visibilityClause !== '') {
            $conditions[] = $visibilityClause;
        }

        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        if ($sourceFilter !== '') {
            $conditions[] = sprintf('l.%s = :source_filter', $leadCols['source'] ?? 'source');
        }

        $sql = sprintf(
            'SELECT l.%1$s AS lead_id, MAX(a.%2$s) AS last_activity'
            . ' FROM %3$s l LEFT JOIN %4$s a ON a.%5$s = l.%1$s'
            . ' WHERE %6$s'
            . ' GROUP BY l.%1$s',
            $leadCols['id'] ?? 'id',
            $activityCols['created_at'] ?? 'created_at',
            $leadTable,
            $activityTable,
            $activityCols['lead_id'] ?? 'lead_id',
            implode(' AND ', $conditions)
        );

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

    /**
     * Count activities grouped by lead id within range.
     *
     * @param array{role:string,user_id:int,user_name:?string} $context
     * @return array<int, array{lead_id:int,total:int}>
     */
    public function engagementCounts(DateRange $range, array $context): array
    {
        $activityTable = $this->activityTable();
        $leadTable     = $this->leadTable();
        $activityCols  = $this->activityMap['columns'] ?? [];
        $leadCols      = $this->leadMap['columns'] ?? [];
        $createdAt     = $activityCols['created_at'] ?? 'created_at';

        $conditions = ['a.' . $createdAt . ' BETWEEN :start AND :end'];

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $leadCols, 'l');
        if ($visibilityClause !== '') {
            $conditions[] = $visibilityClause;
        }

        $sourceFilter = isset($context['source_filter']) ? trim((string) $context['source_filter']) : '';
        if ($sourceFilter !== '') {
            $conditions[] = sprintf('l.%s = :source_filter', $leadCols['source'] ?? 'source');
        }

        $sql = sprintf(
            'SELECT a.%1$s AS lead_id, COUNT(*) AS total FROM %2$s a'
            . ' INNER JOIN %3$s l ON a.%1$s = l.%4$s'
            . ' WHERE %5$s'
            . ' GROUP BY a.%1$s',
            $activityCols['lead_id'] ?? 'lead_id',
            $activityTable,
            $leadTable,
            $leadCols['id'] ?? 'id',
            implode(' AND ', $conditions)
        );

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
