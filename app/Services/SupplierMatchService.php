<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ItemRepository;
use App\Repositories\SupplierRepository;

/**
 * Resolves default supplier for an item on a target weekday (ISO: 1=Mon..7=Sun).
 */
final class SupplierMatchService
{
    public function __construct(
        private SupplierRepository $suppliers = new SupplierRepository(),
        private ItemRepository $items = new ItemRepository()
    ) {
    }

    /** @return list<int> supplier ids that deliver on weekday */
    public function suppliersDeliveringOnWeekday(int $weekday): array
    {
        $all = $this->suppliers->all(true);
        $out = [];
        foreach ($all as $s) {
            $days = $this->suppliers->deliveryWeekdays((int) $s['id']);
            if (in_array($weekday, $days, true)) {
                $out[] = (int) $s['id'];
            }
        }
        return $out;
    }

    /**
     * @return array{supplier_id: int|null, candidates: list<int>}
     */
    public function defaultSupplierForItem(int $itemId, int $targetWeekday): array
    {
        $links = $this->items->supplierLinksForItem($itemId);
        $delivering = $this->suppliersDeliveringOnWeekday($targetWeekday);
        $deliveringSet = array_flip($delivering);

        $eligible = [];
        foreach ($links as $link) {
            $sid = (int) $link['supplier_id'];
            if (isset($deliveringSet[$sid])) {
                $eligible[] = $link;
            }
        }

        usort($eligible, static fn ($a, $b) => (int) $b['priority'] <=> (int) $a['priority']);

        $candidates = array_map(static fn ($r) => (int) $r['supplier_id'], $eligible);
        $top = $eligible[0] ?? null;

        return [
            'supplier_id' => $top ? (int) $top['supplier_id'] : null,
            'candidates' => $candidates,
        ];
    }
}
