<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use PDO;

final class SupplierRepository
{
    /** @return list<array<string, mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM suppliers';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY name ASC';
        return Database::pdo()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM suppliers WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(
        string $name,
        ?string $email,
        string $orderType,
        ?string $emailTemplate,
        bool $active
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO suppliers (name, email, order_type, email_template, active) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $orderType, $emailTemplate, $active ? 1 : 0]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(
        int $id,
        string $name,
        ?string $email,
        string $orderType,
        ?string $emailTemplate,
        bool $active
    ): void {
        $stmt = Database::pdo()->prepare(
            'UPDATE suppliers SET name = ?, email = ?, order_type = ?, email_template = ?, active = ? WHERE id = ?'
        );
        $stmt->execute([$name, $email, $orderType, $emailTemplate, $active ? 1 : 0, $id]);
    }

    /** @return list<int> */
    public function deliveryWeekdays(int $supplierId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT weekday FROM supplier_delivery_days WHERE supplier_id = ? ORDER BY weekday'
        );
        $stmt->execute([$supplierId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $weekdays 1–7 */
    public function setDeliveryWeekdays(int $supplierId, array $weekdays): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM supplier_delivery_days WHERE supplier_id = ?')->execute([$supplierId]);
        $ins = $pdo->prepare('INSERT INTO supplier_delivery_days (supplier_id, weekday) VALUES (?, ?)');
        foreach (array_unique($weekdays) as $d) {
            $d = (int) $d;
            if ($d >= 1 && $d <= 7) {
                $ins->execute([$supplierId, $d]);
            }
        }
    }
}
