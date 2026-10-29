<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\ItemDisplay;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Services\DuplicateFinder;
use App\Services\ItemMergeService;
use App\Services\ItemQualityService;

/** Datenpflege: Prüfungen, Dubletten-Vorschläge, Zusammenführen */
final class MaintenanceController
{
    public function index(): void
    {
        AuthMiddleware::requireEditor();
        $counts = (new ItemQualityService())->counts();
        $groups = [];
        foreach (ItemQualityService::CHECKS as $key => [$title, $severity, $group, $help]) {
            $groups[$group][] = ['key' => $key, 'title' => $title, 'severity' => $severity, 'help' => $help, 'count' => $counts[$key],
                'href' => '/items?active=1&check=' . urlencode($key)];
        }
        $dupes = count((new DuplicateFinder())->suggestions(false));
        array_unshift($groups['Stammdaten'], ['key' => 'duplicates', 'title' => 'Mögliche Dubletten', 'severity' => 'hint',
            'help' => 'Ähnliche Artikel prüfen und ggf. zusammenführen.', 'count' => $dupes, 'href' => '/pflege/dubletten']);

        View::layout('layout', 'pages/maintenance/index', [
            'title' => 'Datenpflege',
            'groups' => $groups,
            'errors' => array_sum(array_map(
                static fn (string $k): int => ItemQualityService::CHECKS[$k][1] === 'error' ? $counts[$k] : 0,
                array_keys($counts)
            )),
        ]);
    }

    public function duplicates(): void
    {
        AuthMiddleware::requireEditor();
        View::layout('layout', 'pages/maintenance/duplicates', [
            'title' => 'Mögliche Dubletten',
            'pairs' => (new DuplicateFinder())->suggestions(true),
            'csrf' => Csrf::token(),
        ]);
    }

    /** POST: Paar „getrennt lassen“ oder „später“ */
    public function decide(): void
    {
        AuthMiddleware::requireEditor();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/pflege/dubletten');
        }
        $a = (int) ($_POST['a'] ?? 0);
        $b = (int) ($_POST['b'] ?? 0);
        $decision = (string) ($_POST['decision'] ?? '');
        if ($a > 0 && $b > 0 && $a !== $b && in_array($decision, ['separate', 'later'], true)) {
            Database::pdo()->prepare(
                'INSERT INTO dedupe_decisions (item_a, item_b, decision, decided_by, decided_at) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE decision = VALUES(decision), decided_by = VALUES(decided_by), decided_at = VALUES(decided_at)'
            )->execute([min($a, $b), max($a, $b), $decision, self::userId(), (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
            $_SESSION['flash_ok'] = $decision === 'separate' ? 'Bleiben getrennt – Vorschlag erscheint nicht mehr.' : 'Zurückgestellt.';
        }
        Response::redirect('/pflege/dubletten');
    }

    public function mergeForm(): void
    {
        AuthMiddleware::requireEditor();
        $service = new ItemMergeService();
        $a = $service->describe((int) ($_GET['a'] ?? 0));
        $b = $service->describe((int) ($_GET['b'] ?? 0));
        if ($a === null || $b === null || $a['id'] === $b['id']) {
            $_SESSION['flash_ok'] = 'Bitte zwei verschiedene Artikel wählen.';
            Response::redirect('/pflege/dubletten');
        }
        // Vorschlag fürs Ziel: der Artikel mit mehr Preisen/Lieferanten, sonst der ältere
        $score = static fn (array $i): array => [$i['price_count'], count($i['links']), -(int) $i['id']];
        $target = $score($a) >= $score($b) ? 'a' : 'b';

        View::layout('layout', 'pages/maintenance/merge', [
            'title' => 'Artikel zusammenführen',
            'a' => $a,
            'b' => $b,
            'target' => $target,
            'error' => $_SESSION['merge_error'] ?? null,
            'csrf' => Csrf::token(),
        ]);
        unset($_SESSION['merge_error']);
    }

    public function merge(): void
    {
        AuthMiddleware::requireEditor();
        $a = (int) ($_POST['a'] ?? 0);
        $b = (int) ($_POST['b'] ?? 0);
        $back = '/pflege/zusammenfuehren?a=' . $a . '&b=' . $b;
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect($back);
        }
        if (empty($_POST['confirm'])) {
            $_SESSION['merge_error'] = 'Bitte bestätigen, dass die Zusammenführung geprüft ist.';
            Response::redirect($back);
        }
        $targetSide = ($_POST['target'] ?? 'a') === 'b' ? 'b' : 'a';
        [$targetId, $sourceId] = $targetSide === 'a' ? [$a, $b] : [$b, $a];
        $choices = [];
        foreach (array_keys(ItemMergeService::FIELDS) as $f) {
            $pick = $_POST['field'][$f] ?? $targetSide;
            $choices[$f] = $pick === $targetSide ? 'target' : 'source';
        }
        try {
            (new ItemMergeService())->merge($sourceId, $targetId, $choices, self::userId());
        } catch (\InvalidArgumentException $e) {
            $_SESSION['merge_error'] = $e->getMessage();
            Response::redirect($back);
        }
        $row = (new ItemMergeService())->describe($targetId);
        $_SESSION['flash_ok'] = 'Zusammengeführt in „' . ItemDisplay::name($row ?? []) . '“.';
        Response::redirect('/pflege/dubletten');
    }

    private static function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
