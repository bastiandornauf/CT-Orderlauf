<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class ItemRepository
{
    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT i.*, l.name AS location_name FROM items i JOIN locations l ON l.id = i.location_id';
        if ($activeOnly) {
            $sql .= ' WHERE i.active = 1 AND l.active = 1';
        }
        $sql .= ' ORDER BY l.sort_order ASC, i.name ASC';
        return Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, l.name AS location_name FROM items i
 JOIN locations l ON l.id = i.location_id WHERE i.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM items WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(
        string $name,
        string $unit,
        int $locationId,
        ?int $minStock,
        ?int $maxStock,
        bool $active
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO items (name, unit, location_id, min_stock, max_stock, active)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $unit, $locationId, $minStock, $maxStock, $active ? 1 : 0]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        string $unit,
        int $locationId,
        ?int $minStock,
        ?int $maxStock,
        bool $active
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE items SET name = ?, unit = ?, location_id = ?, min_stock = ?, max_stock = ?, active = ? WHERE id = ?'
        );
        $stmt->execute([$name, $unit, $locationId, $minStock, $maxStock, $active ? 1 : 0, $id]);
    }

    /** @return list<array{item_id:int,supplier_id:int,priority:int}> */
    public function supplierLinksForItem(int $itemId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT item_id, supplier_id, priority FROM item_supplier WHERE item_id = ? ORDER BY priority DESC'
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function supplierLinksWithNames(int $itemId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT isup.*, s.name AS supplier_name FROM item_supplier isup
             JOIN suppliers s ON s.id = isup.supplier_id WHERE isup.item_id = ? ORDER BY isup.priority DESC'
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setSupplierLinks(int $itemId, array $pairs): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM item_supplier WHERE item_id = ?')->execute([$itemId]);
        $ins = $pdo->prepare(
            'INSERT INTO item_supplier (item_id, supplier_id, priority) VALUES (?, ?, ?)'
        );
        foreach ($pairs as $row) {
            $ins->execute([$itemId, (int) $row['supplier_id'], (int) $row['priority']]);
        }
    }
}
