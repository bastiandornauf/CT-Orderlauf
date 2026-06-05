<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Middleware\AuthMiddleware;
use PDO;

final class ExportController
{
    public function locations(): void
    {
        AuthMiddleware::requireEditor();
        $rows = Database::pdo()->query(
            'SELECT name, sort_order FROM locations ORDER BY sort_order'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->sendCsv('lagerorte.csv', ['name', 'sort_order'], $rows);
    }

    public function suppliers(): void
    {
        AuthMiddleware::requireEditor();
        $rows = Database::pdo()->query(
            'SELECT name, email, order_type AS type, active, email_subject_template FROM suppliers ORDER BY name'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->sendCsv('lieferanten.csv', ['name', 'email', 'type', 'active', 'email_subject_template'], $rows);
    }

    public function deliveryDays(): void
    {
        AuthMiddleware::requireEditor();
        $rows = Database::pdo()->query(
            'SELECT s.name AS supplier_name,
                    GROUP_CONCAT(sdd.weekday ORDER BY sdd.weekday SEPARATOR \',\') AS delivery_days
             FROM suppliers s
             LEFT JOIN supplier_delivery_days sdd ON sdd.supplier_id = s.id
             GROUP BY s.id, s.name
             ORDER BY s.name'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->sendCsv('liefertage.csv', ['supplier_name', 'delivery_days'], $rows);
    }

    public function items(): void
    {
        AuthMiddleware::requireEditor();
        $priceCol = $this->hasValuationPriceColumn()
            ? 'i.valuation_price AS bewertungspreis'
            : 'NULL AS bewertungspreis';
        $rows = Database::pdo()->query(
            "SELECT i.id, i.name, l.name AS location, i.unit, i.min_stock, i.max_stock, {$priceCol}, i.active, i.sort_order
             FROM items i
             JOIN locations l ON l.id = i.location_id
             ORDER BY l.sort_order, i.sort_order, i.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if ($row['bewertungspreis'] !== null && $row['bewertungspreis'] !== '') {
                $row['bewertungspreis'] = number_format((float) $row['bewertungspreis'], 2, ',', '');
            } else {
                $row['bewertungspreis'] = '';
            }
        }
        unset($row);

        $this->sendCsv(
            'artikel.csv',
            ['id', 'name', 'location', 'unit', 'min_stock', 'max_stock', 'bewertungspreis', 'active', 'sort_order'],
            $rows
        );
    }

    public function itemPrices(): void
    {
        AuthMiddleware::requireEditor();
        $priceCol = $this->hasValuationPriceColumn()
            ? 'i.valuation_price AS bewertungspreis'
            : 'NULL AS bewertungspreis';
        $rows = Database::pdo()->query(
            "SELECT i.id, i.name, l.name AS location, i.unit, {$priceCol}
             FROM items i
             JOIN locations l ON l.id = i.location_id
             WHERE i.active = 1
             ORDER BY l.sort_order, i.sort_order, i.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            if ($row['bewertungspreis'] !== null && $row['bewertungspreis'] !== '') {
                $row['bewertungspreis'] = number_format((float) $row['bewertungspreis'], 2, ',', '');
            } else {
                $row['bewertungspreis'] = '';
            }
        }
        unset($row);

        $this->sendCsv('artikel_bewertungspreise.csv', ['id', 'name', 'location', 'unit', 'bewertungspreis'], $rows);
    }

    public function itemSupplier(): void
    {
        AuthMiddleware::requireEditor();
        $rows = Database::pdo()->query(
            'SELECT i.id AS item_id, i.name AS item_name, s.name AS supplier_name, isl.priority
             FROM item_supplier isl
             JOIN items i ON i.id = isl.item_id
             JOIN suppliers s ON s.id = isl.supplier_id
             ORDER BY i.name, isl.priority DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->sendCsv('artikel_lieferanten.csv', ['item_id', 'item_name', 'supplier_name', 'priority'], $rows);
    }

    /**
     * @param list<string> $header
     * @param list<array<string, mixed>> $rows
     */
    private function hasValuationPriceColumn(): bool
    {
        $stmt = Database::pdo()->query("SHOW COLUMNS FROM items LIKE 'valuation_price'");

        return $stmt->fetch() !== false;
    }

    private function sendCsv(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        // UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, $header, ';', '"', '\\');
        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $col) {
                $line[] = (string) ($row[$col] ?? '');
            }
            fputcsv($out, $line, ';', '"', '\\');
        }
        fclose($out);
        exit;
    }
}
