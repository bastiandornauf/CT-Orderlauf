<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Helpers\Units;
use PDO;

/**
 * Konkrete Artikel beim Lieferanten (Bezeichnung, Art.-Nr., Gebinde).
 * Pro (Artikel, Lieferant) wird im Editor der Standard-Artikel (is_default) gepflegt.
 * Zeilen werden nie gelöscht – auch nicht, wenn die Lieferantenzuordnung wegfällt.
 */
final class SupplierArticleRepository
{
    /** Felder, die der Editor bearbeitet */
    public const EDIT_FIELDS = ['supplier_label', 'supplier_sku', 'pack_size', 'pack_unit', 'order_unit'];

    /**
     * Standard-Artikel je Artikel und Lieferant.
     *
     * @param list<int> $itemIds leer = alle
     * @return array<int, array<int, array<string, mixed>>> item_id → supplier_id → Zeile
     */
    public function defaultsByItemIds(array $itemIds = []): array
    {
        $sql = 'SELECT * FROM supplier_articles WHERE is_default = 1 AND active = 1';
        $params = [];
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds !== []) {
            $sql .= ' AND item_id IN (' . implode(',', array_fill(0, count($itemIds), '?')) . ')';
            $params = $itemIds;
        }
        $sql .= ' ORDER BY id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int) $row['item_id']][(int) $row['supplier_id']] ??= self::normalizeRow($row);
        }

        return $out;
    }

    /**
     * Standard-Artikel eines Artikels anlegen oder aktualisieren.
     * Komplett leere Angaben legen keine neue Zeile an; eine vorhandene wird geleert.
     *
     * @param array<int, array<string, mixed>> $bySupplier supplier_id → Felder aus EDIT_FIELDS
     */
    public function saveDefaults(int $itemId, array $bySupplier): void
    {
        $pdo = Database::pdo();
        $find = $pdo->prepare(
            'SELECT id FROM supplier_articles
             WHERE item_id = ? AND supplier_id = ? AND is_default = 1 AND active = 1
             ORDER BY id ASC LIMIT 1'
        );
        $update = $pdo->prepare(
            'UPDATE supplier_articles
             SET supplier_label = ?, supplier_sku = ?, pack_size = ?, pack_unit = ?, order_unit = ?
             WHERE id = ?'
        );
        $insert = $pdo->prepare(
            'INSERT INTO supplier_articles
                (item_id, supplier_id, supplier_label, supplier_sku, pack_size, pack_unit, order_unit, is_default, active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)'
        );
        foreach ($bySupplier as $supplierId => $fields) {
            $supplierId = (int) $supplierId;
            if ($supplierId <= 0) {
                continue;
            }
            $v = self::cleanFields($fields);
            $find->execute([$itemId, $supplierId]);
            $existingId = $find->fetchColumn();
            if ($existingId !== false) {
                $update->execute([...array_values($v), (int) $existingId]);
            } elseif (array_filter($v, static fn ($x): bool => $x !== null) !== []) {
                $insert->execute([$itemId, $supplierId, ...array_values($v)]);
            }
        }
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{supplier_label:?string, supplier_sku:?string, pack_size:?float, pack_unit:?string, order_unit:?string}
     */
    public static function cleanFields(array $fields): array
    {
        $text = static function (mixed $v, int $max): ?string {
            $s = trim((string) ($v ?? ''));

            return $s === '' ? null : mb_substr($s, 0, $max);
        };
        $packSize = Units::parsePositiveDecimal($fields['pack_size'] ?? null);
        $packUnit = Units::normalizePackUnit($fields['pack_unit'] ?? null);
        if ($packSize === null || $packUnit === null) {
            // Gebinde nur vollständig speichern – halbe Angaben führen später zu falschen Umrechnungen.
            $packSize = null;
            $packUnit = null;
        }

        return [
            'supplier_label' => $text($fields['supplier_label'] ?? null, 255),
            'supplier_sku' => $text($fields['supplier_sku'] ?? null, 64),
            'pack_size' => $packSize,
            'pack_unit' => $packUnit,
            'order_unit' => $text($fields['order_unit'] ?? null, 64),
        ];
    }

    /** @param array<string, mixed> $row */
    private static function normalizeRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'item_id' => (int) $row['item_id'],
            'supplier_id' => (int) $row['supplier_id'],
            'supplier_label' => $row['supplier_label'],
            'supplier_sku' => $row['supplier_sku'],
            'pack_size' => $row['pack_size'] !== null ? (float) $row['pack_size'] : null,
            'pack_unit' => $row['pack_unit'],
            'order_unit' => $row['order_unit'],
        ];
    }
}
