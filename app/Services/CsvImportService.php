<?php

declare(strict_types=1);

namespace App\Services;

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
     * @return array{ok: bool, errors: list<string>, preview: list<array<string, mixed>>}
     */
    public function preview(string $type, array $rows): array
    {
        $errors = [];
        $preview = [];
        if ($rows === []) {
            return ['ok' => false, 'errors' => ['Leere Datei.'], 'preview' => []];
        }
        $header = array_map('trim', $rows[0]);
        $dataRows = array_slice($rows, 1);

        match ($type) {
            'locations' => $this->validateLocations($header, $dataRows, $errors, $preview),
            'suppliers' => $this->validateSuppliers($header, $dataRows, $errors, $preview),
            'delivery_days' => $this->validateDeliveryDays($header, $dataRows, $errors, $preview),
            'items' => $this->validateItems($header, $dataRows, $errors, $preview),
            'item_supplier' => $this->validateItemSupplier($header, $dataRows, $errors, $preview),
            default => $errors[] = 'Unbekannter Importtyp.',
        };

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'preview' => $preview,
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
        if ($header !== ['name', 'email', 'type', 'active']) {
            $errors[] = 'Kopfzeile muss sein: name;email;type;active';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $name = trim($cols[0] ?? '');
            $email = trim($cols[1] ?? '');
            $type = trim($cols[2] ?? '');
            $active = trim($cols[3] ?? '1');
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
            $preview[] = [
                'name' => $name,
                'email' => $email ?: null,
                'order_type' => $type,
                'active' => $active === '1',
            ];
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

    /** @param list<list<string>> $dataRows */
    private function validateItems(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        if ($header !== ['name', 'location', 'unit', 'min_stock', 'max_stock', 'active']) {
            $errors[] = 'Kopfzeile muss sein: name;location;unit;min_stock;max_stock;active';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $name = trim($cols[0] ?? '');
            $locName = trim($cols[1] ?? '');
            $unit = trim($cols[2] ?? '');
            $minS = trim($cols[3] ?? '');
            $maxS = trim($cols[4] ?? '');
            $active = trim($cols[5] ?? '1');
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
            $preview[] = [
                'name' => $name,
                'location_id' => (int) $loc['id'],
                'unit' => $unit,
                'min_stock' => $min,
                'max_stock' => $max,
                'active' => $active === '1',
            ];
        }
    }

    /** @param list<list<string>> $dataRows */
    private function validateItemSupplier(array $header, array $dataRows, array &$errors, array &$preview): void
    {
        if ($header !== ['item_name', 'supplier_name', 'priority']) {
            $errors[] = 'Kopfzeile muss sein: item_name;supplier_name;priority';
            return;
        }
        foreach ($dataRows as $i => $cols) {
            $line = $i + 2;
            $iname = trim($cols[0] ?? '');
            $sname = trim($cols[1] ?? '');
            $pr = trim($cols[2] ?? '0');
            if ($iname === '' || $sname === '') {
                $errors[] = "Zeile {$line}: item_name und supplier_name erforderlich.";
                continue;
            }
            if (!ctype_digit($pr) && !is_numeric($pr)) {
                $errors[] = "Zeile {$line}: priority muss numerisch sein.";
                continue;
            }
            $item = $this->items->findByName($iname);
            if ($item === null) {
                $errors[] = "Zeile {$line}: Artikel „{$iname}“ nicht gefunden.";
                continue;
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
                $this->suppliers->update(
                    (int) $existing['id'],
                    $row['name'],
                    $row['email'],
                    $row['order_type'],
                    $existing['email_template'],
                    $row['active']
                );
                $updated++;
            } else {
                $this->suppliers->create(
                    $row['name'],
                    $row['email'],
                    $row['order_type'],
                    null,
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
            $existing = $this->items->findByName($row['name']);
            if ($existing) {
                $this->items->update(
                    (int) $existing['id'],
                    $row['name'],
                    $row['unit'],
                    (int) $row['location_id'],
                    $row['min_stock'],
                    $row['max_stock'],
                    $row['active']
                );
                $updated++;
            } else {
                $this->items->create(
                    $row['name'],
                    $row['unit'],
                    (int) $row['location_id'],
                    $row['min_stock'],
                    $row['max_stock'],
                    $row['active']
                );
                $inserted++;
            }
        }
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
