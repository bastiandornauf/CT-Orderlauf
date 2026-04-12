<?php

declare(strict_types=1);

namespace App\Services;

if (!class_exists('FPDF', false)) {
    require_once APP_ROOT . '/lib/fpdf.php';
}

final class PdfService
{
    /**
     * @param list<array{label: string, quantity: string, unit: string}> $lines
     */
    public function renderOrderPdf(
        string $title,
        string $supplierName,
        string $targetDate,
        array $lines,
        string $extraNote = ''
    ): string {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->Cell(0, 10, $this->encode($title), 0, 1);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Cell(0, 8, $this->encode('Lieferant: ' . $supplierName), 0, 1);
        $pdf->Cell(0, 8, $this->encode('Lieferdatum / Ziel: ' . $targetDate), 0, 1);
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(90, 8, 'Artikel', 1);
        $pdf->Cell(30, 8, 'Menge', 1);
        $pdf->Cell(30, 8, 'Einheit', 1);
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 10);
        foreach ($lines as $l) {
            $pdf->Cell(90, 8, $this->encode($l['label']), 1);
            $pdf->Cell(30, 8, $this->encode((string) $l['quantity']), 1);
            $pdf->Cell(30, 8, $this->encode($l['unit']), 1);
            $pdf->Ln();
        }
        if ($extraNote !== '') {
            $pdf->Ln(4);
            $pdf->MultiCell(0, 6, $this->encode($extraNote));
        }
        return $pdf->Output('S');
    }

    private function encode(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }
}
