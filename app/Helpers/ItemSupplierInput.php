<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Repositories\SupplierArticleRepository;

/**
 * Lieferantenzeilen aus Formular oder JSON → Zuordnungen + Lieferantenartikel.
 */
final class ItemSupplierInput
{
    /**
     * @param list<mixed> $rows je Zeile supplier_id, priority und optional die Artikelfelder
     * @return array{pairs: list<array{supplier_id:int, priority:int}>, articles: ?array<int, array<string, mixed>>}
     *         articles = null, wenn keine Zeile Artikelfelder mitbringt (z. B. Schnellbearbeitung im Rundgang)
     */
    public static function parse(array $rows): array
    {
        $pairs = [];
        $articles = [];
        $hasArticleFields = false;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sid = (int) ($row['supplier_id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $pairs[] = ['supplier_id' => $sid, 'priority' => (int) ($row['priority'] ?? 0)];
            $fields = [];
            foreach (SupplierArticleRepository::EDIT_FIELDS as $f) {
                if (array_key_exists($f, $row)) {
                    $hasArticleFields = true;
                    $fields[$f] = $row[$f];
                }
            }
            if ($fields !== []) {
                $articles[$sid] = $fields;
            }
        }

        return ['pairs' => $pairs, 'articles' => $hasArticleFields ? $articles : null];
    }

    /**
     * Formular-POST mit parallelen Arrays supplier_id[i], supplier_priority[i], supplier_label[i] …
     *
     * @param array<string, mixed> $post
     * @return list<array<string, mixed>>
     */
    public static function rowsFromPost(array $post): array
    {
        $rows = [];
        $ids = is_array($post['supplier_id'] ?? null) ? $post['supplier_id'] : [];
        foreach ($ids as $i => $sid) {
            $row = ['supplier_id' => $sid, 'priority' => $post['supplier_priority'][$i] ?? 0];
            foreach (SupplierArticleRepository::EDIT_FIELDS as $f) {
                if (isset($post[$f]) && is_array($post[$f])) {
                    $row[$f] = $post[$f][$i] ?? null;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
