<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Generates order PDFs matching the layout of the original Excel-based order sheets:
 *  - Two-column header block: Lieferant (left) | Besteller (right)
 *  - Date line: Bestellung vom … | Liefertag …
 *  - Two-column article table to fit more rows per page
 *  - Optional "Zusätzlich" section for free-text lines
 *
 * Pure PHP, no external libraries or font files required.
 */
final class PdfService
{
    /**
     * @param list<array{label: string, quantity: string, unit: string}> $lines
     * @param list<string> $freeLines
     * @param array{name:string,street:string,city:string,phone:string,fax:string} $company
     * @param array{phone:string,fax:string,mobile:string} $supplierContact
     */
    public function renderOrderPdf(
        string $title,
        string $supplierName,
        string $targetDate,
        array $lines,
        string $extraNote = '',
        array $freeLines = [],
        array $company = [],
        array $supplierContact = []
    ): string {
        $gen = new MinimalPdf();
        $gen->addPage();

        $margin  = 40;
        $pageW   = 595;
        $usable  = $pageW - 2 * $margin;   // 515
        $halfW   = ($usable - 10) / 2;     // ~252.5

        // ── Header: Lieferant | Besteller ──────────────────────────────────
        $y = 40;
        $colR = $margin + $halfW + 10;

        $gen->setFont('Helvetica-Bold', 10);
        $gen->text($margin, $y, 'Lieferant :');
        $gen->text($colR,   $y, 'Besteller :');
        $y += 14;

        $gen->setFont('Helvetica-Bold', 11);
        $gen->text($margin, $y, $supplierName);
        $gen->text($colR,   $y, $company['name'] ?? '');
        $y += 13;

        $gen->setFont('Helvetica', 9);
        $contactLines = [];
        if (!empty($supplierContact['fax']))    $contactLines[] = ['Fax :',   $supplierContact['fax']];
        if (!empty($supplierContact['phone']))   $contactLines[] = ['Tel :',   $supplierContact['phone']];
        if (!empty($supplierContact['mobile']))  $contactLines[] = ['Mobil :', $supplierContact['mobile']];

        $companyLines = [];
        if (!empty($company['street']))  $companyLines[] = $company['street'];
        if (!empty($company['city']))    $companyLines[] = $company['city'];
        if (!empty($company['phone']))   $companyLines[] = 'Tel : ' . $company['phone'];
        if (!empty($company['fax']))     $companyLines[] = 'Fax : ' . $company['fax'];

        $headerRows = max(count($contactLines), count($companyLines));
        for ($i = 0; $i < $headerRows; $i++) {
            if (isset($contactLines[$i])) {
                $gen->text($margin,       $y, $contactLines[$i][0]);
                $gen->text($margin + 40,  $y, $contactLines[$i][1]);
            }
            if (isset($companyLines[$i])) {
                $gen->text($colR, $y, $companyLines[$i]);
            }
            $y += 12;
        }

        // ── Date line ──────────────────────────────────────────────────────
        $y += 4;
        $gen->line($margin, $y, $pageW - $margin, $y);
        $y += 8;

        $orderDate = date('d.m.Y');
        $dtTarget = \DateTimeImmutable::createFromFormat('Y-m-d', $targetDate);
        $deliveryDateFmt = $dtTarget !== false ? $dtTarget->format('d.m.Y') : $targetDate;
        $gen->setFont('Helvetica-Bold', 10);
        $gen->text($margin, $y, 'Bestellung vom ' . $orderDate);
        $gen->text($colR,   $y, 'Liefertag ' . $deliveryDateFmt);
        $y += 8;
        $gen->line($margin, $y, $pageW - $margin, $y);
        $y += 10;

        // ── Two-column article table ───────────────────────────────────────
        // Each half: Artikel(140) | Einheit(60) | Menge(52)  × 2, gap = 11
        $cArt  = 140;
        $cEin  = 60;
        $cMng  = 52;
        $halfTW = $cArt + $cEin + $cMng;   // 252
        $gap    = $usable - 2 * $halfTW;    // 515 - 504 = 11

        $rowH = 14;

        // Table header
        $gen->setFont('Helvetica-Bold', 9);
        $this->tableHeaderRow($gen, $margin, $y, $cArt, $cEin, $cMng, $halfTW + $gap, $rowH);
        $y += $rowH;

        // Split items into two halves for the two-column layout
        $total = count($lines);
        $half  = (int) ceil($total / 2);

        $gen->setFont('Helvetica', 9);
        $maxY  = 800;

        for ($i = 0; $i < $half; $i++) {
            if ($y + $rowH > $maxY) {
                $gen->addPage();
                $y = 40;
                $gen->setFont('Helvetica-Bold', 9);
                $this->tableHeaderRow($gen, $margin, $y, $cArt, $cEin, $cMng, $halfTW + $gap, $rowH);
                $y += $rowH;
                $gen->setFont('Helvetica', 9);
            }
            $lLeft  = $lines[$i];
            $lRight = $lines[$i + $half] ?? null;

            // Left cell
            $xL = $margin;
            $gen->rect($xL,              $y, $cArt, $rowH);
            $gen->rect($xL + $cArt,      $y, $cEin, $rowH);
            $gen->rect($xL + $cArt + $cEin, $y, $cMng, $rowH);
            $gen->text($xL + 2,              $y + 10, $this->trunc($lLeft['label'], 28));
            $gen->text($xL + $cArt + 2,      $y + 10, $this->trunc($lLeft['unit'], 10));
            $gen->text($xL + $cArt + $cEin + 2, $y + 10, (string) $lLeft['quantity']);

            // Right cell
            if ($lRight !== null) {
                $xR = $margin + $halfTW + $gap;
                $gen->rect($xR,              $y, $cArt, $rowH);
                $gen->rect($xR + $cArt,      $y, $cEin, $rowH);
                $gen->rect($xR + $cArt + $cEin, $y, $cMng, $rowH);
                $gen->text($xR + 2,              $y + 10, $this->trunc($lRight['label'], 28));
                $gen->text($xR + $cArt + 2,      $y + 10, $this->trunc($lRight['unit'], 10));
                $gen->text($xR + $cArt + $cEin + 2, $y + 10, (string) $lRight['quantity']);
            }
            $y += $rowH;
        }

        // ── Free / addon lines ─────────────────────────────────────────────
        if ($freeLines !== []) {
            $y += 6;
            $gen->setFont('Helvetica-Bold', 9);
            if ($y + 14 > $maxY) { $gen->addPage(); $y = 40; }
            $gen->text($margin, $y + 9, 'Zusätzlich:');
            $y += 13;
            $gen->setFont('Helvetica', 9);
            foreach ($freeLines as $fl) {
                if ($y + 13 > $maxY) { $gen->addPage(); $y = 40; }
                $gen->text($margin + 4, $y + 9, '- ' . $fl);
                $y += 13;
            }
        }

        // ── Extra note ─────────────────────────────────────────────────────
        if ($extraNote !== '') {
            $y += 6;
            $gen->setFont('Helvetica', 8);
            foreach ($gen->wrapText($extraNote, 100) as $noteLine) {
                if ($y + 12 > $maxY) { $gen->addPage(); $y = 40; }
                $gen->text($margin, $y + 9, $noteLine);
                $y += 12;
            }
        }

        // ── Footer ─────────────────────────────────────────────────────────
        $gen->setFont('Helvetica', 8);
        $gen->text($margin, 825, $title . '  –  Bestelliste');

        return $gen->output();
    }

