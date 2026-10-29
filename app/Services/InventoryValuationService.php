<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Money;
use App\Helpers\Units;
use PDO;

/**
 * Inventurbewertung nach dem letzten bestätigten Einkauf bis zum Stichtag.
 *
 * Regel: Je Artikel zählt der jüngste Preis vom Typ „purchase“ mit price_date <= Stichtag,
 * egal bei welchem Lieferanten. Katalog- oder Listenpreise werden nie verwendet.
 * Der Preis wird auf die Zähleinheit (items.unit) umgerechnet; geht das nicht eindeutig,
 * gibt es keinen Wert, sondern den Status „not_convertible“.
 */
final class InventoryValuationService
{
    public const OK = 'ok';
    public const NO_PURCHASE = 'no_purchase';
    public const NOT_CONVERTIBLE = 'not_convertible';

    /**
     * @param list<array<string, mixed>> $items Zeilen mit id, unit, base_unit, unit_factor
     * @return array<int, array<string, mixed>> item_id → Bewertung
     */
    public function forItems(array $items, string $stichtag): array
    {
        $latest = $this->latestPurchases($stichtag);
        $out = [];
        foreach ($items as $item) {
            $id = (int) $item['id'];
            $out[$id] = isset($latest[$id])
                ? self::value($item, $latest[$id])
                : ['status' => self::NO_PURCHASE];
        }

        return $out;
    }

    /**
     * Bewertung eines Artikels aus einem Einkaufspreis (öffentlich für Tests).
     *
     * @param array<string, mixed> $item
     * @param array<string, mixed> $p Preiszeile inkl. pack_size, pack_unit, order_unit, supplier_name
     * @return array<string, mixed>
     */
    public static function value(array $item, array $p): array
    {
        $amount = Money::fromDb($p['amount']) ?? 0;
        $perQty = Money::fromDb($p['per_qty']) ?? Money::SCALE;
        $itemUnit = trim((string) ($item['unit'] ?? ''));
        $orderUnit = trim((string) ($p['order_unit'] ?? ''));
        $perLabel = $p['per_unit'] === 'order'
            ? ($orderUnit !== '' ? $orderUnit : ($itemUnit !== '' ? $itemUnit : 'Einheit'))
            : (Units::BASE_LABELS[$p['per_unit']] ?? (string) $p['per_unit']);
        $source = [
            'supplier' => (string) ($p['supplier_name'] ?? ''),
            'price_date' => (string) $p['price_date'],
            'basis' => Money::formatDe($amount) . ' €/' . ($perQty !== Money::SCALE ? rtrim(rtrim(Money::formatDe($perQty, 4), '0'), ',') . ' ' : '') . $perLabel,
            'document_ref' => $p['document_ref'] ?? null,
        ];

        $unitPrice = null;
        $method = null;
        // 1) Preis gilt je Bestelleinheit und die ist die Zähleinheit → direkt
        if ($p['per_unit'] === 'order' && ($orderUnit === '' || mb_strtolower($orderUnit) === mb_strtolower($itemUnit))) {
            $unitPrice = Money::mulDiv($amount, Money::SCALE, $perQty);
            $method = 'direct';
        } else {
            // 2) Über €/kg, €/l, €/Stück und den Inhalt einer Zähleinheit
            $conv = self::itemConversion($item);
            $cmp = Units::comparisonPrice(
                $amount,
                $perQty,
                (string) $p['per_unit'],
                Money::fromDb($p['pack_size'] ?? null),
                $p['pack_unit'] ?? null
            );
            if ($conv !== null && $cmp !== null && $cmp['base'] === $conv['base']) {
                $unitPrice = Money::mulDiv($cmp['amount'], $conv['factor'], Money::SCALE);
                $method = 'converted';
            }
        }

        if ($unitPrice === null) {
            return ['status' => self::NOT_CONVERTIBLE] + $source;
        }

        return [
            'status' => self::OK,
            'method' => $method,
            'unit_price' => Money::formatDe($unitPrice),
            // ganzzahlig (Skala 4) für die Wertberechnung im Browser ohne Kommazahl-Fehler
            'unit_price_units' => $unitPrice,
        ] + $source;
    }

    /**
     * Inhalt einer Zähleinheit in kg/l/Stück: ausdrücklich gepflegt (unit_factor + base_unit)
     * oder ableitbar, wenn die Zähleinheit selbst kg, g, l, ml oder Stück ist.
     *
     * @return array{base: string, factor: int}|null factor in Skala 4
     */
    public static function itemConversion(array $item): ?array
    {
        $factor = Money::fromDb($item['unit_factor'] ?? null);
        $base = $item['base_unit'] ?? null;
        if ($factor !== null && $factor > 0 && isset(Units::BASE_LABELS[$base])) {
            return ['base' => $base, 'factor' => $factor];
        }
        $unit = Units::normalizePackUnit($item['unit'] ?? null);
        if ($unit === null) {
            return null;
        }
        // 1 g = 0,001 kg usw. – über comparisonPrice derselben Einheit ermitteln
        $one = Units::comparisonPrice(Money::SCALE, Money::SCALE, $unit, null, null);
        if ($one === null || $one['amount'] <= 0) {
            return null;
        }

        return ['base' => $one['base'], 'factor' => Money::mulDiv(Money::SCALE, Money::SCALE, $one['amount'])];
    }

    /** @return array<int, array<string, mixed>> item_id → jüngster Einkauf bis Stichtag */
    private function latestPurchases(string $stichtag): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT p.*, sa.item_id, sa.pack_size, sa.pack_unit, sa.order_unit, s.name AS supplier_name
             FROM supplier_article_prices p
             JOIN supplier_articles sa ON sa.id = p.supplier_article_id
             JOIN suppliers s ON s.id = sa.supplier_id
             WHERE p.price_type = 'purchase' AND p.price_date <= ?
             ORDER BY p.price_date DESC, p.id DESC"
        );
        $stmt->execute([$stichtag]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int) $r['item_id']] ??= $r;
        }

        return $out;
    }
}
