<?php

declare(strict_types=1);

namespace HouzzHunt\Repositories;

use PDO;

final class SchemaInspector
{
    private PDO $pdo;
    private string $databaseName;
    private array $columnCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->databaseName = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
    }

    public function columnExists(string $table, string $column): bool
    {
        $tableKey = strtolower($table);
        if (!isset($this->columnCache[$tableKey])) {
            $stmt = $this->pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table'
            );
            $stmt->execute([
                ':schema' => $this->databaseName,
                ':table'  => $table,
            ]);
            $this->columnCache[$tableKey] = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        return in_array(strtolower($column), $this->columnCache[$tableKey], true);
    }
}
