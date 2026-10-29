<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Helpers\Money;
use App\Repositories\ItemRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;
use App\Services\ItemMergeService;
use Tests\DbTestCase;

final class ItemMergeTest extends DbTestCase
{
    public function testMergeKeepsAllSupplierDataAndOldOrderNames(): void
    {
        $items = new ItemRepository();
        $articles = new SupplierArticleRepository();
        $target = $this->insertItem('Blütenhonig, Spender', 'Flasche (500 g)');
        $source = $this->insertItem('Honig in Spritzflaschen', 'Flasche');
        $items->setSupplierLinks($target, [['supplier_id' => 3, 'priority' => 0]]);
        $items->setSupplierLinks($source, [['supplier_id' => 2, 'priority' => 1], ['supplier_id' => 3, 'priority' => 2]]);
        $articles->saveDefaults($target, [3 => ['supplier_label' => 'Blütenhonig fl.Spend.TGQ 500g', 'supplier_sku' => '447621']]);
        $articles->saveDefaults($source, [3 => ['supplier_sku' => '447621']]);
        (new PriceRepository())->add([
            'supplier_article_id' => $articles->ensureDefault($source, 3), 'price_type' => 'purchase', 'amount' => Money::parse('3,34'),
            'per_qty' => Money::SCALE, 'per_unit' => 'order', 'price_date' => '2026-10-01', 'source' => 'invoice',
            'document_ref' => 'RE-1', 'note' => null, 'is_net' => true,
        ], null);

        (new ItemMergeService())->merge($source, $target, ['min_stock' => 'source'], null);

        // Quelle stillgelegt, nicht gelöscht
        $src = $items->find($source);
        self::assertSame(0, (int) $src['active']);
        self::assertSame($target, (int) $src['merged_into_id']);
        // Zuordnungen: Pütz neu, Transgourmet mit höherer Priorität
        $links = array_column($items->supplierLinksForItem($target), 'priority', 'supplier_id');
        self::assertSame(['3' => 2, '2' => 1], array_map('intval', $links));
        // Pütz bestellt weiter unter dem bisherigen Namen; TG-Standard bleibt der des Ziels
        $defaults = $articles->defaultsByItemIds([$target])[$target];
        self::assertSame('Honig in Spritzflaschen', $defaults[2]['supplier_label']);
        self::assertSame('Blütenhonig fl.Spend.TGQ 500g', $defaults[3]['supplier_label']);
        // Preis der Quelle ist erhalten und hängt jetzt am Ziel
        self::assertCount(1, (new PriceRepository())->historyForItem($target));
        // Vorher-Stand protokolliert
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM maintenance_log WHERE action = 'item_merged'")->fetchColumn());
    }

    public function testFailedMergeChangesNothing(): void
    {
        $a = $this->insertItem('A');
        $b = $this->insertItem('B');
        $merge = new ItemMergeService();
        $merge->merge($a, $b, [], null);

        try {
            $merge->merge($a, $b, [], null);
            self::fail('Zweites Zusammenführen muss abgelehnt werden.');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM maintenance_log')->fetchColumn());
    }
}
