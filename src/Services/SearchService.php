<?php

declare(strict_types=1);

namespace HouzzHunt\Services;

use HouzzHunt\Support\LeadVisibility;
use PDO;
use PDOStatement;

final class SearchService
{
    private PDO $pdo;
    private array $map;

    public function __construct(PDO $pdo, array $datamap)
    {
        $this->pdo = $pdo;
        $this->map = $datamap;
    }

    /**
     * @param array{role:string,user_id:int,user_name:?string} $context
     */
    public function search(string $query, array $context, int $limitPerGroup = 5): array
    {
        $term = '%' . $query . '%';
        $results = [
            'leads' => $this->searchLeads($term, $context, $limitPerGroup),
            'projects' => $this->searchProjects($term, $limitPerGroup),
            'agents' => $this->searchAgents($term, $limitPerGroup),
            'activities' => $this->searchActivities($term, $context, $limitPerGroup),
        ];

        return $results;
    }

    private function searchLeads(string $term, array $context, int $limit): array
    {
        $leadMap = $this->map['leads'] ?? [];
        $table = $leadMap['table'] ?? 'all_leads';
        $columns = $leadMap['columns'] ?? [];
        $nameCol = $columns['name'] ?? 'name';
        $emailCol = $columns['email'] ?? 'email';
        $phoneCol = $columns['phone'] ?? 'phone';
        $idCol = $columns['id'] ?? 'id';

        $sql = sprintf(
            'SELECT %1$s AS id, %2$s AS name, %3$s AS email, %4$s AS phone, %5$s AS created_at'
            . ' FROM %6$s WHERE (%2$s LIKE :term OR %3$s LIKE :term OR %4$s LIKE :term)',
            $idCol,
            $nameCol,
            $emailCol,
            $phoneCol,
            $columns['created_at'] ?? 'created_at',
            $table
        );

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $columns);
        if ($visibilityClause !== '') {
            $sql .= ' AND ' . $visibilityClause;
        }

        $sql .= sprintf(' ORDER BY %s DESC LIMIT :limit', $columns['created_at'] ?? 'created_at');

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        foreach ($visibilityParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function searchProjects(string $term, int $limit): array
    {
        $projects = $this->map['projects'] ?? [];
        $table = $projects['table'] ?? 'properties_list';
        $columns = $projects['columns'] ?? [];
        $nameCol = $columns['name'] ?? 'project_name';
        $titleCol = $columns['title'] ?? 'property_title';
        $idCol = $columns['id'] ?? 'id';

        $sql = sprintf(
            'SELECT %1$s AS id, %2$s AS project_name, %3$s AS property_title, %4$s AS location'
            . ' FROM %5$s WHERE (%2$s LIKE :term OR %3$s LIKE :term)'
            . ' ORDER BY %4$s ASC LIMIT :limit',
            $idCol,
            $nameCol,
            $titleCol,
            $columns['location'] ?? 'property_location',
            $table
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function searchAgents(string $term, int $limit): array
    {
        $users = $this->map['users'] ?? [];
        $table = $users['table'] ?? 'users';
        $columns = $users['columns'] ?? [];
        $nameCol = $columns['name'] ?? 'full_name';
        $emailCol = $columns['email'] ?? 'email';
        $idCol = $columns['id'] ?? 'id';
        $roleCol = $columns['role'] ?? 'role';

        $sql = sprintf(
            'SELECT %1$s AS id, %2$s AS name, %3$s AS email, %4$s AS role'
            . ' FROM %5$s WHERE (%2$s LIKE :term OR %3$s LIKE :term)'
            . ' ORDER BY %2$s ASC LIMIT :limit',
            $idCol,
            $nameCol,
            $emailCol,
            $roleCol,
            $table
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function searchActivities(string $term, array $context, int $limit): array
    {
        $activityMap = $this->map['lead_activity'] ?? [];
        $leadMap = $this->map['leads'] ?? [];
        $activityTable = $activityMap['table'] ?? 'lead_activity_log';
        $leadTable = $leadMap['table'] ?? 'all_leads';
        $activityCols = $activityMap['columns'] ?? [];
        $leadCols = $leadMap['columns'] ?? [];

        $sql = sprintf(
            'SELECT a.%1$s AS id, a.%2$s AS activity_type, a.%3$s AS description, a.%4$s AS created_at,'
            . ' l.%5$s AS lead_id, l.%6$s AS lead_name'
            . ' FROM %7$s a INNER JOIN %8$s l ON a.%9$s = l.%5$s'
            . ' WHERE (a.%3$s LIKE :term)',
            $activityCols['id'] ?? 'id',
            $activityCols['type'] ?? 'activity_type',
            $activityCols['description'] ?? 'description',
            $activityCols['created_at'] ?? 'created_at',
            $leadCols['id'] ?? 'id',
            $leadCols['name'] ?? 'name',
            $activityTable,
            $leadTable,
            $activityCols['lead_id'] ?? 'lead_id'
        );

        [$visibilityClause, $visibilityParams] = LeadVisibility::build($context, $leadCols, 'l');
        if ($visibilityClause !== '') {
            $sql .= ' AND ' . $visibilityClause;
        }

        $sql .= sprintf(' ORDER BY a.%s DESC LIMIT :limit', $activityCols['created_at'] ?? 'created_at');

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':term', $term, PDO::PARAM_STR);
        foreach ($visibilityParams as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
