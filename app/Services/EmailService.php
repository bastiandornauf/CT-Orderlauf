<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds mail subject/body from supplier template placeholders.
 */
final class EmailService
{
    private const DEFAULT_ORDER_SUBJECT = 'Bestellung {{COMPANY}} {{TARGET_DATE}}';

    /**
     * @param array{label: string, quantity: string, unit: string} $l
     */
    private static function formatCatalogLine(array $l): string
    {
        $q = trim((string) $l['quantity']);
        $label = trim((string) $l['label']);
        $unit = trim((string) $l['unit']);
        if ($label === '') {
            return '';
        }
        if ($unit !== '') {
            return sprintf("- %sx %s (%s)\n", $q, $label, $unit);
        }

        return sprintf("- %sx %s\n", $q, $label);
    }

    /**
     * @param list<array{label: string, quantity: string, unit: string}> $lines
     * @param list<string> $freeLines
     */
    public function build(
        ?string $template,
        string $supplierName,
        string $targetDateFormatted,
        array $lines,
        array $freeLines,
        string $supplierNote = '',
        ?string $subjectTemplate = null,
        string $companyName = '',
        string $appName = '',
        string $userName = ''
    ): array {
        $defaultBody = "Bestellung für {{TARGET_DATE}}\n\n{{LINES}}\n\n{{IF ADDONS}}Zusätzlich:\n{{ADDONS}}\n{{ENDIF}}\n{{SUPPLIER_NOTE}}";
        $tpl = $template !== null && trim($template) !== '' ? $template : $defaultBody;

        $linesBlock = '';
        foreach ($lines as $l) {
            $linesBlock .= self::formatCatalogLine($l);
        }
        if ($linesBlock === '') {
            $linesBlock = "(keine Artikel)\n";
        }

        $freeBlock = '';
        foreach ($freeLines as $f) {
            $freeBlock .= "- {$f}\n";
        }

        $noteBlock = $supplierNote !== '' ? "Hinweis:\n{$supplierNote}\n" : '';

        $user = trim($userName);
        $body = str_replace(
            [
                '{{TARGET_DATE}}',
                '{{SUPPLIER}}',
                '{{USER}}',
                '{{LINES}}',
                '{{ADDONS}}',
                '{{SUPPLIER_NOTE}}',
                '{{DATE_TODAY}}',
                '[DATE_TODAY]',
            ],
            [
                $targetDateFormatted,
                $supplierName,
                $user,
                $linesBlock,
                $freeBlock,
                $noteBlock,
                $targetDateFormatted,
                $targetDateFormatted,
            ],
            $tpl
        );

        // Conditional blocks {{IF ADDONS}} ... {{ENDIF}}
        if (str_contains($body, '{{IF ADDONS}}')) {
            $hasAddons = $freeLines !== [];
            $body = preg_replace(
                '/\{\{IF ADDONS\}\}.*?\{\{ENDIF\}\}/s',
                $hasAddons ? '$0' : '',
                $body
            ) ?? $body;
            $body = str_replace(['{{IF ADDONS}}', '{{ENDIF}}'], '', $body);
        }

        $subjectTpl = $subjectTemplate !== null && trim($subjectTemplate) !== ''
            ? trim($subjectTemplate)
            : self::DEFAULT_ORDER_SUBJECT;
        $company = trim($companyName) !== '' ? trim($companyName) : trim($appName);
        $subject = str_replace(
            [
                '{{COMPANY}}',
                '{{APP_NAME}}',
                '{{SUPPLIER}}',
                '{{USER}}',
                '{{TARGET_DATE}}',
                '{{DATE_TODAY}}',
                '[DATE_TODAY]',
            ],
            [
                $company,
                trim($appName),
                $supplierName,
                $user,
                $targetDateFormatted,
                $targetDateFormatted,
                $targetDateFormatted,
            ],
            $subjectTpl
        );
        $subject = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);

        return ['subject' => $subject, 'body' => trim($body)];
    }
}
