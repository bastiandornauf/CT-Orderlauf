<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Money;
use App\Repositories\PriceRepository;
use PDO;

/**
 * Datenqualitätsprüfungen für die Datenpflege. Jede Prüfung liefert betroffene aktive Artikel
 * mit kurzer Begründung. Es wird nur angezeigt – nichts wird automatisch geändert.
 */
final class ItemQualityService
{
    public const STALE_MONTHS = 6;
    /** Preissprung gegenüber dem Vorpreis desselben Lieferantenartikels (Kommafehler o. Ä.) */
    public const JUMP_FACTOR = 3;
    /** Abweichung zwischen Lieferanten beim Vergleichspreis (€/kg, €/l, €/Stück) */
    public const SPREAD_FACTOR = 1.5;

    /** Schreibweisen gleicher Einheiten (klein, ohne Punkt) → gemeinsamer Schlüssel */
    private const UNIT_SYNONYMS = [
        'ktn' => 'karton', 'kt' => 'karton', 'ka' => 'karton',
        'btl' => 'beutel', 'stk' => 'stück', 'st' => 'stück', 'stücke' => 'stück',
        'kilo' => 'kg', 'pk' => 'paket', 'pkg' => 'packung', 'fl' => 'flasche',
    ];

    /**
     * Definition aller Prüfungen: Schlüssel → [Titel, Art, Gruppe, Kurzhilfe]
     * Art: error = echter Fehler, hint = optionale Verbesserung
     */
    public const CHECKS = [
        'no_supplier' => ['Ohne aktiven Lieferanten', 'error', 'Stammdaten', 'Kann nicht bestellt werden.'],
        'duplicate_sku' => ['Gleiche Art.-Nr. mehrfach', 'error', 'Stammdaten', 'Dieselbe Art.-Nr. eines Lieferanten hängt an mehreren Artikeln – oft eine Dublette.'],
        'orphan_article' => ['Bestelldaten ohne Zuordnung', 'error', 'Stammdaten', 'Lieferant wurde entfernt, seine Bestelldaten sind noch da.'],
        'missing_sku' => ['Ohne Art.-Nr.', 'hint', 'Bestelldaten', 'Nur Lieferanten mit „Hat Artikelnummern“.'],
        'missing_pack' => ['Gebinde fehlt', 'hint', 'Bestelldaten', 'Ohne Gebinde kein Vergleichspreis.'],
        'unit_spelling' => ['Einheit uneinheitlich geschrieben', 'hint', 'Bestelldaten', 'z. B. Ktn statt Karton.'],
        'long_name' => ['Langer Name ohne Alias', 'hint', 'Bestelldaten', 'Ein kurzer Alias liest sich im Rundgang besser.'],
        'no_price' => ['Ohne Preis', 'hint', 'Preise', 'Kein bekannter Preis bei keinem Lieferanten.'],
        'stale_price' => ['Preis älter als 6 Monate', 'hint', 'Preise', 'Für Kalkulation und Inventur prüfen.'],
        'price_jump' => ['Auffälliger Preissprung', 'hint', 'Preise', 'Neuer Preis mindestens 3× höher/niedriger als der vorige – Tippfehler?'],
        'price_spread' => ['Große Preisunterschiede zwischen Lieferanten', 'hint', 'Preise', 'Vergleichspreis weicht um mehr als 50 % ab.'],
        'no_purchase' => ['Ohne Einkaufspreis', 'hint', 'Inventur', 'Inventur kann nicht nach Einkauf bewerten.'],
        'not_convertible' => ['Einkaufspreis nicht umrechenbar', 'hint', 'Inventur', '„Inhalt je Einheit“ oder Gebinde ergänzen.'],
    ];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** @return array<string, int> Schlüssel → Anzahl betroffener Artikel */
    public function counts(): array
    {
        $out = [];
        foreach (array_keys(self::CHECKS) as $key) {
            $out[$key] = count($this->run($key));
        }

        return $out;
    }

