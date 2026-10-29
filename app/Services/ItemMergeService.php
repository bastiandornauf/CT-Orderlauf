<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\MaintenanceLogRepository;
use PDO;

/**
 * Führt zwei Artikel zusammen. Der Zielartikel behält seine ID; der Quellartikel wird deaktiviert
 * und verweist per merged_into_id auf das Ziel. Es wird nichts gelöscht:
 * Bestelldaten und Preise wandern mit, Lieferanten der Quelle behalten ihre bisherige Bestellbezeichnung.
 * Alles in einer Transaktion; der Vorher-Stand beider Artikel steht im maintenance_log.
 */
final class ItemMergeService
{
    /** Felder, die im Dialog je Artikel gewählt werden können */
    public const FIELDS = [
        'name' => 'Name',
        'alias' => 'Alias',
        'unit' => 'Einheit',
        'location_id' => 'Lagerort',
        'sort_order' => 'Reihenfolge',
        'min_stock' => 'Mindestbestand',
        'max_stock' => 'Maximalbestand',
        'valuation_price' => 'Bewertungspreis',
        'unit_factor' => 'Inhalt je Einheit',
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, mixed>|null Artikel mit Lagerort, Zuordnungen, Bestelldaten und letzten Preisen */
    public function describe(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT i.*, l.name AS location_name FROM items i JOIN locations l ON l.id = i.location_id WHERE i.id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            return null;
        }
        $links = $this->pdo->prepare(
            'SELECT l.supplier_id, l.priority, s.name AS supplier_name, sa.supplier_label, sa.supplier_sku, sa.pack_size, sa.pack_unit
             FROM item_supplier l JOIN suppliers s ON s.id = l.supplier_id
             LEFT JOIN supplier_articles sa ON sa.item_id = l.item_id AND sa.supplier_id = l.supplier_id AND sa.is_default = 1 AND sa.active = 1
             WHERE l.item_id = ? ORDER BY l.priority DESC, s.name'
        );
        $links->execute([$id]);
        $item['links'] = $links->fetchAll(PDO::FETCH_ASSOC);
        $prices = $this->pdo->prepare(
            'SELECT COUNT(*) FROM supplier_article_prices p JOIN supplier_articles sa ON sa.id = p.supplier_article_id WHERE sa.item_id = ?'
        );
        $prices->execute([$id]);
        $item['price_count'] = (int) $prices->fetchColumn();

        return $item;
    }

    /**
     * @param array<string, 'source'|'target'> $choices Feld → woher der Wert kommt (fehlend = Ziel)
     */
    public function merge(int $sourceId, int $targetId, array $choices, ?int $userId): void
    {
        if ($sourceId === $targetId) {
            throw new \InvalidArgumentException('Ein Artikel kann nicht mit sich selbst zusammengeführt werden.');
        }
        $this->pdo->beginTransaction();
        try {
            $lock = $this->pdo->prepare('SELECT * FROM items WHERE id IN (?, ?) FOR UPDATE');
            $lock->execute([$sourceId, $targetId]);
            $rows = [];
            foreach ($lock->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[(int) $r['id']] = $r;
            }
            $source = $rows[$sourceId] ?? null;
            $target = $rows[$targetId] ?? null;
            if ($source === null || $target === null) {
                throw new \InvalidArgumentException('Artikel nicht gefunden.');
            }
            if ($source['merged_into_id'] !== null || $target['merged_into_id'] !== null) {
                throw new \InvalidArgumentException('Einer der Artikel wurde bereits zusammengeführt.');
            }

            (new MaintenanceLogRepository())->log('item_merged', $targetId, [
                'source' => $this->describe($sourceId),
                'target' => $this->describe($targetId),
                'choices' => $choices,
            ], $userId);

            // 1) Gewählte Felder der Quelle ins Ziel übernehmen
            $set = [];
            $params = [];
            foreach (array_keys(self::FIELDS) as $f) {
                if (($choices[$f] ?? 'target') !== 'source') {
                    continue;
                }
                $set[] = "{$f} = ?";
                $params[] = $source[$f];
                if ($f === 'unit_factor') {
                    $set[] = 'base_unit = ?';
                    $params[] = $source['base_unit'];
                }
            }
            if ($set !== []) {
                $params[] = $targetId;
                $this->pdo->prepare('UPDATE items SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);
            }

            // 2) Lieferanten, die bisher nur die Quelle hatten, bestellen weiter mit dem bisherigen Namen
            $this->pdo->prepare(
                "INSERT INTO supplier_articles (item_id, supplier_id, supplier_label, is_default, active)
                 SELECT ?, l.supplier_id, ?, 1, 1 FROM item_supplier l
                 WHERE l.item_id = ?
                   AND NOT EXISTS (SELECT 1 FROM item_supplier t WHERE t.item_id = ? AND t.supplier_id = l.supplier_id)
                   AND NOT EXISTS (SELECT 1 FROM supplier_articles sa WHERE sa.item_id = l.item_id AND sa.supplier_id = l.supplier_id AND sa.is_default = 1 AND sa.active = 1)"
            )->execute([$targetId, $source['name'], $sourceId, $targetId]);

            // 3) Bestelldaten (mit Preisen) umhängen. Hat das Ziel beim selben Lieferanten schon Standard-Bestelldaten,
            //    bleiben die der Quelle als zusätzliche (nicht Standard) erhalten.
            $this->pdo->prepare(
                'UPDATE supplier_articles sa
                 SET sa.is_default = IF(EXISTS (SELECT 1 FROM (SELECT supplier_id FROM supplier_articles
                         WHERE item_id = ? AND is_default = 1 AND active = 1) t WHERE t.supplier_id = sa.supplier_id), 0, sa.is_default),
                     sa.supplier_label = COALESCE(sa.supplier_label, ?),
                     sa.item_id = ?
                 WHERE sa.item_id = ?'
            )->execute([$targetId, $source['name'], $targetId, $sourceId]);

            // 4) Zuordnungen zusammenführen, höhere Priorität gewinnt
            $this->pdo->prepare(
                'INSERT INTO item_supplier (item_id, supplier_id, priority)
                 SELECT ?, s.supplier_id, s.priority FROM (SELECT supplier_id, priority FROM item_supplier WHERE item_id = ?) s
                 ON DUPLICATE KEY UPDATE priority = GREATEST(item_supplier.priority, VALUES(priority))'
            )->execute([$targetId, $sourceId]);

            // 5) Quelle stilllegen (nicht löschen); frühere Zusammenführungen auf die Quelle zeigen jetzt aufs Ziel
            $this->pdo->prepare('UPDATE items SET active = 0, merged_into_id = ? WHERE id = ?')->execute([$targetId, $sourceId]);
            $this->pdo->prepare('UPDATE items SET merged_into_id = ? WHERE merged_into_id = ?')->execute([$targetId, $sourceId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
