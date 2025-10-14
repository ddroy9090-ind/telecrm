<?php

declare(strict_types=1);

namespace HouzzHunt\Repositories;

use HouzzHunt\Support\DateRange;
use PDO;

final class ProjectRepository
{
    private PDO $pdo;
    private array $map;
    private SchemaInspector $schemaInspector;

    public function __construct(PDO $pdo, array $datamap, SchemaInspector $inspector)
    {
        $this->pdo = $pdo;
        $this->map = $datamap['projects'] ?? [];
        $this->schemaInspector = $inspector;
    }

    private function table(): string
    {
        return $this->map['table'] ?? 'properties_list';
    }

    /**
     * Summarise project inventory for the dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inventorySummary(DateRange $range): array
    {
        $table = $this->table();
        $columns = $this->map['columns'] ?? [];
        $createdAtColumn = $columns['created_at'] ?? 'created_at';

        $selectColumns = [
            $columns['id'] ?? 'id',
            $columns['name'] ?? 'project_name',
            $columns['title'] ?? 'property_title',
            $columns['location'] ?? 'property_location',
            $columns['type'] ?? 'property_type',
            $columns['starting_price'] ?? 'starting_price',
            $columns['booking_percentage'] ?? 'booking_percentage',
            $columns['booking_amount'] ?? 'booking_amount',
            $columns['total_area'] ?? 'total_area',
        ];

        $optionalNumericColumns = [
            'total_units',
            'sold_units',
            'available_units',
            'avg_price_aed',
        ];

        foreach ($optionalNumericColumns as $columnKey) {
            if (isset($columns[$columnKey]) && $this->schemaInspector->columnExists($table, $columns[$columnKey])) {
                $selectColumns[] = $columns[$columnKey];
            }
        }

        $sql = sprintf(
            'SELECT %s FROM %s WHERE %s BETWEEN :start AND :end ORDER BY %s DESC, %s DESC',
            implode(', ', array_unique($selectColumns)),
            $table,
            $createdAtColumn,
            $createdAtColumn,
            $columns['id'] ?? 'id'
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':start' => $range->getStart()->format('Y-m-d H:i:s'),
            ':end'   => $range->getEnd()->format('Y-m-d H:i:s'),
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function (array $row) use ($columns) {
            $name = trim((string) ($row[$columns['name'] ?? 'project_name'] ?? ''));
            $title = trim((string) ($row[$columns['title'] ?? 'property_title'] ?? ''));
            $label = $name !== '' ? $name : ($title !== '' ? $title : 'Untitled Project');

            $startingPriceRaw = (string) ($row[$columns['starting_price'] ?? 'starting_price'] ?? '');
            $avgPrice = $this->parseCurrency($startingPriceRaw);

            $totalUnits = $this->extractInt($row, $columns, 'total_units');
            $soldUnits  = $this->extractInt($row, $columns, 'sold_units');
            $availableUnits = $this->extractInt($row, $columns, 'available_units');
            $progressPct = $this->parsePercentage($row[$columns['booking_percentage'] ?? 'booking_percentage'] ?? null);

            if ($totalUnits === null && $soldUnits !== null && $availableUnits !== null) {
                $totalUnits = $soldUnits + $availableUnits;
            }

            if ($soldUnits === null && $totalUnits !== null && $progressPct !== null) {
                $soldUnits = (int) round($totalUnits * ($progressPct / 100));
            }

            if ($availableUnits === null && $totalUnits !== null && $soldUnits !== null) {
                $availableUnits = max($totalUnits - $soldUnits, 0);
            }

            return [
                'project_name'  => $label,
                'location'      => $row[$columns['location'] ?? 'property_location'] ?? null,
                'property_type' => $row[$columns['type'] ?? 'property_type'] ?? null,
                'total_units'   => $totalUnits,
                'sold_units'    => $soldUnits,
                'available'     => $availableUnits,
                'avg_price'     => $avgPrice,
                'progress_pct'  => $progressPct,
            ];
        }, $rows);
    }

    private function parseCurrency(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $filtered = preg_replace('/[^0-9.]/', '', $value);
        if ($filtered === null || $filtered === '') {
            return null;
        }

        return (float) $filtered;
    }

    private function extractInt(array $row, array $columns, string $key): ?int
    {
        if (!isset($columns[$key])) {
            return null;
        }

        $columnName = $columns[$key];
        if (!$this->schemaInspector->columnExists($this->table(), $columnName)) {
            return null;
        }

        $value = $row[$columnName] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function parsePercentage($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $filtered = str_replace('%', '', $value);
            if ($filtered === '') {
                return null;
            }

            if (is_numeric($filtered)) {
                return (float) $filtered;
            }
        }

        return null;
    }
}
