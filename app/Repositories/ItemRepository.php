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
        return $this->allForList(
            null,
            $activeOnly ? '1' : 'all',
            null,
            $activeOnly,
            null
        );
    }

    /**
     * Artikel-Liste mit optionalen Filtern (Admin-UI).
     *
     * @param 'all'|'1'|'0' $activeFilter
     * @param positive-int|null $supplierId nur Artikel mit Zuordnung zu diesem Lieferanten
     */
    public function allForList(
        ?int $locationId,
        string $activeFilter = 'all',
        ?string $search = null,
        bool $requireActiveLocation = false,
        ?int $supplierId = null
    ): array {
        $pdo = Database::pdo();
        $sql = 'SELECT i.*, l.name AS location_name FROM items i JOIN locations l ON l.id = i.location_id WHERE 1=1';
        $params = [];
        if ($locationId !== null && $locationId > 0) {
            $sql .= ' AND i.location_id = ?';
            $params[] = $locationId;
        }
        if ($activeFilter === '1' || $activeFilter === '0') {
            $sql .= ' AND i.active = ?';
            $params[] = (int) $activeFilter;
        }
        if ($requireActiveLocation) {
            $sql .= ' AND l.active = 1';
        }
        if ($supplierId !== null && $supplierId > 0) {
            $sql .= ' AND EXISTS (SELECT 1 FROM item_supplier isf WHERE isf.item_id = i.id AND isf.supplier_id = ?)';
            $params[] = $supplierId;
        }
        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (i.name LIKE ? OR i.unit LIKE ?)';
            $t = '%' . trim($search) . '%';
            $params[] = $t;
            $params[] = $t;
        }
        $sql .= ' ORDER BY l.sort_order ASC, i.sort_order ASC, i.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $byItem = $this->supplierNamesByItemIds($ids);
        foreach ($rows as &$row) {
            $row['supplier_names'] = $byItem[(int) $row['id']] ?? [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Lieferanten-Namen je Artikel, nach Priorität absteigend (höchste zuerst).
     *
     * @param list<int> $itemIds
     * @return array<int, list<string>>
     */
    public function supplierNamesByItemIds(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter($itemIds)));
        if ($itemIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT isup.item_id, s.name
             FROM item_supplier isup
             JOIN suppliers s ON s.id = isup.supplier_id
             WHERE isup.item_id IN ($placeholders)
             ORDER BY isup.item_id ASC, isup.priority DESC, s.name ASC"
        );
        $stmt->execute($itemIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $iid = (int) $r['item_id'];
            if (!isset($out[$iid])) {
                $out[$iid] = [];
            }
            $out[$iid][] = (string) $r['name'];
        }

        return $out;
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
        bool $active,
        int $sortOrder = 0,
        ?float $valuationPrice = null
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO items (name, unit, location_id, sort_order, min_stock, max_stock, valuation_price, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $unit,
            $locationId,
            $sortOrder,
            $minStock,
            $maxStock,
            $valuationPrice,
            $active ? 1 : 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function updateValuationPrice(int $id, ?float $valuationPrice): void
    {
        Database::pdo()->prepare('UPDATE items SET valuation_price = ? WHERE id = ?')
            ->execute([$valuationPrice, $id]);
    }

    public function update(
        int $id,
        string $name,
        string $unit,
        int $locationId,
        ?int $minStock,
        ?int $maxStock,
        bool $active,
        int $sortOrder = 0,
        ?float $valuationPrice = null
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE items SET name = ?, unit = ?, location_id = ?, sort_order = ?, min_stock = ?, max_stock = ?, valuation_price = ?, active = ? WHERE id = ?'
        );
        $stmt->execute([
            $name,
            $unit,
            $locationId,
            $sortOrder,
            $minStock,
            $maxStock,
            $valuationPrice,
            $active ? 1 : 0,
            $id,
        ]);
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

    /**
     * Aktive Artikel, deren ID nicht in der Liste vorkommt (für CSV-Roundtrip „fehlend in Datei“).
     *
     * @param list<int> $keepIds
     * @return list<array{id:int,name:string}>
     */
    public function findActiveNotInIds(array $keepIds): array
    {
        $keepIds = array_values(array_unique(array_filter(array_map('intval', $keepIds), static fn (int $x): bool => $x > 0)));
        if ($keepIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT id, name FROM items WHERE active = 1 AND id NOT IN ($placeholders) ORDER BY name ASC"
        );
        $stmt->execute($keepIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param list<int> $ids */
    public function deactivateByIds(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $x): bool => $x > 0)));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("UPDATE items SET active = 0 WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
}
