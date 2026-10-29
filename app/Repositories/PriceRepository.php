<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\Database;
use App\Helpers\Money;
use App\Helpers\Units;
use PDO;

/**
 * Preise je Lieferantenartikel. Jeder Eintrag ist ein eigener datierter Datensatz (Historie);
 * es wird nichts überschrieben. Derselbe Preis/Beleg kann nicht doppelt eingetragen werden.
 */
final class PriceRepository
{
    public const TYPES = ['known', 'purchase'];
    public const SOURCES = ['manual', 'shop', 'pricelist', 'invoice', 'import'];
    public const PER_UNITS = ['order', 'kg', 'g', 'l', 'ml', 'stk'];

    /**
     * @param array{supplier_article_id:int, price_type:string, amount:int, per_qty:int, per_unit:string,
     *              price_date:string, source:string, document_ref:?string, note:?string, is_net:bool} $p
     *        amount/per_qty in Skala 4 (Money)
     * @return array{id:int, created:bool} created = false, wenn der Eintrag schon existierte
     */
    public function add(array $p, ?int $userId): array
    {
        $key = self::dedupeKey($p);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO supplier_article_prices
                (supplier_article_id, price_type, amount, is_net, per_qty, per_unit, price_date, source, document_ref, note, dedupe_key, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $p['supplier_article_id'],
            $p['price_type'],
            Money::toDb($p['amount']),
            $p['is_net'] ? 1 : 0,
            Money::toDb($p['per_qty']),
            $p['per_unit'],
            $p['price_date'],
            $p['source'],
            $p['document_ref'],
            $p['note'],
            $key,
            $userId,
        ]);
        if ($stmt->rowCount() > 0) {
            return ['id' => (int) $pdo->lastInsertId(), 'created' => true];
        }
        $find = $pdo->prepare('SELECT id FROM supplier_article_prices WHERE dedupe_key = ?');
        $find->execute([$key]);

        return ['id' => (int) $find->fetchColumn(), 'created' => false];
    }

    /** Gleicher Artikel, Typ, Tag, Betrag, Basis und Beleg = derselbe Preis. */
    public static function dedupeKey(array $p): string
    {
        return sha1(implode('|', [
            $p['supplier_article_id'],
            $p['price_type'],
            $p['price_date'],
            $p['amount'],
            $p['per_qty'],
            $p['per_unit'],
            $p['is_net'] ? 'net' : 'gross',
            mb_strtolower(trim((string) ($p['document_ref'] ?? ''))),
        ]));
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, sa.item_id, sa.supplier_id FROM supplier_article_prices p
             JOIN supplier_articles sa ON sa.id = p.supplier_article_id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM supplier_article_prices WHERE id = ?')->execute([$id]);
    }

    /**
     * Alle Preise eines Artikels über alle Lieferantenartikel, neueste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForItem(int $itemId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, sa.supplier_id, sa.pack_size, sa.pack_unit, sa.order_unit, sa.is_default, i.unit AS item_unit
             FROM supplier_article_prices p
             JOIN supplier_articles sa ON sa.id = p.supplier_article_id
             JOIN items i ON i.id = sa.item_id
             WHERE sa.item_id = ?
             ORDER BY p.price_date DESC, p.id DESC'
        );
        $stmt->execute([$itemId]);

        return array_map(self::present(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Neuester Preis (bekannt oder Einkauf) je Standard-Lieferantenartikel – für die Anzeige beim Bestellen.
     *
     * @return array<int, array<int, array<string, mixed>>> item_id → supplier_id → Preis
     */
    public function latestForDefaults(?int $itemId = null): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, sa.item_id, sa.supplier_id, sa.pack_size, sa.pack_unit, sa.order_unit, i.unit AS item_unit
             FROM supplier_article_prices p
             JOIN supplier_articles sa ON sa.id = p.supplier_article_id AND sa.is_default = 1 AND sa.active = 1
             JOIN items i ON i.id = sa.item_id'
            . ($itemId !== null ? ' WHERE sa.item_id = ?' : '')
            . ' ORDER BY p.price_date DESC, p.id DESC'
        );
        $stmt->execute($itemId !== null ? [$itemId] : []);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['item_id']][(int) $r['supplier_id']] ??= self::present($r);
        }

        return $out;
    }

    /**
     * Preiszeile für API/Anzeige inkl. Vergleichspreis. Beträge als Strings ("24,80"), nie float.
     *
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    public static function present(array $r): array
    {
        $amount = Money::fromDb($r['amount']) ?? 0;
        $perQty = Money::fromDb($r['per_qty']) ?? Money::SCALE;
        $cmp = Units::comparisonPrice(
            $amount,
            $perQty,
            (string) $r['per_unit'],
            Money::fromDb($r['pack_size'] ?? null),
            $r['pack_unit'] ?? null
        );
        $orderUnit = trim((string) ($r['order_unit'] ?? '')) ?: trim((string) ($r['item_unit'] ?? ''));

        return [
            'id' => (int) $r['id'],
            'supplier_article_id' => (int) $r['supplier_article_id'],
            'supplier_id' => isset($r['supplier_id']) ? (int) $r['supplier_id'] : null,
            'price_type' => (string) $r['price_type'],
            'amount' => Money::formatDe($amount),
            'per_qty' => rtrim(rtrim(Money::formatDe($perQty, 4), '0'), ','),
            'per_unit' => (string) $r['per_unit'],
            'per_label' => $r['per_unit'] === 'order' ? ($orderUnit !== '' ? $orderUnit : 'Einheit') : (Units::BASE_LABELS[$r['per_unit']] ?? $r['per_unit']),
            'price_date' => (string) $r['price_date'],
            'source' => (string) $r['source'],
            'document_ref' => $r['document_ref'],
            'note' => $r['note'] ?? null,
            'is_net' => (bool) $r['is_net'],
            'comparison' => $cmp === null ? null : [
                'amount' => Money::formatDe($cmp['amount']),
                'base' => Units::BASE_LABELS[$cmp['base']],
            ],
        ];
    }
}
