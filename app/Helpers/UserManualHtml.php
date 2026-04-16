<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Sehr kleiner Markdown-Renderer nur für docs/ANLEITUNG.md (Überschriften, Listen, Tabellen, Fett, Links, HR).
 */
final class UserManualHtml
{
    public static function renderFile(string $absolutePath): ?string
    {
        if (!is_readable($absolutePath)) {
            return null;
        }
        $raw = file_get_contents($absolutePath);
        if ($raw === false || $raw === '') {
            return null;
        }

        return self::markdownToHtml($raw);
    }

    public static function markdownToHtml(string $md): string
    {
        $md = str_replace(["\r\n", "\r"], "\n", $md);
        $lines = explode("\n", $md);
        $blocks = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            $line = $lines[$i];
            $trim = trim($line);

            if ($trim === '---') {
                $blocks[] = '<hr class="help-doc__hr">';
                $i++;
                continue;
            }

            if (!str_starts_with($trim, '##') && preg_match('/^# (.+)$/', $trim, $mh)) {
                $blocks[] = '<h1 class="help-doc__h1">' . self::inlinePlain($mh[1]) . '</h1>';
                $i++;
                continue;
            }

            if (str_starts_with($trim, '## ')) {
                $blocks[] = '<h2 class="help-doc__h2">' . self::inlinePlain(substr($trim, 3)) . '</h2>';
                $i++;
                continue;
            }

            if (str_starts_with($trim, '### ')) {
                $blocks[] = '<h3 class="help-doc__h3">' . self::inlinePlain(substr($trim, 4)) . '</h3>';
                $i++;
                continue;
            }

            if (str_starts_with($trim, '|') && substr_count($trim, '|') >= 2) {
                $tableLines = [];
                while ($i < $n && str_starts_with(trim($lines[$i]), '|')) {
                    $tableLines[] = $lines[$i];
                    $i++;
                }
                $blocks[] = self::renderTable($tableLines);
                continue;
            }

            if (str_starts_with($trim, '- ')) {
                $items = [];
                while ($i < $n && str_starts_with(trim($lines[$i]), '- ')) {
                    $items[] = self::inline(trim(substr(trim($lines[$i]), 2)));
                    $i++;
                }
                $blocks[] = '<ul class="help-doc__ul">' . implode('', array_map(
                    static fn (string $it): string => '<li>' . $it . '</li>',
                    $items
                )) . '</ul>';
                continue;
            }

            if (preg_match('/^\d+\.\s+/', $trim)) {
                $items = [];
                while ($i < $n && preg_match('/^\d+\.\s+(.+)$/', trim($lines[$i]), $m)) {
                    $items[] = self::inline($m[1]);
                    $i++;
                }
                $blocks[] = '<ol class="help-doc__ol">' . implode('', array_map(
                    static fn (string $it): string => '<li>' . $it . '</li>',
                    $items
                )) . '</ol>';
                continue;
            }

            if ($trim === '') {
                $i++;
                continue;
            }

            $para = [];
            while ($i < $n) {
                $t = trim($lines[$i]);
                if ($t === '' || preg_match('/^# (?!#)/', $t) || str_starts_with($t, '##') || str_starts_with($t, '###')
                    || $t === '---' || str_starts_with($t, '|') || str_starts_with($t, '- ')
                    || preg_match('/^\d+\.\s/', $t)) {
                    break;
                }
                $para[] = $lines[$i];
                $i++;
            }
            $text = trim(implode("\n", $para));
            if ($text !== '') {
                $blocks[] = '<p class="help-doc__p">' . self::inline(self::mdLineBreaks($text)) . '</p>';
            }
        }

        return implode("\n", $blocks);
    }

    private static function mdLineBreaks(string $s): string
    {
        return preg_replace('/ {2,}\n/', "<br>\n", $s) ?? $s;
    }

    private static function inline(string $s): string
    {
        return nl2br(self::inlineCore($s), false);
    }

    private static function inlinePlain(string $s): string
    {
        return self::inlineCore($s);
    }

    private static function inlineCore(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s) ?? $s;
        $s = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            static function (array $m): string {
                $label = $m[1];
                $url = $m[2];
                if (preg_match('#^https?://#i', $url)) {
                    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
                }

                return '<span class="help-doc__ref" title="Liegt im Projektordner (nicht im Web)">' . $label . '</span>';
            },
            $s
        ) ?? $s;

        return $s;
    }

    /**
     * @param list<string> $tableLines
     */
    private static function renderTable(array $tableLines): string
    {
        $rows = [];
        foreach ($tableLines as $tl) {
            $cells = array_map('trim', explode('|', trim($tl, '|')));
            if ($cells !== [] && self::isMarkdownTableSeparatorRow($cells)) {
                continue;
            }
            $rows[] = $cells;
        }
        if ($rows === []) {
            return '';
        }
        $html = '<div class="help-doc__table-wrap"><table class="help-doc__table"><thead><tr>';
        $header = array_shift($rows);
        foreach ($header as $h) {
            $html .= '<th>' . self::inlineNoBr($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . self::inlineNoBr($cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private static function inlineNoBr(string $s): string
    {
        return self::inlineCore($s);
    }

    /** @param list<string> $cells */
    private static function isMarkdownTableSeparatorRow(array $cells): bool
    {
        foreach ($cells as $c) {
            if ($c === '' || !preg_match('/^:?-{3,}:?$/', $c)) {
                return false;
            }
        }

        return true;
    }
}
