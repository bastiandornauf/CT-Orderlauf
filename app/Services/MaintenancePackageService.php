<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\ItemDisplay;
use App\Helpers\Money;
use App\Helpers\Units;
use App\Repositories\InboxRepository;
use App\Repositories\ItemRepository;
use App\Repositories\MaintenanceLogRepository;
use App\Repositories\PendingItemRepository;
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
 *     {"op": "unit_factor", "item_id": 180, "unit_factor": "4,25", "base_unit": "kg"},
 *     {"op": "suggest", "name": "Kabeljau Loin MSC (TK)", "unit": "Karton (5 kg)", "supplier": "Transgourmet",
 *      "location": "TK", "quantity": "1",                // fehlender Artikel → Artikel-Vorschläge (ohne item_id)
 *      "article": {"supplier_label": "MSC Paz.Kab.Loin180-200g TK5k", "supplier_sku": "657372"},
 *      "price": {"amount": "21,096", "per_unit": "kg", "price_type": "purchase", "source": "invoice"}},
 *     {"op": "suggest", "pending_id": 31, "supplier": "Transgourmet", "article": {…}, "price": {…}}
 *                                                       // vorhandenen Vorschlag (frei bestellt) mit Beleg verknüpfen
 *   ]
 * }
 * „item_name“ ist optional und dient nur der Kontrolle: weicht er ab, warnt die Vorschau.
 * „inbox_message_ids“ (optional, Liste): Belege aus dem Posteingang, die beim Einspielen abgehakt werden.
 */
