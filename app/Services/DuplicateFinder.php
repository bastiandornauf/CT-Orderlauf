<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use PDO;

/**
 * Findet mögliche Dubletten im Artikelbestand. Ergebnis ist nur ein Vorschlag mit Begründung –
 * zusammengeführt wird ausschließlich nach Entscheidung des Benutzers.
 *
 * Score 0…1: Wortübereinstimmung (nach Normalisierung und Synonymen) plus starke Signale
 * (gleiche Art.-Nr./EAN), minus Unterschiede in Varianten-Merkmalen (glatt/kraus, frisch/TK, 1,5 %/3,5 % …).
 */
final class DuplicateFinder
{
    public const MIN_SCORE = 0.55;

    /** @var array<string, string> Wort → Normalform */
    private array $synonym = [];
    /** @var list<array<string, true>> */
    private array $opposites = [];
    /** @var array<string, true> */
    private array $ignore = [];

    public function __construct(?array $config = null)
    {
        $config ??= require dirname(__DIR__) . '/Data/synonyms.php';
        foreach ($config['synonyms'] as $group) {
            foreach ($group as $w) {
                $this->synonym[$w] = $group[0];
            }
        }
        foreach ($config['opposites'] as $group) {
            $this->opposites[] = array_fill_keys(array_map(fn (string $w): string => $this->synonym[$w] ?? $w, $group), true);
        }
        $this->ignore = array_fill_keys($config['ignore'], true);
    }

