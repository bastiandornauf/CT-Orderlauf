<?php

declare(strict_types=1);

namespace Tests\Db;

use App\Helpers\Money;
use App\Repositories\ItemRepository;
use App\Repositories\PriceRepository;
use App\Repositories\SupplierArticleRepository;
use Tests\DbTestCase;

final class PricesTest extends DbTestCase
{
    private int $itemId;
    private int $articleId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->itemId = $this->insertItem('Steakhouse Fries', 'Karton');
        (new ItemRepository())->setSupplierLinks($this->itemId, [['supplier_id' => 2, 'priority' => 1]]);
        $articles = new SupplierArticleRepository();
        $articles->saveDefaults($this->itemId, [2 => ['pack_size' => '10', 'pack_unit' => 'kg']]);
        $this->articleId = $articles->ensureDefault($this->itemId, 2);
    }

    private function price(string $amount, string $date, string $type = 'known', ?string $doc = null): array
    {
        return [
            'supplier_article_id' => $this->articleId,
            'price_type' => $type,
            'amount' => Money::parse($amount),
            'per_qty' => Money::SCALE,
            'per_unit' => 'order',
            'price_date' => $date,
            'source' => $doc ? 'invoice' : 'manual',
            'document_ref' => $doc,
            'note' => null,
            'is_net' => true,
        ];
    }

    public function testSamePriceAndDocumentIsNotStoredTwice(): void
    {
        $repo = new PriceRepository();
        $first = $repo->add($this->price('24,80', '2026-10-01', 'purchase', 'RE-123'), null);
        $again = $repo->add($this->price('24.8', '2026-10-01', 'purchase', ' re-123 '), null);

        self::assertTrue($first['created']);
        self::assertFalse($again['created']);
        self::assertSame($first['id'], $again['id']);
        self::assertCount(1, $repo->historyForItem($this->itemId));
    }

    public function testNewPricesKeepHistoryAndLatestWins(): void
    {
        $repo = new PriceRepository();
        $repo->add($this->price('22,90', '2026-09-01'), null);
        $repo->add($this->price('24,80', '2026-10-15'), null);
        $repo->add($this->price('23,50', '2026-10-01', 'purchase', 'RE-9'), null);

        $history = $repo->historyForItem($this->itemId);
        self::assertSame(['2026-10-15', '2026-10-01', '2026-09-01'], array_column($history, 'price_date'));

        $latest = $repo->latestForDefaults()[$this->itemId][2];
        self::assertSame('24,80', $latest['amount']);
        self::assertSame('Karton', $latest['per_label']);
        self::assertSame(['amount' => '2,48', 'base' => 'kg'], $latest['comparison']);
    }

    public function testEnsureDefaultReusesExistingArticle(): void
    {
        self::assertSame($this->articleId, (new SupplierArticleRepository())->ensureDefault($this->itemId, 2));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM supplier_articles')->fetchColumn());
    }
}
