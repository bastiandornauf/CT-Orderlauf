<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\ItemDisplay;
use App\Helpers\Money;
use App\Helpers\Units;
use App\Repositories\ItemRepository;
use App\Repositories\MaintenanceLogRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;
use PDO;

/**
 * Pflegepaket: strukturierte Änderungen (z. B. von Claude aus Rechnungsfotos erstellt) mit Vorschau
 * und selektiver Übernahme. Es wird ausschließlich über die Repositories der App geschrieben –
 * gleiche Prüfungen, gleicher Doppel-Schutz, Protokoll im maintenance_log.
 *
 * Format (JSON):
 * {
 *   "format": "orderlauf-pflegepaket", "version": 1,
 *   "title": "Pütz Rechnung 4711 vom 20.10.2026",
 *   "source": {"supplier": "Pütz", "document_ref": "RE 4711", "date": "2026-10-20"},   // optional, Standardwerte
 *   "changes": [
 *     {"op": "price", "item_id": 180, "item_name": "…", "supplier": "Pütz", "price_type": "purchase",
 *      "amount": "24,80", "per_qty": "1", "per_unit": "order", "price_date": "2026-10-20",
 *      "source": "invoice", "document_ref": "RE 4711", "note": "Pos. 3"},
 *     {"op": "article", "item_id": 180, "supplier": "Pütz", "supplier_label": "…", "supplier_sku": "…",
 *      "pack_size": "4,25", "pack_unit": "kg", "order_unit": "Dose"},
 *     {"op": "link", "item_id": 180, "supplier": "Transgourmet", "priority": 0},
 *     {"op": "alias", "item_id": 180, "alias": "Oliven schwarz"},
 *     {"op": "name", "item_id": 180, "name": "…"},
 *     {"op": "unit", "item_id": 180, "unit": "Dose"},
 *     {"op": "unit_factor", "item_id": 180, "unit_factor": "4,25", "base_unit": "kg"}
 *   ]
 * }
 * „item_name“ ist optional und dient nur der Kontrolle: weicht er ab, warnt die Vorschau.
 */
final class MaintenancePackageService
{
    public const FORMAT = 'orderlauf-pflegepaket';
    public const OPS = ['price', 'article', 'link', 'alias', 'name', 'unit', 'unit_factor'];

    private PDO $pdo;
    /** @var array<string, int> Lieferantenname (klein) → ID */
    private array $supplierIds = [];
    /** @var array<int, string> */
    private array $supplierNames = [];

    public function __construct()
    {
        $this->pdo = Database::pdo();
        foreach ($this->pdo->query('SELECT id, name FROM suppliers')->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $this->supplierIds[mb_strtolower($s['name'])] = (int) $s['id'];
            $this->supplierNames[(int) $s['id']] = $s['name'];
        }
    }

    /** @return list<string> Fehler im Paketaufbau (leer = ok) */
    public static function validateEnvelope(mixed $pkg): array
    {
        if (!is_array($pkg)) {
            return ['Kein gültiges JSON-Objekt.'];
        }
        $errors = [];
        if (($pkg['format'] ?? '') !== self::FORMAT) {
            $errors[] = 'Unbekanntes Format – erwartet „' . self::FORMAT . '“.';
        }
        if ((int) ($pkg['version'] ?? 0) !== 1) {
            $errors[] = 'Nur Version 1 wird unterstützt.';
        }
        if (!isset($pkg['changes']) || !is_array($pkg['changes']) || $pkg['changes'] === []) {
            $errors[] = 'Keine Änderungen enthalten.';
        }

        return $errors;
    }

