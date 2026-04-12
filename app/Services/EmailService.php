<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Builds mail subject/body from supplier template placeholders.
 */
final class EmailService
{
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
        string $supplierNote = ''
    ): array {
        $defaultBody = "Bestellung für {{TARGET_DATE}}\n\n{{LINES}}\n\n{{FREE_ITEMS}}\n\n{{SUPPLIER_NOTE}}";
        $tpl = $template !== null && trim($template) !== '' ? $template : $defaultBody;

        $linesBlock = '';
        foreach ($lines as $l) {
            $linesBlock .= sprintf(
                "- %s: %s %s\n",
                $l['label'],
                $l['quantity'],
                $l['unit']
            );
        }
        if ($linesBlock === '') {
            $linesBlock = "(keine Artikel)\n";
        }

        $freeBlock = '';
        if ($freeLines !== []) {
            $freeBlock = "Zusätzlich:\n";
            foreach ($freeLines as $f) {
                $freeBlock .= "- {$f}\n";
            }
        }

        $noteBlock = $supplierNote !== '' ? "Hinweis:\n{$supplierNote}\n" : '';

        $body = str_replace(
            [
                '{{TARGET_DATE}}',
                '{{SUPPLIER}}',
                '{{LINES}}',
                '{{FREE_ITEMS}}',
                '{{SUPPLIER_NOTE}}',
                '[DATE_TODAY]',
            ],
            [
                $targetDateFormatted,
                $supplierName,
                $linesBlock,
                $freeBlock,
                $noteBlock,
                $targetDateFormatted,
            ],
            $tpl
        );

        // Simple conditional blocks [IF ADDONS] ... [ENDIF]
        if (str_contains($body, '[IF ADDONS]')) {
            $hasAddons = $freeLines !== [];
            $body = preg_replace(
                '/\[IF ADDONS\].*?\[ENDIF\]/s',
                $hasAddons ? '$0' : '',
                $body
            ) ?? $body;
            $body = str_replace(['[IF ADDONS]', '[ENDIF]'], '', $body);
        }

        $subject = 'Bestellung ' . $supplierName . ' ' . $targetDateFormatted;

        return ['subject' => $subject, 'body' => trim($body)];
    }
}
