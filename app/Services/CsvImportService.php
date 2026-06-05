<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\ValuationPrice;
use App\Repositories\ItemRepository;
use App\Repositories\LocationRepository;
use App\Repositories\SupplierRepository;

final class CsvImportService
{
    public function __construct(
        private LocationRepository $locations = new LocationRepository(),
        private SupplierRepository $suppliers = new SupplierRepository(),
        private ItemRepository $items = new ItemRepository()
    ) {
    }

    /**
     * @return list<list<string>>
     */
    public function parse(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, ';', '"', '\\');
        }
        return $rows;
    }

    /**
     * @param list<list<string>> $rows
     * @return array{ok: bool, errors: list<string>, preview: list<array<string, mixed>>, items_not_in_csv: list<array{id:int,name:string}>}
     */
    public function preview(string $type, array $rows): array
    {
        $errors = [];
        $preview = [];
        $itemsNotInCsv = [];
        if ($rows === []) {
            return [
                'ok' => false,
                'errors' => ['Leere Datei.'],
                'preview' => [],
                'items_not_in_csv' => [],
            ];
        }
        $header = array_map('trim', $rows[0]);
        $dataRows = array_slice($rows, 1);

        match ($type) {
            'locations' => $this->validateLocations($header, $dataRows, $errors, $preview),
            'suppliers' => $this->validateSuppliers($header, $dataRows, $errors, $preview),
            'delivery_days' => $this->validateDeliveryDays($header, $dataRows, $errors, $preview),
            'items' => $this->validateItems($header, $dataRows, $errors, $preview, $itemsNotInCsv),
            'item_prices' => $this->validateItemPrices($header, $dataRows, $errors, $preview),
            'item_supplier' => $this->validateItemSupplier($header, $dataRows, $errors, $preview),
            default => $errors[] = 'Unbekannter Importtyp.',
        };

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'preview' => $preview,
            'items_not_in_csv' => $itemsNotInCsv,
        ];
    }

    /**
     * @param list<string> $errors
     * @param list<array<string, mixed>> $preview
     * @param list<list<string>> $dataRows
     */
    private function validateLocations(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        if ($header !== ['name', 'sort_order']) {
            $errors[] = 'Kopfzeile muss sein: name;sort_order';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $name = trim($cols[0] ?? '');
            $sort = trim($cols[1] ?? '0');
            if ($name === '') {
                $errors[] = "Zeile {$line}: name fehlt.";
                continue;
            }
            if (!is_numeric($sort)) {
                $errors[] = "Zeile {$line}: sort_order ungültig.";
                continue;
            }
            $preview[] = ['name' => $name, 'sort_order' => (int) $sort];
        }
    }

    /** @param list<list<string>> $dataRows */
    private function validateSuppliers(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        $base = ['name', 'email', 'type', 'active'];
        $withSubject = ['name', 'email', 'type', 'active', 'email_subject_template'];
        $hasSubjectCol = false;
        if ($header === $withSubject) {
            $hasSubjectCol = true;
        } elseif ($header !== $base) {
            $errors[] = 'Kopfzeile muss sein: name;email;type;active oder mit zusätzlicher Spalte email_subject_template';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $name = trim($cols[0] ?? '');
            $email = trim($cols[1] ?? '');
            $type = trim($cols[2] ?? '');
            $active = trim($cols[3] ?? '1');
            $subjTpl = null;
            if ($hasSubjectCol) {
                $raw = trim((string) ($cols[4] ?? ''));
                if ($raw !== '' && mb_strlen($raw) > 512) {
                    $errors[] = "Zeile {$line}: email_subject_template max. 512 Zeichen.";
                    continue;
                }
                $subjTpl = $raw !== '' ? $raw : null;
            }
            if ($name === '') {
                $errors[] = "Zeile {$line}: name fehlt.";
                continue;
            }
            if (!in_array($type, ['mail', 'webshop'], true)) {
                $errors[] = "Zeile {$line}: type muss mail oder webshop sein.";
                continue;
            }
            if (!in_array($active, ['0', '1'], true)) {
                $errors[] = "Zeile {$line}: active muss 0 oder 1 sein.";
                continue;
            }
            if ($type === 'mail' && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Zeile {$line}: E-Mail ungültig.";
                continue;
            }
            $row = [
                'name' => $name,
                'email' => $email ?: null,
                'order_type' => $type,
                'active' => $active === '1',
            ];
            if ($hasSubjectCol) {
                $row['email_subject_template'] = $subjTpl;
            }
            $preview[] = $row;
        }
    }

    /** @param list<list<string>> $dataRows */
    private function validateDeliveryDays(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        if ($header !== ['supplier_name', 'delivery_days']) {
            $errors[] = 'Kopfzeile muss sein: supplier_name;delivery_days';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $sname = trim($cols[0] ?? '');
            $daysRaw = trim($cols[1] ?? '');
            if ($sname === '') {
                $errors[] = "Zeile {$line}: supplier_name fehlt.";
                continue;
            }
            $sup = $this->suppliers->findByName($sname);
            if ($sup === null) {
                $errors[] = "Zeile {$line}: Lieferant „{$sname}“ nicht gefunden.";
                continue;
            }
            $parts = array_filter(array_map('trim', explode(',', $daysRaw)));
            $days = [];
            foreach ($parts as $p) {
                if (!ctype_digit($p)) {
                    $errors[] = "Zeile {$line}: delivery_days enthält ungültige Werte.";
                    continue 2;
                }
                $d = (int) $p;
                if ($d < 1 || $d > 7) {
                    $errors[] = "Zeile {$line}: Wochentag muss 1–7 sein.";
                    continue 2;
                }
                $days[] = $d;
            }
            $preview[] = ['supplier_id' => (int) $sup['id'], 'supplier_name' => $sname, 'weekdays' => $days];
        }
    }

    /**
     * @param list<list<string>> $dataRows
     * @param list<array{id:int,name:string}> $itemsNotInCsv
     */
    private function validateItems(
        array $header,
        array $dataRows,
        array &$errors,
        array &$preview,
        array &$itemsNotInCsv
    ): void {
        $parsed = $this->parseItemsCsvHeader($header);
        if ($parsed === null) {
            $errors[] = 'Artikel: Kopfzeile z. B. id;name;location;unit;min_stock;max_stock;bewertungspreis;active;sort_order '
                . '(bewertungspreis optional, sort_order optional) — oder ohne id/bewertungspreis wie bisher.';
            return;
        }
        ['withId' => $withId, 'hasPrice' => $hasPrice, 'hasSort' => $hasSort] = $parsed;

        $presentIds = [];
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            if ($withId) {
                $idRaw = trim((string) ($cols[0] ?? ''));
                $name = trim((string) ($cols[1] ?? ''));
                $locName = trim((string) ($cols[2] ?? ''));
                $unit = trim((string) ($cols[3] ?? ''));
                $minS = trim((string) ($cols[4] ?? ''));
                $maxS = trim((string) ($cols[5] ?? ''));
                $priceRaw = $hasPrice ? trim((string) ($cols[6] ?? '')) : '';
                $active = trim((string) ($cols[$hasPrice ? 7 : 6] ?? '1'));
                $sortRaw = $hasSort ? trim((string) ($cols[$hasPrice ? 8 : 7] ?? '')) : '';
            } else {
                $idRaw = '';
                $name = trim((string) ($cols[0] ?? ''));
                $locName = trim((string) ($cols[1] ?? ''));
                $unit = trim((string) ($cols[2] ?? ''));
                $minS = trim((string) ($cols[3] ?? ''));
                $maxS = trim((string) ($cols[4] ?? ''));
                $priceRaw = $hasPrice ? trim((string) ($cols[5] ?? '')) : '';
                $active = trim((string) ($cols[$hasPrice ? 6 : 5] ?? '1'));
                $sortRaw = $hasSort ? trim((string) ($cols[$hasPrice ? 7 : 6] ?? '')) : '';
            }

            $sortOrder = 0;
            if ($hasSort) {
                if ($sortRaw !== '' && !is_numeric($sortRaw)) {
                    $errors[] = "Zeile {$line}: sort_order muss eine Ganzzahl sein.";
                    continue;
                }
                $sortOrder = $sortRaw === '' ? 0 : (int) $sortRaw;
            }

            if ($name === '' || $locName === '') {
                $errors[] = "Zeile {$line}: name und location erforderlich.";
                continue;
            }
            $loc = $this->locations->findByName($locName);
            if ($loc === null) {
                $errors[] = "Zeile {$line}: Lagerort „{$locName}“ nicht gefunden.";
                continue;
            }
            if (!in_array($active, ['0', '1'], true)) {
                $errors[] = "Zeile {$line}: active ungültig.";
                continue;
            }
            $min = $minS === '' ? null : (int) $minS;
            $max = $maxS === '' ? null : (int) $maxS;
            $valuationPrice = null;
            $hasPriceInRow = false;
            if ($hasPrice) {
                $hasPriceInRow = true;
                if ($priceRaw !== '' && ValuationPrice::parse($priceRaw) === null) {
                    $errors[] = "Zeile {$line}: bewertungspreis ungültig (Zahl ≥ 0, Komma oder Punkt).";
                    continue;
                }
                $valuationPrice = ValuationPrice::parse($priceRaw);
            }

            if ($withId && $idRaw !== '') {
                if (!ctype_digit($idRaw)) {
                    $errors[] = "Zeile {$line}: id muss eine positive Ganzzahl sein oder leer.";
                    continue;
                }
                $eid = (int) $idRaw;
                if ($eid <= 0) {
                    $errors[] = "Zeile {$line}: id ungültig.";
                    continue;
                }
                $exist = $this->items->find($eid);
                if ($exist === null) {
                    $errors[] = "Zeile {$line}: Artikel-ID {$eid} nicht gefunden.";
                    continue;
                }
                $presentIds[] = $eid;
                $row = [
                    'target_id' => $eid,
                    'name' => $name,
                    'location_id' => (int) $loc['id'],
                    'unit' => $unit,
                    'min_stock' => $min,
                    'max_stock' => $max,
                    'active' => $active === '1',
                    'sort_order' => $sortOrder,
                ];
                if ($hasPriceInRow) {
                    $row['valuation_price'] = $valuationPrice;
                }
                $preview[] = $row;
            } elseif ($withId) {
                $row = [
                    'target_id' => null,
                    'name' => $name,
                    'location_id' => (int) $loc['id'],
                    'unit' => $unit,
                    'min_stock' => $min,
                    'max_stock' => $max,
                    'active' => $active === '1',
                    'sort_order' => $sortOrder,
                ];
                if ($hasPriceInRow) {
                    $row['valuation_price'] = $valuationPrice;
                }
                $preview[] = $row;
            } else {
                $exist = $this->items->findByName($name);
                $row = [
                    'target_id' => $exist !== null ? (int) $exist['id'] : null,
                    'name' => $name,
                    'location_id' => (int) $loc['id'],
                    'unit' => $unit,
                    'min_stock' => $min,
                    'max_stock' => $max,
                    'active' => $active === '1',
                    'sort_order' => $sortOrder,
                ];
                if ($hasPriceInRow) {
                    $row['valuation_price'] = $valuationPrice;
                }
                $preview[] = $row;
            }
        }

        if ($errors === [] && $withId && $presentIds !== []) {
            $itemsNotInCsv = $this->items->findActiveNotInIds($presentIds);
        }
    }

    /**
     * Massenpflege nur Bewertungspreise: id;bewertungspreis (name optional zur Kontrolle).
     *
     * @param list<list<string>> $dataRows
     */
    private function validateItemPrices(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        $h = array_map(static fn (string $c): string => strtolower(trim($c)), $header);
        if ($h[0] === 'valuation_price') {
            $h[0] = 'bewertungspreis';
        }
        foreach ($h as $i => $col) {
            if ($col === 'valuation_price') {
                $h[$i] = 'bewertungspreis';
            }
        }
        $withName = $h === ['id', 'name', 'bewertungspreis']
            || $h === ['id', 'name', 'location', 'unit', 'bewertungspreis'];
        $minimal = $h === ['id', 'bewertungspreis'];
        if (!$minimal && !$withName) {
            $errors[] = 'Bewertungspreise: Kopfzeile id;bewertungspreis oder Export artikel_bewertungspreise.csv (id;name;location;unit;bewertungspreis).';
            return;
        }
        $idIdx = 0;
        $priceIdx = array_search('bewertungspreis', $h, true);
        if ($priceIdx === false) {
            $errors[] = 'Spalte bewertungspreis fehlt.';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $idRaw = trim((string) ($cols[$idIdx] ?? ''));
            if ($idRaw === '' || !ctype_digit($idRaw)) {
                $errors[] = "Zeile {$line}: id fehlt oder ungültig.";
                continue;
            }
            $eid = (int) $idRaw;
            if ($eid <= 0) {
                $errors[] = "Zeile {$line}: id ungültig.";
                continue;
            }
            $exist = $this->items->find($eid);
            if ($exist === null) {
                $errors[] = "Zeile {$line}: Artikel-ID {$eid} nicht gefunden.";
                continue;
            }
            $priceRaw = trim((string) ($cols[$priceIdx] ?? ''));
            if ($priceRaw !== '' && ValuationPrice::parse($priceRaw) === null) {
                $errors[] = "Zeile {$line}: bewertungspreis ungültig.";
                continue;
            }
            $preview[] = [
                'target_id' => $eid,
                'valuation_price' => ValuationPrice::parse($priceRaw),
            ];
        }
    }

    /**
     * @return array{withId: bool, hasPrice: bool, hasSort: bool}|null
     */
    private function parseItemsCsvHeader(array $header): ?array
    {
        $hasSort = count($header) > 0 && end($header) === 'sort_order';
        $h = $hasSort ? array_slice($header, 0, -1) : $header;
        $h = array_map(static function (string $c): string {
            $c = strtolower(trim($c));
            return $c === 'valuation_price' ? 'bewertungspreis' : $c;
        }, $h);

        $baseWithId = ['id', 'name', 'location', 'unit', 'min_stock', 'max_stock', 'active'];
        $baseWithIdPrice = ['id', 'name', 'location', 'unit', 'min_stock', 'max_stock', 'bewertungspreis', 'active'];
        $baseLegacy = ['name', 'location', 'unit', 'min_stock', 'max_stock', 'active'];
        $baseLegacyPrice = ['name', 'location', 'unit', 'min_stock', 'max_stock', 'bewertungspreis', 'active'];

        if ($h === $baseWithId) {
            return ['withId' => true, 'hasPrice' => false, 'hasSort' => $hasSort];
        }
        if ($h === $baseWithIdPrice) {
            return ['withId' => true, 'hasPrice' => true, 'hasSort' => $hasSort];
        }
        if ($h === $baseLegacy) {
            return ['withId' => false, 'hasPrice' => false, 'hasSort' => $hasSort];
        }
        if ($h === $baseLegacyPrice) {
            return ['withId' => false, 'hasPrice' => true, 'hasSort' => $hasSort];
        }

        return null;
    }

    /** @param list<list<string>> $dataRows */
    private function validateItemSupplier(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        $withId = $header === ['item_id', 'item_name', 'supplier_name', 'priority'];
        $legacy = $header === ['item_name', 'supplier_name', 'priority'];
        if (!$withId && !$legacy) {
            $errors[] = 'Artikel–Lieferant: Kopfzeile mit ID: item_id;item_name;supplier_name;priority — oder item_name;supplier_name;priority';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            if ($withId) {
                $itemIdRaw = trim((string) ($cols[0] ?? ''));
                $iname = trim((string) ($cols[1] ?? ''));
                $sname = trim((string) ($cols[2] ?? ''));
                $pr = trim((string) ($cols[3] ?? '0'));
            } else {
                $itemIdRaw = '';
                $iname = trim((string) ($cols[0] ?? ''));
                $sname = trim((string) ($cols[1] ?? ''));
                $pr = trim((string) ($cols[2] ?? '0'));
            }
            if ($sname === '') {
                $errors[] = "Zeile {$line}: supplier_name erforderlich.";
                continue;
            }
            if (!ctype_digit($pr) && !is_numeric($pr)) {
                $errors[] = "Zeile {$line}: priority muss numerisch sein.";
                continue;
            }
            if ($itemIdRaw !== '') {
                if (!ctype_digit($itemIdRaw)) {
                    $errors[] = "Zeile {$line}: item_id muss eine Ganzzahl sein oder leer.";
                    continue;
                }
                $item = $this->items->find((int) $itemIdRaw);
                if ($item === null) {
                    $errors[] = "Zeile {$line}: Artikel-ID „{$itemIdRaw}“ nicht gefunden.";
                    continue;
                }
            } else {
                if ($iname === '') {
                    $errors[] = "Zeile {$line}: item_id leer — dann item_name erforderlich.";
                    continue;
                }
                $item = $this->items->findByName($iname);
                if ($item === null) {
                    $errors[] = "Zeile {$line}: Artikel „{$iname}“ nicht gefunden.";
                    continue;
                }
            }
            $sup = $this->suppliers->findByName($sname);
            if ($sup === null) {
                $errors[] = "Zeile {$line}: Lieferant „{$sname}“ nicht gefunden.";
                continue;
            }
            $preview[] = [
                'item_id' => (int) $item['id'],
                'supplier_id' => (int) $sup['id'],
                'priority' => (int) $pr,
            ];
        }
    }

    /** @param list<array<string, mixed>> $preview */
    public function execute(string $type, array $preview): array
    {
        $inserted = 0;
        $updated = 0;
        match ($type) {
            'locations' => $this->importLocations($preview, $inserted),
            'suppliers' => $this->importSuppliers($preview, $inserted, $updated),
            'delivery_days' => $this->importDeliveryDays($preview, $inserted),
            'items' => $this->importItems($preview, $inserted, $updated),
            'item_prices' => $this->importItemPrices($preview, $updated),
            'item_supplier' => $this->importItemSupplier($preview, $inserted),
            default => null,
        };
        return ['inserted' => $inserted, 'updated' => $updated];
    }

    /** @param list<array<string, mixed>> $preview */
    private function importLocations(array $preview, int &$inserted): void
    {
        foreach ($preview as $row) {
            $existing = $this->locations->findByName($row['name']);
            if ($existing) {
                $this->locations->update(
                    (int) $existing['id'],
                    $row['name'],
                    (int) $row['sort_order'],
                    (bool) $existing['active']
                );
            } else {
                $this->locations->create($row['name'], (int) $row['sort_order'], true);
                $inserted++;
            }
        }
    }

    /** @param list<array<string, mixed>> $preview */
    private function importSuppliers(array $preview, int &$inserted, int &$updated): void
    {
        foreach ($preview as $row) {
            $existing = $this->suppliers->findByName($row['name']);
            if ($existing) {
                $subjTpl = array_key_exists('email_subject_template', $row)
                    ? $row['email_subject_template']
                    : ($existing['email_subject_template'] ?? null);
                $this->suppliers->update(
                    (int) $existing['id'],
                    $row['name'],
                    $row['email'],
                    $existing['phone'] ?? null,
                    $existing['fax'] ?? null,
                    $existing['mobile'] ?? null,
                    $existing['street'] ?? null,
                    $existing['city'] ?? null,
                    $row['order_type'],
                    $existing['email_template'] ?? null,
                    $subjTpl,
                    $row['active'],
                    (bool) ($existing['attach_pdf'] ?? false)
                );
                $updated++;
            } else {
                $subjTpl = $row['email_subject_template'] ?? null;
                $this->suppliers->create(
                    $row['name'],
                    $row['email'],
                    null,
                    null,
                    null,
                    null,
                    null,
                    $row['order_type'],
                    null,
                    $subjTpl,
                    $row['active']
                );
                $inserted++;
            }
        }
    }

    /** @param list<array<string, mixed>> $preview */
    private function importDeliveryDays(array $preview, int &$inserted): void
    {
        foreach ($preview as $row) {
            $this->suppliers->setDeliveryWeekdays((int) $row['supplier_id'], $row['weekdays']);
            $inserted++;
        }
    }

    /** @param list<array<string, mixed>> $preview */
    private function importItems(array $preview, int &$inserted, int &$updated): void
    {
        foreach ($preview as $row) {
            $tid = isset($row['target_id']) && $row['target_id'] !== null ? (int) $row['target_id'] : 0;
            $sort = (int) ($row['sort_order'] ?? 0);
            $valuationPrice = null;
            $hasPrice = array_key_exists('valuation_price', $row);
            if ($hasPrice) {
                $valuationPrice = $row['valuation_price'];
            }
            if ($tid > 0) {
                if (!$hasPrice) {
                    $existing = $this->items->find($tid);
                    $valuationPrice = $existing !== null && $existing['valuation_price'] !== null && $existing['valuation_price'] !== ''
                        ? (float) $existing['valuation_price']
                        : null;
                }
                $this->items->update(
                    $tid,
                    $row['name'],
                    $row['unit'],
                    (int) $row['location_id'],
                    $row['min_stock'],
                    $row['max_stock'],
                    $row['active'],
                    $sort,
                    $valuationPrice
                );
                $updated++;
            } else {
                $this->items->create(
                    $row['name'],
                    $row['unit'],
                    (int) $row['location_id'],
                    $row['min_stock'],
                    $row['max_stock'],
                    $row['active'],
                    $sort,
                    $hasPrice ? $valuationPrice : null
                );
                $inserted++;
            }
        }
    }

    /** @param list<array<string, mixed>> $preview */
    private function importItemPrices(array $preview, int &$updated): void
    {
        foreach ($preview as $row) {
            $tid = (int) ($row['target_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $this->items->updateValuationPrice($tid, $row['valuation_price'] ?? null);
            $updated++;
        }
    }

    /** @param list<int> $ids */
    public function deactivateItemsByIds(array $ids): void
    {
        $this->items->deactivateByIds($ids);
    }

    /** @param list<array<string, mixed>> $preview */
    private function importItemSupplier(array $preview, int &$inserted): void
    {
        $byItem = [];
        foreach ($preview as $row) {
            $byItem[(int) $row['item_id']][] = [
                'supplier_id' => (int) $row['supplier_id'],
                'priority' => (int) $row['priority'],
            ];
        }
        foreach ($byItem as $itemId => $pairs) {
            $this->items->setSupplierLinks($itemId, $pairs);
            $inserted += count($pairs);
        }
    }
}