    private function tableHeaderRow(
        MinimalPdf $gen, float $x, float $y,
        float $cArt, float $cEin, float $cMng,
        float $rightOffset, float $rowH
    ): void {
        foreach ([0, $rightOffset] as $ox) {
            $gen->rect($x + $ox,              $y, $cArt, $rowH);
            $gen->rect($x + $ox + $cArt,      $y, $cEin, $rowH);
            $gen->rect($x + $ox + $cArt + $cEin, $y, $cMng, $rowH);
            $gen->text($x + $ox + 2,              $y + 10, 'Artikel');
            $gen->text($x + $ox + $cArt + 2,      $y + 10, 'Einheit');
            $gen->text($x + $ox + $cArt + $cEin + 2, $y + 10, 'Menge');
        }
    }

    private function trunc(string $s, int $max): string
    {
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
    }
}

/**
 * Low-level PDF stream builder (PDF 1.4, standard Type1 fonts, no embedding).
 */
final class MinimalPdf
{
    private array  $pages    = [];
    private array  $stream   = [];
    private string $curFont  = 'Helvetica';
    private int    $curSize  = 10;

    private const W = 595;
    private const H = 842;

    public function addPage(): void
    {
        if ($this->stream !== []) {
            $this->pages[] = $this->stream;
        }
        $this->stream = [];
    }

