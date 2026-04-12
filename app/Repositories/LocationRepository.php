<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class LocationRepository
{
    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM locations';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        return Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM locations WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(string $name, int $sortOrder, bool $active): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO locations (name, sort_order, active) VALUES (?, ?, ?)'
        );
        $stmt->execute([$name, $sortOrder, $active ? 1 : 0]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, string $name, int $sortOrder, bool $active): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE locations SET name = ?, sort_order = ?, active = ? WHERE id = ?'
        );
        $stmt->execute([$name, $sortOrder, $active ? 1 : 0, $id]);
    }
}
