<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Repositories\ItemRepository;
use App\Repositories\SupplierArticleRepository;
use Tests\DbTestCase;

final class SupplierArticlesTest extends DbTestCase
{
    public function testSavingLinksKeepsExistingRowIds(): void
    {
        $items = new ItemRepository();
        $id = $this->insertItem('Oliven schwarz');
        $items->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1], ['supplier_id' => 3, 'priority' => 0]]);
        $before = $this->linkIds($id);

        $items->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 5], ['supplier_id' => 3, 'priority' => 0]]);

        self::assertSame($before, $this->linkIds($id));
        self::assertSame(5, (int) $this->pdo->query("SELECT priority FROM item_supplier WHERE item_id = {$id} AND supplier_id = 2")->fetchColumn());
    }

    public function testRemovingALinkDeletesOnlyThatLink(): void
    {
        $items = new ItemRepository();
        $id = $this->insertItem('Oliven schwarz');
        $items->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1], ['supplier_id' => 3, 'priority' => 0]]);

        $items->setSupplierLinks($id, [['supplier_id' => 3, 'priority' => 0]]);

        self::assertSame([3], array_keys($this->linkIds($id)));
    }

    public function testArticleSurvivesLinkRebuildByOlderAppVersion(): void
    {
        $id = $this->insertItem('Oliven schwarz');
        (new ItemRepository())->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1]]);
        (new SupplierArticleRepository())->saveDefaults($id, [2 => ['supplier_label' => 'Oliven schwarz o. Stein 4,25 kg', 'supplier_sku' => '4711']]);

        // So speichert die bisherige Version: alles löschen, neu anlegen
        $this->pdo->exec("DELETE FROM item_supplier WHERE item_id = {$id}");
        $this->pdo->exec("INSERT INTO item_supplier (item_id, supplier_id, priority) VALUES ({$id}, 2, 1)");

        $article = (new SupplierArticleRepository())->defaultsByItemIds([$id])[$id][2] ?? null;
        self::assertSame('Oliven schwarz o. Stein 4,25 kg', $article['supplier_label'] ?? null);
        self::assertSame('4711', $article['supplier_sku'] ?? null);
    }

    public function testSaveDefaultsUpdatesInPlaceAndSkipsEmptyNewRows(): void
    {
        $repo = new SupplierArticleRepository();
        $id = $this->insertItem('Oliven schwarz');
        $repo->saveDefaults($id, [
            2 => ['supplier_label' => 'Alt', 'pack_size' => '4,25', 'pack_unit' => 'kg'],
            3 => ['supplier_label' => '', 'supplier_sku' => ''],
        ]);
        $repo->saveDefaults($id, [2 => ['supplier_label' => 'Neu', 'pack_size' => '4,25', 'pack_unit' => 'kg']]);

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM supplier_articles')->fetchColumn());
        $row = $repo->defaultsByItemIds([$id])[$id][2];
        self::assertSame('Neu', $row['supplier_label']);
        self::assertSame(4.25, $row['pack_size']);
        self::assertSame('kg', $row['pack_unit']);
    }

    public function testRemovingSupplierLinkKeepsArticle(): void
    {
        $id = $this->insertItem('Oliven schwarz');
        $items = new ItemRepository();
        $items->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1]]);
        (new SupplierArticleRepository())->saveDefaults($id, [2 => ['supplier_sku' => '4711']]);

        $items->setSupplierLinks($id, []);

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM supplier_articles')->fetchColumn());
    }

    public function testSearchFindsAliasAndSupplierData(): void
    {
        $items = new ItemRepository();
        $id = $this->insertItem('Farmfrites Steakhouse Fries', 'Karton');
        $items->updateAlias($id, 'Pommes');
        $items->setSupplierLinks($id, [['supplier_id' => 2, 'priority' => 1]]);
        (new SupplierArticleRepository())->saveDefaults($id, [2 => ['supplier_label' => 'Steakhouse 10 kg', 'supplier_sku' => 'FF-1234']]);
        $this->insertItem('Oliven schwarz');

        foreach (['pommes', 'Steakhouse 10', 'FF-12'] as $q) {
            $found = array_column($items->allForList(null, 'all', $q), 'id');
            self::assertSame([$id], array_map('intval', $found), "Suche nach {$q}");
        }
    }

    public function testEmptyAliasIsStoredAsNull(): void
    {
        $items = new ItemRepository();
        $id = $this->insertItem('Oliven schwarz');
        $items->updateAlias($id, '  ');

        self::assertNull($items->find($id)['alias']);
    }

    /** @return array<int, int> supplier_id → item_supplier.id */
    private function linkIds(int $itemId): array
    {
        $rows = $this->pdo->query("SELECT supplier_id, id FROM item_supplier WHERE item_id = {$itemId} ORDER BY supplier_id")->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['supplier_id']] = (int) $r['id'];
        }

        return $out;
    }
}
