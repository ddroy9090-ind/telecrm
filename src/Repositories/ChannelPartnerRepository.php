<?php

declare(strict_types=1);

namespace HouzzHunt\Repositories;

use HouzzHunt\Support\DateRange;
use PDO;

final class ChannelPartnerRepository
{
    private PDO $pdo;
    private array $map;

    public function __construct(PDO $pdo, array $datamap)
    {
        $this->pdo = $pdo;
        $this->map = $datamap['channel_partners'] ?? [];
    }

    private function table(): string
    {
        return $this->map['table'] ?? 'channel_partners';
    }

    private function columns(): array
    {
        return $this->map['columns'] ?? [];
    }

    public function countInRange(DateRange $range, ?string $status = 'active'): int
    {
        return $this->countBetween(
            $range->getStart()->format('Y-m-d H:i:s'),
            $range->getEnd()->format('Y-m-d H:i:s'),
            $status
        );
    }

    public function countBetween(string $start, string $end, ?string $status = 'active'): int
    {
        $table = $this->table();
        $columns = $this->columns();
        $createdColumn = $columns['created_at'] ?? 'created_at';
        $statusColumn = $columns['status'] ?? 'status';

        $sql = sprintf('SELECT COUNT(*) FROM %s WHERE %s BETWEEN :start AND :end', $table, $createdColumn);
        $params = [
            ':start' => $start,
            ':end'   => $end,
        ];

        if ($status !== null) {
            $sql .= sprintf(' AND %s = :status', $statusColumn);
            $params[':status'] = $status;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