    /**
     * Vorschau je Änderung.
     *
     * @return list<array{index:int, op:string, item:string, supplier:string, field:string, old:string, new:string,
     *                    status:string, message:string}>  status: change | same | warn | error
     */
    public function preview(array $pkg): array
    {
        $rows = [];
        $pendingLinks = [];
        foreach (array_values($pkg['changes']) as $i => $c) {
            $c = is_array($c) ? $c + self::defaults($pkg) : [];
            try {
                $row = $this->evaluate($c, $pendingLinks);
            } catch (\InvalidArgumentException $e) {
                $row = ['op' => (string) ($c['op'] ?? '?'), 'item' => self::itemLabelRaw($c), 'supplier' => (string) ($c['supplier'] ?? ''),
                    'field' => '', 'old' => '', 'new' => '', 'status' => 'error', 'message' => $e->getMessage()];
            }
            if ($row['op'] === 'link' && $row['status'] !== 'error') {
                $pendingLinks[(int) $c['item_id'] . ':' . $this->supplierId($c)] = true;
            }
            $rows[] = ['index' => $i] + $row;
        }

        return $rows;
    }

    /**
     * Übernimmt die ausgewählten Änderungen in einer Transaktion.
     *
     * @param list<int> $selected Indizes aus der Vorschau
     * @return array{applied:int, skipped:int}
     */
    public function apply(array $pkg, array $selected, ?int $userId): array
    {
        $selected = array_fill_keys(array_map('intval', $selected), true);
        $changes = array_values($pkg['changes']);
        $applied = [];
        $skipped = 0;
        $this->pdo->beginTransaction();
        try {
            $pendingLinks = [];
            foreach ($changes as $i => $c) {
                if (!isset($selected[$i]) || !is_array($c)) {
                    continue;
                }
                $c += self::defaults($pkg);
                $row = $this->evaluate($c, $pendingLinks);   // erneut prüfen – das Paket kommt vom Client
                if ($row['status'] === 'error') {
                    throw new \InvalidArgumentException('Zeile ' . ($i + 1) . ': ' . $row['message']);
                }
                if ($row['status'] === 'same') {
                    $skipped++;
                    continue;
                }
                $this->write($c, $userId);
                if ($c['op'] === 'link') {
                    $pendingLinks[(int) $c['item_id'] . ':' . $this->supplierId($c)] = true;
                }
                $applied[] = ['index' => $i] + $row;
            }
            (new MaintenanceLogRepository())->log('package_applied', null, [
                'title' => (string) ($pkg['title'] ?? ''),
                'source' => $pkg['source'] ?? null,
                'applied' => $applied,
            ], $userId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['applied' => count($applied), 'skipped' => $skipped];
    }

    /** Standardwerte aus „source“ für Preiszeilen */
    private static function defaults(array $pkg): array
    {
        $src = is_array($pkg['source'] ?? null) ? $pkg['source'] : [];
        $map = ['supplier' => 'supplier', 'document_ref' => 'document_ref', 'date' => 'price_date'];
        $d = [];
        foreach ($map as $from => $to) {
            if (isset($src[$from])) {
                $d[$to] = $src[$from];
            }
        }

        return $d;
    }

    private static function itemLabelRaw(array $c): string
    {
        return '#' . (int) ($c['item_id'] ?? 0) . (isset($c['item_name']) ? ' ' . $c['item_name'] : '');
    }

    private function supplierId(array $c): int
    {
        $s = $c['supplier'] ?? null;
        if (is_int($s) || ctype_digit((string) $s)) {
            $id = (int) $s;
            if (!isset($this->supplierNames[$id])) {
                throw new \InvalidArgumentException("Lieferant {$s} unbekannt.");
            }

            return $id;
        }
        $id = $this->supplierIds[mb_strtolower(trim((string) $s))] ?? null;
        if ($id === null) {
            throw new \InvalidArgumentException('Lieferant „' . $s . '“ unbekannt.');
        }

        return $id;
    }

    /** @return array<string, mixed> */
    private function item(array $c): array
    {
        $item = (new ItemRepository())->find((int) ($c['item_id'] ?? 0));
        if ($item === null) {
            throw new \InvalidArgumentException('Artikel ' . self::itemLabelRaw($c) . ' nicht gefunden.');
        }

        return $item;
    }

    /** Bewertet eine Änderung gegen den aktuellen Stand, ohne zu schreiben. */
    private function evaluate(array $c, array $pendingLinks): array
    {
        $op = (string) ($c['op'] ?? '');
        if (!in_array($op, self::OPS, true)) {
            throw new \InvalidArgumentException("Unbekannte Änderung „{$op}“.");
        }
        $item = $this->item($c);
        $warn = [];
        if (isset($c['item_name']) && trim((string) $c['item_name']) !== ''
            && !in_array(mb_strtolower(trim((string) $c['item_name'])), [mb_strtolower($item['name']), mb_strtolower((string) $item['alias'])], true)) {
            $warn[] = 'Artikelname im Paket („' . $c['item_name'] . '“) passt nicht zur ID';
        }
        $base = ['op' => $op, 'item' => ItemDisplay::name($item) . ' (#' . $item['id'] . ')', 'supplier' => ''];
        $result = match ($op) {
            'price' => $this->evalPrice($c, $item, $pendingLinks),
            'article' => $this->evalArticle($c, $item, $pendingLinks),
            'link' => $this->evalLink($c, $item),
            'alias' => self::diff('Alias', (string) ($item['alias'] ?? ''), trim((string) ($c['alias'] ?? ''))),
            'name' => self::diff('Name', (string) $item['name'], self::required($c, 'name')),
            'unit' => self::diff('Einheit', (string) $item['unit'], self::required($c, 'unit')),
            'unit_factor' => $this->evalUnitFactor($c, $item),
        };
        $row = $result + $base;
        if ($warn !== [] && $row['status'] === 'change') {
            $row['status'] = 'warn';
            $row['message'] = trim(implode(' · ', $warn) . ' ' . ($row['message'] ?? ''));
        }

        return $row + ['message' => ''];
    }

    private static function required(array $c, string $key): string
    {
        $v = trim((string) ($c[$key] ?? ''));
        if ($v === '') {
            throw new \InvalidArgumentException("„{$key}“ fehlt.");
        }

        return $v;
    }

    private static function diff(string $field, string $old, string $new): array
    {
        return ['field' => $field, 'old' => $old, 'new' => $new, 'status' => $old === $new ? 'same' : 'change'];
    }

    private function linked(int $itemId, int $supplierId, array $pendingLinks): bool
    {
        if (isset($pendingLinks["{$itemId}:{$supplierId}"])) {
            return true;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM item_supplier WHERE item_id = ? AND supplier_id = ?');
        $stmt->execute([$itemId, $supplierId]);

        return (bool) $stmt->fetchColumn();
    }

    /** @return array{0: array<string, mixed>, 1: int} Preis-Datensatz (Skala 4) und Lieferanten-ID */
    private function priceRecord(array $c, array $item): array
    {
        $sid = $this->supplierId($c);
        $type = (string) ($c['price_type'] ?? 'known');
        $perUnit = (string) ($c['per_unit'] ?? 'order');
        $source = (string) ($c['source'] ?? ($type === 'purchase' ? 'invoice' : 'manual'));
        $amount = Money::parse($c['amount'] ?? null);
        $perQty = Money::parse($c['per_qty'] ?? '1');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($c['price_date'] ?? ''));
        $err = match (true) {
            !in_array($type, PriceRepository::TYPES, true) => 'price_type muss known oder purchase sein.',
            $amount === null || $amount === 0 => 'Betrag fehlt oder ungültig.',
            $perQty === null || $perQty === 0 => 'per_qty muss größer als 0 sein.',
            !in_array($perUnit, PriceRepository::PER_UNITS, true) => 'per_unit ungültig.',
            !in_array($source, PriceRepository::SOURCES, true) => 'source ungültig.',
            $date === false => 'price_date fehlt (JJJJ-MM-TT).',
            $date > new \DateTimeImmutable('tomorrow') => 'Datum liegt in der Zukunft.',
            default => null,
        };
        if ($err !== null) {
            throw new \InvalidArgumentException($err);
        }
        $text = static fn (mixed $v, int $max): ?string => trim((string) ($v ?? '')) === '' ? null : mb_substr(trim((string) $v), 0, $max);

        return [[
            'price_type' => $type,
            'amount' => $amount,
            'per_qty' => $perQty,
            'per_unit' => $perUnit,
            'price_date' => $date->format('Y-m-d'),
            'source' => $source,
            'document_ref' => $text($c['document_ref'] ?? null, 128),
            'note' => $text($c['note'] ?? null, 255),
            'is_net' => ($c['is_net'] ?? true) !== false,
        ], $sid];
    }

    private function evalPrice(array $c, array $item, array $pendingLinks): array
    {
        [$p, $sid] = $this->priceRecord($c, $item);
        if (!$this->linked((int) $item['id'], $sid, $pendingLinks)) {
            throw new \InvalidArgumentException("{$this->supplierNames[$sid]} ist diesem Artikel nicht zugeordnet (vorher „link“ ergänzen).");
        }
        $articleId = $this->defaultArticleId((int) $item['id'], $sid);
        $exists = false;
        $old = '';
        if ($articleId !== null) {
            $key = PriceRepository::dedupeKey(['supplier_article_id' => $articleId] + $p);
            $stmt = $this->pdo->prepare('SELECT 1 FROM supplier_article_prices WHERE dedupe_key = ?');
            $stmt->execute([$key]);
            $exists = (bool) $stmt->fetchColumn();
            $last = $this->pdo->prepare('SELECT amount, per_qty, per_unit, price_date FROM supplier_article_prices
                WHERE supplier_article_id = ? AND price_type = ? ORDER BY price_date DESC, id DESC LIMIT 1');
            $last->execute([$articleId, $p['price_type']]);
            if ($r = $last->fetch(PDO::FETCH_ASSOC)) {
                $old = Money::formatDe(Money::fromDb($r['amount'])) . ' € (' . date('d.m.y', strtotime($r['price_date'])) . ')';
            }
        }
        $per = $p['per_unit'] === 'order' ? (trim((string) $item['unit']) ?: 'Einheit') : (Units::BASE_LABELS[$p['per_unit']] ?? $p['per_unit']);
        $new = Money::formatDe($p['amount']) . ' €/' . ($p['per_qty'] !== Money::SCALE ? rtrim(rtrim(Money::formatDe($p['per_qty'], 4), '0'), ',') . ' ' : '') . $per
            . ' · ' . date('d.m.y', strtotime($p['price_date'])) . ($p['document_ref'] ? ' · ' . $p['document_ref'] : '');

        return [
            'supplier' => $this->supplierNames[$sid],
            'field' => $p['price_type'] === 'purchase' ? 'Einkaufspreis' : 'Listenpreis',
            'old' => $old,
            'new' => $new,
            'status' => $exists ? 'same' : 'change',
            'message' => $exists ? 'schon vorhanden' : '',
        ];
    }

    private function defaultArticleId(int $itemId, int $supplierId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM supplier_articles WHERE item_id = ? AND supplier_id = ? AND is_default = 1 AND active = 1 ORDER BY id LIMIT 1');
        $stmt->execute([$itemId, $supplierId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    private function evalArticle(array $c, array $item, array $pendingLinks): array
    {
        $sid = $this->supplierId($c);
        if (!$this->linked((int) $item['id'], $sid, $pendingLinks)) {
            throw new \InvalidArgumentException("{$this->supplierNames[$sid]} ist diesem Artikel nicht zugeordnet (vorher „link“ ergänzen).");
        }
        $given = array_intersect_key($c, array_flip(SupplierArticleRepository::EDIT_FIELDS));
        if ($given === []) {
            throw new \InvalidArgumentException('Keine Bestelldaten angegeben.');
        }
        $current = ItemRepository::articleFields((new SupplierArticleRepository())->defaultsByItemIds([(int) $item['id']])[(int) $item['id']][$sid] ?? null);
        $next = SupplierArticleRepository::cleanFields($given + $current);
        if (isset($given['pack_size']) && $next['pack_size'] === null) {
            throw new \InvalidArgumentException('Gebinde unvollständig (pack_size und pack_unit kg|g|l|ml|stk).');
        }
        $fmt = static function (array $a): string {
            $parts = [];
            foreach (['supplier_label' => '', 'supplier_sku' => 'Art.-Nr. ', 'order_unit' => 'Einheit '] as $k => $p) {
                if (($a[$k] ?? null) !== null && $a[$k] !== '') {
                    $parts[] = $p . $a[$k];
                }
            }
            if (($a['pack_size'] ?? null) !== null) {
                $parts[] = str_replace('.', ',', (string) (0 + $a['pack_size'])) . ' ' . $a['pack_unit'];
            }

            return implode(' · ', $parts);
        };
        $old = $fmt($current);
        $new = $fmt($next);

        return ['supplier' => $this->supplierNames[$sid], 'field' => 'Bestelldaten', 'old' => $old, 'new' => $new,
            'status' => $old === $new ? 'same' : 'change'];
    }

    private function evalLink(array $c, array $item): array
    {
        $sid = $this->supplierId($c);
        $prio = (int) ($c['priority'] ?? 0);
        $stmt = $this->pdo->prepare('SELECT priority FROM item_supplier WHERE item_id = ? AND supplier_id = ?');
        $stmt->execute([(int) $item['id'], $sid]);
        $cur = $stmt->fetchColumn();

        return ['supplier' => $this->supplierNames[$sid], 'field' => 'Lieferant (Priorität)',
            'old' => $cur === false ? '' : 'Prio ' . (int) $cur, 'new' => 'Prio ' . $prio,
            'status' => $cur !== false && (int) $cur === $prio ? 'same' : 'change'];
    }

    private function evalUnitFactor(array $c, array $item): array
    {
        $f = Money::parse($c['unit_factor'] ?? null);
        $b = (string) ($c['base_unit'] ?? '');
        if ($f === null || $f === 0 || !isset(Units::BASE_LABELS[$b])) {
            throw new \InvalidArgumentException('unit_factor > 0 und base_unit kg|l|stk angeben.');
        }
        $old = $item['unit_factor'] !== null ? Money::formatDe(Money::fromDb($item['unit_factor']), 4) . ' ' . $item['base_unit'] : '';

        return self::diff('Inhalt je Einheit', $old, Money::formatDe($f, 4) . ' ' . $b);
    }

    private function write(array $c, ?int $userId): void
    {
        $items = new ItemRepository();
        $id = (int) $c['item_id'];
        switch ($c['op']) {
            case 'price':
                [$p, $sid] = $this->priceRecord($c, $items->find($id));
                $articleId = (new SupplierArticleRepository())->ensureDefault($id, $sid);
                (new PriceRepository())->add(['supplier_article_id' => $articleId] + $p, $userId);
                break;
            case 'article':
                $sid = $this->supplierId($c);
                $repo = new SupplierArticleRepository();
                $current = ItemRepository::articleFields($repo->defaultsByItemIds([$id])[$id][$sid] ?? null);
                $repo->saveDefaults($id, [$sid => array_intersect_key($c, array_flip(SupplierArticleRepository::EDIT_FIELDS)) + $current]);
                break;
            case 'link':
                $this->pdo->prepare('INSERT INTO item_supplier (item_id, supplier_id, priority) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE priority = VALUES(priority)')->execute([$id, $this->supplierId($c), (int) ($c['priority'] ?? 0)]);
                break;
            case 'alias':
                $items->updateAlias($id, (string) ($c['alias'] ?? ''));
                break;
            case 'name':
                $this->pdo->prepare('UPDATE items SET name = ? WHERE id = ?')->execute([mb_substr(trim((string) $c['name']), 0, 255), $id]);
                break;
            case 'unit':
                $this->pdo->prepare('UPDATE items SET unit = ? WHERE id = ?')->execute([mb_substr(trim((string) $c['unit']), 0, 64), $id]);
                break;
            case 'unit_factor':
                $items->updateUnitConversion($id, $c['unit_factor'], $c['base_unit']);
                break;
        }
    }
}
