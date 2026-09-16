<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SupplierRepository;
use DateTimeImmutable;

/**
 * Nächstmögliches Lieferdatum je Lieferant ab Ziel-Datum (max. 7 Tage Fenster).
 */
final class SupplierDeliveryService
{
    public function __construct(
        private SupplierRepository $suppliers = new SupplierRepository()
    ) {
    }

    /**
     * @return array{target_date: string, suppliers: list<array{
     *   id: int,
     *   name: string,
     *   order_type: string,
     *   delivery_date: string|null,
     *   on_target: bool
     * }>}
     */
    public function previewForTargetDate(string $targetDateYmd): array
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $targetDateYmd);
        if ($dt === false) {
            throw new \InvalidArgumentException('target_date muss YYYY-MM-DD sein.');
        }

        $rows = [];
        foreach ($this->suppliers->all(true) as $s) {
            $sid = (int) $s['id'];
            $days = $this->suppliers->deliveryWeekdays($sid);
            $deliveryDate = null;
            if ($days !== []) {
                for ($offset = 0; $offset <= 6; $offset++) {
                    $candidate = $dt->modify("+{$offset} days");
                    if (in_array((int) $candidate->format('N'), $days, true)) {
                        $deliveryDate = $candidate->format('Y-m-d');
                        break;
                    }
                }
            }
            $rows[] = [
                'id' => $sid,
                'name' => (string) $s['name'],
                'order_type' => (string) ($s['order_type'] ?? 'mail'),
                'delivery_date' => $deliveryDate,
                'on_target' => $deliveryDate === $targetDateYmd,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['on_target'] !== $b['on_target']) {
                return $a['on_target'] ? -1 : 1;
            }
            $da = $a['delivery_date'] ?? '9999-99-99';
            $db = $b['delivery_date'] ?? '9999-99-99';
            if ($da !== $db) {
                return $da <=> $db;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        return [
            'target_date' => $targetDateYmd,
            'suppliers' => $rows,
        ];
    }
}