    public function setFont(string $name, int $size): void
    {
        $this->curFont = $name;
        $this->curSize = $size;
    }

    public function text(float $x, float $y, string $text): void
    {
        if ($text === '') return;
        $pdfY = self::H - $y;
        $alias = $this->fontAlias();
        $esc   = $this->esc($text);
        $this->stream[] = "BT /{$alias} {$this->curSize} Tf {$x} {$pdfY} Td ({$esc}) Tj ET";
    }

    public function rect(float $x, float $y, float $w, float $h): void
    {
        $pdfY = self::H - $y - $h;
        $this->stream[] = sprintf('%.2f %.2f %.2f %.2f re S', $x, $pdfY, $w, $h);
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $py1 = self::H - $y1;
        $py2 = self::H - $y2;
        $this->stream[] = sprintf('%.2f %.2f m %.2f %.2f l S', $x1, $py1, $x2, $py2);
    }

    /** @return list<string> */
    public function wrapText(string $text, int $maxChars): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $cur   = '';
        foreach ($words as $w) {
            if ($cur === '') {
                $cur = $w;
            } elseif (strlen($cur) + 1 + strlen($w) <= $maxChars) {
                $cur .= ' ' . $w;
            } else {
                $lines[] = $cur;
                $cur     = $w;
            }
        }
        if ($cur !== '') $lines[] = $cur;
        return $lines;
    }

    public function output(): string
    {
        if ($this->stream !== []) {
            $this->pages[] = $this->stream;
            $this->stream  = [];
        }

        $pdf  = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $xref = [];

        // Obj 1 = Catalog, Obj 2 = Pages, Obj 3 = F1 (Helvetica), Obj 4 = F2 (Helvetica-Bold)
        $nextObj = 5;
        $pageObjs = [];
        foreach ($this->pages as $s) {
            $pageObjs[] = ['content' => $nextObj++, 'page' => $nextObj++, 'stream' => $s];
        }

        $xref[1] = strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $kids = implode(' 0 R ', array_column($pageObjs, 'page')) . ' 0 R';
        $xref[2] = strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count " . count($pageObjs) . " >>\nendobj\n";

        $xref[3] = strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $xref[4] = strlen($pdf);
        $pdf .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        foreach ($pageObjs as $p) {
            $s   = implode("\n", $p['stream']);
            $len = strlen($s);

            $xref[$p['content']] = strlen($pdf);
            $pdf .= "{$p['content']} 0 obj\n<< /Length {$len} >>\nstream\n{$s}\nendstream\nendobj\n";

            $xref[$p['page']] = strlen($pdf);
            $pdf .= "{$p['page']} 0 obj\n"
                 .  "<< /Type /Page /Parent 2 0 R"
                 .  " /MediaBox [0 0 " . self::W . " " . self::H . "]"
                 .  " /Contents {$p['content']} 0 R"
                 .  " /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $total   = max(array_keys($xref)) + 1;
        $pdf .= "xref\n0 {$total}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $pdf .= isset($xref[$i])
                ? sprintf("%010d 00000 n \n", $xref[$i])
                : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size {$total} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF\n";

        return $pdf;
    }

    private function fontAlias(): string
    {
        return $this->curFont === 'Helvetica-Bold' ? 'F2' : 'F1';
    }

    private function esc(string $s): string
    {
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
}
