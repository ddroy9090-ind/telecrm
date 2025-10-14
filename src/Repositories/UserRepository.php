<?php

declare(strict_types=1);

namespace HouzzHunt\Repositories;

use PDO;

final class UserRepository
{
    private PDO $pdo;
    private array $map;

    public function __construct(PDO $pdo, array $datamap)
    {
        $this->pdo = $pdo;
        $this->map = $datamap['users'] ?? [];
    }

    public function find(int $id): ?array
    {
        $table = $this->map['table'] ?? 'users';
        $columns = $this->map['columns'] ?? [];
        $idColumn = $columns['id'] ?? 'id';

        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', $table, $idColumn));
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Fetch all agents/managers/admins optionally filtered by role list.
     *
     * @param list<string> $roles
     * @return array<int, array<string, mixed>>
     */
    public function listUsers(array $roles = []): array
    {
        $table = $this->map['table'] ?? 'users';
        $sql   = sprintf('SELECT * FROM %s', $table);
        $params = [];

        if ($roles) {
            $roleColumn = $this->map['columns']['role'] ?? 'role';
            $placeholders = [];
            foreach ($roles as $index => $role) {
                $placeholder = ':role_' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $role;
            }
            $sql .= sprintf(' WHERE %s IN (%s)', $roleColumn, implode(', ', $placeholders));
        }

        $nameColumn = $this->map['columns']['name'] ?? 'full_name';
        $sql .= sprintf(' ORDER BY %s ASC', $nameColumn);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