final class MaintenancePackageService
{
    public const FORMAT = 'orderlauf-pflegepaket';
    public const OPS = ['price', 'article', 'link', 'alias', 'name', 'unit', 'unit_factor', 'suggest'];

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
     * @return array{applied:int, skipped:int, receipts_done:int}
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
            $receiptsDone = 0;
            if (is_array($pkg['inbox_message_ids'] ?? null) && $pkg['inbox_message_ids'] !== []) {
                try {
                    $receiptsDone = (new InboxRepository())->markReceiptsDone($pkg['inbox_message_ids']);
                } catch (\PDOException) {
                    $receiptsDone = 0;   // Posteingang-Migration noch nicht eingespielt
                }
            }
            (new MaintenanceLogRepository())->log('package_applied', null, [
                'title' => (string) ($pkg['title'] ?? ''),
                'source' => $pkg['source'] ?? null,
                'applied' => $applied,
                'inbox_message_ids' => $pkg['inbox_message_ids'] ?? null,
            ], $userId);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['applied' => count($applied), 'skipped' => $skipped, 'receipts_done' => $receiptsDone];
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
        if ($op === 'suggest') {
            return $this->evalSuggest($c) + ['message' => ''];
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

    /**
     * Fehlender Artikel → Artikel-Vorschläge (angelegt wird er erst dort vom User).
     * Mit „pending_id“: vorhandenen Vorschlag (z. B. frei bestellt „5 kg TK Kabeljau“) mit den Beleg-Daten verknüpfen.
     * Optional „article“ (Bezeichnung, Art.-Nr., Gebinde) und „price“ – werden beim Übernehmen zu Bestelldaten/Preis.
     */
    private function evalSuggest(array $c): array
    {
        $sid = $this->supplierId($c);
        $receipt = $this->receipt($c, $sid);
        $receiptText = $receipt !== null ? self::receiptText($receipt) : '';
        $pendingId = (int) ($c['pending_id'] ?? 0);

        if ($pendingId > 0) {
            $p = (new PendingItemRepository())->find($pendingId);
            if ($p === null || $p['status'] !== 'open') {
                throw new \InvalidArgumentException("Artikel-Vorschlag #{$pendingId} ist nicht (mehr) offen.");
            }
            if ($receipt === null) {
                throw new \InvalidArgumentException('Zum Verknüpfen „article“ oder „price“ angeben.');
            }
            $base = ['op' => 'suggest', 'item' => '„' . $p['name'] . '“ (Vorschlag #' . $pendingId . ')',
                'supplier' => $this->supplierNames[$sid], 'field' => 'Lieferschein-Daten',
                'old' => self::receiptText(is_string($p['receipt'] ?? null) ? (json_decode($p['receipt'], true) ?: []) : [])];
            if (self::sameReceipt($p['receipt'] ?? null, $receipt)) {
                return $base + ['new' => $receiptText, 'status' => 'same', 'message' => 'schon verknüpft'];
            }

            return $base + ['new' => $receiptText, 'status' => 'warn',
                'message' => 'Zuordnung prüfen: frei bestellt „' . $p['name'] . '“ = ' . ($receipt['supplier_label'] ?? 'Beleg-Artikel')];
        }

        $name = (string) preg_replace('/\s+/u', ' ', self::required($c, 'name'));
        $locId = $this->locationId($c);
        $base = ['op' => 'suggest', 'item' => $name, 'supplier' => $this->supplierNames[$sid], 'field' => 'Artikel-Vorschlag', 'old' => ''];
        $new = trim(implode(' · ', array_filter([
            trim((string) ($c['unit'] ?? '')),
            $locId !== null ? 'Lager ' . $this->locationName($locId) : '',
            $receiptText,
        ])));

        $stmt = $this->pdo->prepare('SELECT id, name FROM items WHERE active = 1 AND merged_into_id IS NULL AND (LOWER(name) = ? OR LOWER(alias) = ?) LIMIT 1');
        $lower = mb_strtolower($name);
        $stmt->execute([$lower, $lower]);
        if ($hit = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $base + ['new' => $new, 'status' => 'error', 'message' => 'Artikel gibt es schon (#' . $hit['id'] . ') – stattdessen Preis/Bestelldaten zuordnen.'];
        }
        $stmt = $this->pdo->prepare('SELECT status, receipt FROM pending_items WHERE dedupe_key = ?');
        $stmt->execute([PendingItemRepository::dedupeKey($name)]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($existing !== null && $existing['status'] === 'open' && ($receipt === null || self::sameReceipt($existing['receipt'], $receipt))) {
            return $base + ['new' => $new, 'status' => 'same', 'message' => 'schon in Artikel-Vorschlägen'];
        }

        return $base + ['new' => $new !== '' ? $new : 'neu', 'status' => 'change',
            'message' => match (true) {
                $existing !== null && $existing['status'] === 'open' => 'Vorschlag gibt es schon – Beleg-Daten werden ergänzt',
                $existing !== null => 'war verworfen – kommt zurück',
                default => '',
            }];
    }

    /** @return array<string, mixed>|null Beleg-Daten für einen Vorschlag (null = keine) */
    private function receipt(array $c, int $sid): ?array
    {
        $article = is_array($c['article'] ?? null) ? $c['article'] : [];
        $r = ['supplier_id' => $sid, 'supplier' => $this->supplierNames[$sid]];
        foreach (SupplierArticleRepository::EDIT_FIELDS as $f) {
            $v = trim((string) ($article[$f] ?? ''));
            if ($v !== '') {
                $r[$f] = mb_substr($v, 0, 255);
            }
        }
        if (isset($r['pack_size']) !== isset($r['pack_unit'])) {
            throw new \InvalidArgumentException('Gebinde nur mit pack_size und pack_unit zusammen.');
        }
        if (isset($r['pack_size']) && Money::parse($r['pack_size']) === null) {
            throw new \InvalidArgumentException('pack_size ungültig.');
        }
        if (is_array($c['price'] ?? null)) {
            [$p] = $this->priceRecord($c['price'] + array_intersect_key($c, array_flip(['price_date', 'document_ref', 'supplier'])), []);
            $r['price'] = [
                'price_type' => $p['price_type'], 'amount' => Money::formatDe($p['amount'], 4), 'per_qty' => Money::formatDe($p['per_qty'], 4),
                'per_unit' => $p['per_unit'], 'source' => $p['source'], 'price_date' => $p['price_date'],
                'document_ref' => $p['document_ref'], 'note' => $p['note'], 'is_net' => $p['is_net'],
            ];
        }
        foreach (['document_ref', 'price_date'] as $f) {
            if (trim((string) ($c[$f] ?? '')) !== '') {
                $r[$f] = trim((string) $c[$f]);
            }
        }

        return count($r) > 2 + (isset($r['document_ref']) ? 1 : 0) + (isset($r['price_date']) ? 1 : 0) ? $r : null;
    }

    private static function receiptText(array $r): string
    {
        if ($r === []) {
            return '';
        }
        $price = '';
        if (is_array($r['price'] ?? null)) {
            $p = $r['price'];
            $per = $p['per_unit'] === 'order' ? 'Einheit' : (Units::BASE_LABELS[$p['per_unit']] ?? $p['per_unit']);
            $qty = rtrim(rtrim((string) $p['per_qty'], '0'), ',');
            $price = rtrim(rtrim((string) $p['amount'], '0'), ',') . ' €/' . ($qty !== '1' ? $qty . ' ' : '') . $per;
        }

        return implode(' · ', array_filter([
            (string) ($r['supplier_label'] ?? ''),
            isset($r['supplier_sku']) ? 'Art.-Nr. ' . $r['supplier_sku'] : '',
            isset($r['pack_size']) ? $r['pack_size'] . ' ' . $r['pack_unit'] : '',
            $price,
            (string) ($r['document_ref'] ?? ''),
        ]));
    }

    private static function sameReceipt(mixed $stored, array $receipt): bool
    {
        $old = is_string($stored) ? json_decode($stored, true) : (is_array($stored) ? $stored : null);

        return is_array($old) && json_encode($old) === json_encode($receipt);
    }

    private function locationId(array $c): ?int
    {
        $l = $c['location'] ?? null;
        if ($l === null || trim((string) $l) === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM locations WHERE id = ? OR LOWER(name) = ? LIMIT 1');
        $stmt->execute([ctype_digit((string) $l) ? (int) $l : 0, mb_strtolower(trim((string) $l))]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new \InvalidArgumentException('Lagerort „' . $l . '“ unbekannt.');
        }

        return (int) $id;
    }

    private function locationName(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM locations WHERE id = ?');
        $stmt->execute([$id]);

        return (string) $stmt->fetchColumn();
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
        if ($c['op'] === 'suggest') {
            $pending = new PendingItemRepository();
            $sid = $this->supplierId($c);
            $receipt = $this->receipt($c, $sid);
            $pendingId = (int) ($c['pending_id'] ?? 0);
            if ($pendingId <= 0) {
                $pending->record([
                    'name' => (string) $c['name'],
                    'unit' => (string) ($c['unit'] ?? ''),
                    'quantity' => (string) ($c['quantity'] ?? ''),
                    'location_id' => $this->locationId($c),
                    'supplier_id' => $sid,
                    'source' => 'order',
                ], $userId);
                $pendingId = (int) $pending->idByName((string) $c['name']);
            }
            if ($receipt !== null && $pendingId > 0) {
                $pending->setReceipt($pendingId, $receipt, isset($c['unit']) ? (string) $c['unit'] : null);
            }

            return;
        }
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