    public static function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'é' => 'e', 'è' => 'e']);
        $s = preg_replace('/(\p{L})-(\p{L})/u', '$1$2', $s) ?? $s;   // „Salat-Mayonnaise“ = „Salatmayonnaise“
        // Mehrwort-Merkmale zusammenziehen, damit sie als ein Token zählen
        // Größenangaben („Gr. XL“) als eigenes Merkmal, sonst verschwinden sie zwischen den Einheiten
        $s = preg_replace('/\bgr(?:oesse)?\.?\s*(xxl|xl|xs|s|m|l)\b/u', 'gr$1', $s) ?? $s;
        $s = preg_replace(['/\bo\.\s*stein\b/u', '/\bohne\s+stein\b/u', '/\bohne\s+kern\b/u', '/\bmit\s+stein\b/u', '/\bohne\s+schale\b/u'],
            ['ohnestein', 'ohnestein', 'ohnekern', 'mitstein', 'ohneschale'], $s) ?? $s;

        return $s;
    }

    /**
     * Wörter (normalisiert, Synonyme aufgelöst) und Zahlenangaben eines Artikels.
     *
     * @return array{words: array<string, true>, numbers: array<string, true>}
     */
    public function tokens(string $text): array
    {
        $s = self::normalize($text);
        // Varianten-Zahlen: Fettstufen (1,5 %) und Maße (160×240)
        preg_match_all('/\d+(?:[.,]\d+)?\s*%|\d+\s*[x×]\s*\d+(?:\s*[x×]\s*\d+)?/u', $s, $pct);
        preg_match_all('/[a-z]+/u', $s, $m);
        $words = [];
        foreach ($m[0] as $w) {
            if (isset($this->ignore[$w])) {
                continue;
            }
            $w = $this->synonym[$w] ?? $w;
            if (strlen($w) >= 2 || $this->isOpposite($w)) {
                $words[$w] = true;
            }
        }
        $numbers = [];
        foreach ($pct[0] as $p) {
            $numbers[str_replace([' ', ',', '×'], ['', '.', 'x'], $p)] = true;
        }

        return ['words' => $words, 'numbers' => $numbers];
    }

    private function isOpposite(string $w): bool
    {
        foreach ($this->opposites as $g) {
            if (isset($g[$w])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vergleich zweier Artikel.
     *
     * @param array{name:string, alias?:?string, skus?:list<string>} $a
     * @param array{name:string, alias?:?string, skus?:list<string>} $b
     * @return array{score: float, reasons: list<string>}
     */
    public function compare(array $a, array $b): array
    {
        // Name und Alias getrennt vergleichen, bester Treffer zählt
        $best = null;
        foreach (array_filter([$a['name'], $a['alias'] ?? null]) as $na) {
            foreach (array_filter([$b['name'], $b['alias'] ?? null]) as $nb) {
                $r = $this->compareTexts($na, $nb);
                if ($best === null || $r['score'] > $best['score']) {
                    $best = $r;
                }
            }
        }
        // Varianten-Merkmale aus Name UND Alias, damit z. B. „dkl.“ im Lieferantennamen nicht durchrutscht
        $variant = $this->variantPenalty(
            $this->tokens($a['name'] . ' ' . ($a['alias'] ?? '')),
            $this->tokens($b['name'] . ' ' . ($b['alias'] ?? ''))
        );
        $best['score'] = max(0.0, $best['score'] - $variant['penalty']);
        array_push($best['reasons'], ...$variant['reasons']);

        $skus = array_intersect($a['skus'] ?? [], $b['skus'] ?? []);
        if ($skus !== []) {
            $best['score'] = max($best['score'], 0.95);
            $best['reasons'][] = 'gleiche Art.-Nr. ' . implode(', ', $skus);
        }
        $best['score'] = round(min(1.0, $best['score']), 2);

        return $best;
    }

    /**
     * Ähnlichkeit zweier Texte (ohne Varianten-Abzug): Anteil gemeinsamer Wörter.
     *
     * @return array{score: float, reasons: list<string>}
     */
    private function compareTexts(string $na, string $nb): array
    {
        $ca = $this->tokens($na)['words'];
        $cb = $this->tokens($nb)['words'];
        if ($ca === [] || $cb === []) {
            return ['score' => 0.0, 'reasons' => []];
        }
        $common = [];
        foreach (array_keys($ca) as $w) {
            foreach (array_keys($cb) as $v) {
                if (self::wordsMatch($w, $v)) {
                    $common[] = $w === $v ? $w : "{$w}~{$v}";
                    break;
                }
            }
        }
        $m = count($common);
        $score = $m / (count($ca) + count($cb) - $m);
        // Kürzerer Text (mind. 2 Wörter) komplett im längeren enthalten – ein einzelnes Wort wie „Eis“ reicht nicht
        if (min(count($ca), count($cb)) >= 2 && $m === min(count($ca), count($cb))) {
            $score = max($score, 0.75);
        }

        return ['score' => $score, 'reasons' => $common === [] ? [] : ['gleiche Wörter: ' . implode(', ', $common)]];
    }

    /**
     * Abzug für unterschiedliche Varianten-Merkmale (glatt/kraus, 1,5 %/3,5 %, Portionen/Einzelgebinde).
     *
     * @return array{penalty: float, reasons: list<string>}
     */
    private function variantPenalty(array $ta, array $tb): array
    {
        $penalty = 0.0;
        $reasons = [];
        foreach ($this->opposites as $group) {
            $wa = array_intersect_key($group, $ta['words']);
            $wb = array_intersect_key($group, $tb['words']);
            if ($wa !== [] && $wb !== [] && array_intersect_key($wa, $wb) === []) {
                $penalty += 0.5;
                $reasons[] = 'Variante: ' . implode('/', array_keys($wa)) . ' ↔ ' . implode('/', array_keys($wb));
            }
        }
        $na = $ta['numbers'];
        $nb = $tb['numbers'];
        if ($na !== [] && $nb !== [] && array_intersect_key($na, $nb) === []) {
            $penalty += 0.5;
            $reasons[] = 'Variante: ' . implode('/', array_keys($na)) . ' ↔ ' . implode('/', array_keys($nb));
        }
        $packA = preg_grep('/x/', array_keys($na));
        $packB = preg_grep('/x/', array_keys($nb));
        if (($packA === []) !== ($packB === [])) {
            $penalty += 0.5;
            $reasons[] = 'Variante: Portions-/Mehrfachpackung ↔ Einzelgebinde';
        }

        return ['penalty' => $penalty, 'reasons' => $reasons];
    }

    private static function wordsMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        // Nur Tippfehler, keine Wortteile: „Puderzucker“ ist kein „Zucker“, „Kichererbsen“ keine „Erbsen“
        return min(strlen($a), strlen($b)) >= 6 && levenshtein($a, $b) <= 1;
    }

    /**
     * Vorschläge aus dem aktiven Bestand, ohne bereits entschiedene Paare.
     *
     * @return list<array{a: array, b: array, score: float, reasons: list<string>, decision: ?string}>
     */
    public function suggestions(bool $includeLater = true): array
    {
        $pdo = Database::pdo();
        $items = $pdo->query(
            'SELECT i.id, i.name, i.alias, i.unit, l.name AS location FROM items i JOIN locations l ON l.id = i.location_id WHERE i.active = 1'
        )->fetchAll(PDO::FETCH_ASSOC);
        $skus = [];
        foreach ($pdo->query('SELECT item_id, supplier_id, supplier_sku FROM supplier_articles WHERE supplier_sku IS NOT NULL AND active = 1') as $r) {
            $skus[(int) $r['item_id']][] = $r['supplier_id'] . ':' . $r['supplier_sku'];
        }
        $decisions = [];
        foreach ($pdo->query('SELECT item_a, item_b, decision FROM dedupe_decisions') as $r) {
            $decisions[$r['item_a'] . '-' . $r['item_b']] = $r['decision'];
        }
        $out = [];
        $n = count($items);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                [$a, $b] = (int) $items[$i]['id'] < (int) $items[$j]['id'] ? [$items[$i], $items[$j]] : [$items[$j], $items[$i]];
                $decision = $decisions[$a['id'] . '-' . $b['id']] ?? null;
                if ($decision === 'separate' || ($decision === 'later' && !$includeLater)) {
                    continue;
                }
                $r = $this->compare(
                    $a + ['skus' => $skus[(int) $a['id']] ?? []],
                    $b + ['skus' => $skus[(int) $b['id']] ?? []]
                );
                if ($r['score'] >= self::MIN_SCORE) {
                    $out[] = ['a' => $a, 'b' => $b, 'decision' => $decision] + $r;
                }
            }
        }
        usort($out, static fn (array $x, array $y): int => [$x['decision'] === 'later', -$x['score']] <=> [$y['decision'] === 'later', -$y['score']]);

        return $out;
    }
}