    /**
     * Betroffene Artikel einer Prüfung.
     *
     * @return array<int, string> item_id → Begründung (kann leer sein)
     */
    public function run(string $key): array
    {
        return match ($key) {
            'no_supplier' => $this->ids(
                'SELECT i.id FROM items i WHERE i.active = 1 AND NOT EXISTS (
                    SELECT 1 FROM item_supplier l JOIN suppliers s ON s.id = l.supplier_id AND s.active = 1 WHERE l.item_id = i.id)'
            ),
            'duplicate_sku' => $this->details(
                "SELECT i.id, CONCAT(s.name, ' ', sa.supplier_sku, ': auch bei ', GROUP_CONCAT(DISTINCT o.name SEPARATOR ', ')) AS d
                 FROM supplier_articles sa
                 JOIN items i ON i.id = sa.item_id AND i.active = 1
                 JOIN suppliers s ON s.id = sa.supplier_id
                 JOIN supplier_articles sb ON sb.supplier_id = sa.supplier_id AND sb.supplier_sku = sa.supplier_sku AND sb.item_id <> sa.item_id AND sb.active = 1
                 JOIN items o ON o.id = sb.item_id AND o.active = 1
                 WHERE sa.active = 1 AND sa.supplier_sku IS NOT NULL
                 GROUP BY i.id, s.name, sa.supplier_sku"
            ),
            'orphan_article' => $this->details(
                "SELECT i.id, GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS d
                 FROM supplier_articles sa
                 JOIN items i ON i.id = sa.item_id AND i.active = 1
                 JOIN suppliers s ON s.id = sa.supplier_id
                 WHERE sa.active = 1 AND NOT EXISTS (SELECT 1 FROM item_supplier l WHERE l.item_id = sa.item_id AND l.supplier_id = sa.supplier_id)
                 GROUP BY i.id"
            ),
            'missing_sku' => $this->details(
                "SELECT i.id, GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS d
                 FROM items i
                 JOIN item_supplier l ON l.item_id = i.id
                 JOIN suppliers s ON s.id = l.supplier_id AND s.active = 1 AND s.has_skus = 1
                 WHERE i.active = 1 AND NOT EXISTS (
                    SELECT 1 FROM supplier_articles sa WHERE sa.item_id = i.id AND sa.supplier_id = l.supplier_id
                      AND sa.is_default = 1 AND sa.active = 1 AND sa.supplier_sku IS NOT NULL)
                 GROUP BY i.id"
            ),
            'missing_pack' => $this->details(
                "SELECT i.id, GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS d
                 FROM supplier_articles sa
                 JOIN items i ON i.id = sa.item_id AND i.active = 1
                 JOIN item_supplier l ON l.item_id = sa.item_id AND l.supplier_id = sa.supplier_id
                 JOIN suppliers s ON s.id = sa.supplier_id
                 WHERE sa.is_default = 1 AND sa.active = 1 AND sa.pack_size IS NULL
                   -- Preis je kg/l/Stück braucht kein Gebinde (z. B. Lachs nach Gewicht)
                   AND NOT EXISTS (SELECT 1 FROM supplier_article_prices p WHERE p.supplier_article_id = sa.id AND p.per_unit <> 'order')
                 GROUP BY i.id"
            ),
            'unit_spelling' => $this->unitSpelling(),
            'long_name' => $this->ids(
                "SELECT id FROM items WHERE active = 1 AND CHAR_LENGTH(name) > 32 AND (alias IS NULL OR TRIM(alias) = '')"
            ),
            'no_price' => $this->ids(
                'SELECT i.id FROM items i WHERE i.active = 1 AND NOT EXISTS (
                    SELECT 1 FROM supplier_articles sa JOIN supplier_article_prices p ON p.supplier_article_id = sa.id WHERE sa.item_id = i.id)'
            ),
            'stale_price' => $this->details(
                "SELECT i.id, CONCAT('zuletzt ', DATE_FORMAT(MAX(p.price_date), '%d.%m.%Y')) AS d
                 FROM items i
                 JOIN supplier_articles sa ON sa.item_id = i.id
                 JOIN supplier_article_prices p ON p.supplier_article_id = sa.id
                 WHERE i.active = 1
                 GROUP BY i.id
                 HAVING MAX(p.price_date) < ?",
                [(new \DateTimeImmutable('today'))->modify('-' . self::STALE_MONTHS . ' months')->format('Y-m-d')]
            ),
            'price_jump' => $this->priceJumps(),
            'price_spread' => $this->priceSpread(),
            'no_purchase' => $this->ids(
                "SELECT i.id FROM items i WHERE i.active = 1 AND NOT EXISTS (
                    SELECT 1 FROM supplier_articles sa JOIN supplier_article_prices p ON p.supplier_article_id = sa.id AND p.price_type = 'purchase'
                    WHERE sa.item_id = i.id)"
            ),
            'not_convertible' => $this->notConvertible(),
            default => throw new \InvalidArgumentException("Unbekannte Prüfung: {$key}"),
        };
    }

    /** @return array<int, string> */
    private function ids(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), '');
    }

    /** @return array<int, string> */
    private function details(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['id'];
            $out[$id] = isset($out[$id]) ? $out[$id] . ' · ' . $r['d'] : (string) $r['d'];
        }

        return $out;
    }

    public static function unitKey(string $unit): string
    {
        $k = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $unit) ?? ''));
        $k = rtrim($k, '.');

        return self::UNIT_SYNONYMS[$k] ?? $k;
    }

    /** Seltenere Schreibweise einer Einheit, die es auch in häufigerer Form gibt (Ktn ↔ Karton, Kg ↔ kg). */
    private function unitSpelling(): array
    {
        $rows = $this->pdo->query("SELECT id, unit FROM items WHERE active = 1 AND unit <> ''")->fetchAll(PDO::FETCH_ASSOC);
        $byKey = [];
        foreach ($rows as $r) {
            $byKey[self::unitKey($r['unit'])][$r['unit']][] = (int) $r['id'];
        }
        $out = [];
        foreach ($byKey as $spellings) {
            if (count($spellings) < 2) {
                continue;
            }
            uasort($spellings, static fn (array $a, array $b): int => count($b) <=> count($a));
            $preferred = array_key_first($spellings);
            foreach ($spellings as $spelling => $ids) {
                if ($spelling === $preferred) {
                    continue;
                }
                foreach ($ids as $id) {
                    $out[$id] = "„{$spelling}“ → „{$preferred}“";
                }
            }
        }

        return $out;
    }

    /** Neuester vs. vorheriger Preis desselben Lieferantenartikels mit gleicher Preisbasis */
    private function priceJumps(): array
    {
        $rows = $this->pdo->query(
            "SELECT sa.item_id, s.name AS supplier, p.supplier_article_id, p.amount, p.per_qty, p.per_unit, p.price_date
             FROM supplier_article_prices p
             JOIN supplier_articles sa ON sa.id = p.supplier_article_id
             JOIN items i ON i.id = sa.item_id AND i.active = 1
             JOIN suppliers s ON s.id = sa.supplier_id
             ORDER BY p.supplier_article_id, p.price_date DESC, p.id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $byArticle = [];
        foreach ($rows as $r) {
            $byArticle[(int) $r['supplier_article_id']][] = $r;
        }
        $out = [];
        foreach ($byArticle as $prices) {
            [$new, $prev] = [$prices[0], null];
            foreach (array_slice($prices, 1) as $p) {
                if ($p['per_unit'] === $new['per_unit'] && $p['per_qty'] === $new['per_qty']) {
                    $prev = $p;
                    break;
                }
            }
            if ($prev === null) {
                continue;
            }
            $a = Money::fromDb($new['amount']);
            $b = Money::fromDb($prev['amount']);
            if ($a > 0 && $b > 0 && ($a >= self::JUMP_FACTOR * $b || $b >= self::JUMP_FACTOR * $a)) {
                $out[(int) $new['item_id']] = sprintf('%s: %s € → %s €', $new['supplier'], Money::formatDe($b), Money::formatDe($a));
            }
        }

        return $out;
    }

    /** Neueste Vergleichspreise je Lieferant (gleiche Basis) mit großem Abstand */
    private function priceSpread(): array
    {
        $names = $this->pdo->query('SELECT id, name FROM suppliers')->fetchAll(PDO::FETCH_KEY_PAIR);
        $active = array_fill_keys(array_map('intval', $this->pdo->query('SELECT id FROM items WHERE active = 1')->fetchAll(PDO::FETCH_COLUMN)), true);
        $out = [];
        foreach ((new PriceRepository())->latestForDefaults() as $itemId => $bySupplier) {
            if (!isset($active[$itemId]) || count($bySupplier) < 2) {
                continue;
            }
            $byBase = [];
            foreach ($bySupplier as $sid => $p) {
                if ($p['comparison'] !== null) {
                    $byBase[$p['comparison']['base']][$sid] = Money::parse($p['comparison']['amount']);
                }
            }
            foreach ($byBase as $base => $values) {
                if (count($values) < 2 || min($values) <= 0 || max($values) < self::SPREAD_FACTOR * min($values)) {
                    continue;
                }
                arsort($values);
                $parts = [];
                foreach ($values as $sid => $v) {
                    $parts[] = ($names[$sid] ?? '?') . ' ' . Money::formatDe($v) . " €/{$base}";
                }
                $out[$itemId] = implode(' · ', $parts);
            }
        }

        return $out;
    }

    /** Artikel mit Einkaufspreis, der sich nicht auf die Zähleinheit umrechnen lässt */
    private function notConvertible(): array
    {
        $items = $this->pdo->query(
            "SELECT DISTINCT i.id, i.unit, i.base_unit, i.unit_factor FROM items i
             JOIN supplier_articles sa ON sa.item_id = i.id
             JOIN supplier_article_prices p ON p.supplier_article_id = sa.id AND p.price_type = 'purchase'
             WHERE i.active = 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        foreach ((new InventoryValuationService())->forItems($items, $today) as $id => $v) {
            if ($v['status'] === InventoryValuationService::NOT_CONVERTIBLE) {
                $out[$id] = 'Einkauf: ' . ($v['basis'] ?? '');
            }
        }

        return $out;
    }
}
