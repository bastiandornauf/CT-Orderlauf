<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
use App\Helpers\Response;
use App\Middleware\AuthMiddleware;
use App\Repositories\ItemRepository;
use App\Repositories\LocationRepository;
use App\Repositories\SettingsRepository;
use App\Repositories\SupplierRepository;
use App\Services\MailSenderService;
use App\Services\PdfService;
use DateTimeImmutable;
use PDO;

final class OrderApiController
{
    public function payload(): void
    {
        AuthMiddleware::requireAuth();
        $date = (string) ($_GET['target_date'] ?? '');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($dt === false) {
            Response::jsonError('target_date muss YYYY-MM-DD sein.', 422);
        }
        $weekday = (int) $dt->format('N');

        $locRepo = new LocationRepository();
        $supRepo = new SupplierRepository();
        $itemRepo = new ItemRepository();
        $settings = new SettingsRepository();
        $defaultSubjectTpl = 'Bestellung {{COMPANY}} {{TARGET_DATE}}';
        $subjectTpl = trim((string) $settings->get('order_email_subject_template', ''));
        if ($subjectTpl === '') {
            $subjectTpl = $defaultSubjectTpl;
        }

        $locations = $locRepo->all(true);
        $suppliers = $supRepo->all(true);
        $deliveryRows = Database::pdo()->query(
            'SELECT supplier_id, weekday FROM supplier_delivery_days ORDER BY supplier_id, weekday'
        )->fetchAll(PDO::FETCH_ASSOC);

        $items = Database::pdo()->query(
            'SELECT id, name, unit, location_id, min_stock, max_stock, active FROM items WHERE active = 1 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);


        $links = Database::pdo()->query(
            'SELECT item_id, supplier_id, priority FROM item_supplier ORDER BY item_id, priority DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        // For each supplier, find the next delivery date >= target_date (within 7 days)
        $deliveryTargets = [];
        $deliveringIds = [];
        foreach ($suppliers as $s) {
            $days = $supRepo->deliveryWeekdays((int) $s['id']);
            if (empty($days)) {
                continue;
            }
            for ($offset = 0; $offset <= 6; $offset++) {
                $candidate = $dt->modify("+{$offset} days");
                if (in_array((int) $candidate->format('N'), $days, true)) {
                    $deliveryTargets[] = [
                        'supplier_id'   => (int) $s['id'],
                        'delivery_date' => $candidate->format('Y-m-d'),
                    ];
                    $deliveringIds[] = (int) $s['id'];
                    break;
                }
            }
        }

        Response::jsonOk([
            'target_date' => $date,
            'target_weekday' => $weekday,
            'suppliers_delivering_ids' => $deliveringIds,
            'supplier_delivery_targets' => $deliveryTargets,
            'locations' => $locations,
            'suppliers' => $suppliers,
            'supplier_delivery_days' => $deliveryRows,
            'items' => $items,
            'item_supplier_links' => $links,
            'settings' => [
                'order_cc_email' => $settings->get('order_cc_email', ''),
                'app_name' => $settings->get('app_name', 'CT-Orderlauf'),
                'company_name' => (string) $settings->get('company_name', ''),
                'order_email_subject_template' => $subjectTpl,
                'dev_mode' => $settings->get('dev_mode', '0') === '1',
                'dev_email' => $settings->get('dev_email', ''),
                'ui_show_outlook_export' => $settings->get('ui_show_outlook_export', '1') === '1',
                'ui_show_pdf'           => $settings->get('ui_show_pdf', '1') === '1',
                'send_email_direct'     => $settings->get('send_email_direct', '0') === '1',
            ],
        ]);
    }

    public function pdf(): void
    {
        AuthMiddleware::requireAuth();
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }
        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        $targetDate = trim((string) ($data['target_date'] ?? ''));
        $lines = $data['lines'] ?? [];
        if ($supplierName === '' || !is_array($lines)) {
            Response::jsonError('supplier_name und lines erforderlich.', 422);
        }
        /** @var list<array{label: string, quantity: string, unit: string}> $norm */
        $norm = [];
        foreach ($lines as $l) {
            if (!is_array($l)) {
                continue;
            }
            $norm[] = [
                'label' => (string) ($l['label'] ?? ''),
                'quantity' => (string) ($l['quantity'] ?? ''),
                'unit' => (string) ($l['unit'] ?? ''),
            ];
        }
        $note = (string) ($data['note'] ?? '');
        $rawFree = $data['free_lines'] ?? [];
        $freeLines = is_array($rawFree) ? array_values(array_filter(array_map('strval', $rawFree))) : [];

        $settings = new SettingsRepository();
        $app = $settings->get('app_name', 'CT-Orderlauf');

        $company = [
            'name'   => $settings->get('company_name', ''),
            'street' => $settings->get('company_street', ''),
            'city'   => $settings->get('company_city', ''),
            'phone'  => $settings->get('company_phone', ''),
            'fax'    => $settings->get('company_fax', ''),
        ];

        $supRepo  = new \App\Repositories\SupplierRepository();
        $supRow   = $supRepo->findByName($supplierName);
        if ($supRow === null) {
            Response::jsonError('Lieferant „' . $supplierName . '" nicht gefunden.', 404);
        }
        $supplierContact = [
            'phone'  => (string) ($supRow['phone']  ?? ''),
            'fax'    => (string) ($supRow['fax']    ?? ''),
            'mobile' => (string) ($supRow['mobile'] ?? ''),
        ];

        $pdf = new PdfService();
        $bin = $pdf->renderOrderPdf(
            $app . ' – Bestellung',
            $supplierName,
            $targetDate,
            $norm,
            $note,
            $freeLines,
            $company,
            $supplierContact
        );
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="bestellung-' . preg_replace('/[^a-z0-9_-]+/i', '_', $supplierName) . '.pdf"');
        echo $bin;
        exit;
    }

    public function sendMail(): void
    {
        AuthMiddleware::requireAuth();
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }

        $mailer = new MailSenderService();
        if (!$mailer->isDirectSendEnabled()) {
            Response::jsonError('Direktversand ist nicht aktiviert.', 403);
        }

        $to = trim((string) ($data['to'] ?? ''));
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $cc = trim((string) ($data['cc'] ?? ''));

        if ($to === '' || $subject === '') {
            Response::jsonError('Empfänger und Betreff erforderlich.', 422);
        }

        // Optionally generate and attach PDF
        $attachment = null;
        if (!empty($data['attach_pdf'])) {
            $supplierName = trim((string) ($data['supplier_name'] ?? ''));
            $targetDate   = trim((string) ($data['target_date'] ?? ''));
            $lines        = $data['lines'] ?? [];
            $note         = (string) ($data['note'] ?? '');
            $rawFree      = $data['free_lines'] ?? [];
            $freeLines    = is_array($rawFree) ? array_values(array_filter(array_map('strval', $rawFree))) : [];

            if ($supplierName !== '' && is_array($lines)) {
                $norm = [];
                foreach ($lines as $l) {
                    if (!is_array($l)) continue;
                    $norm[] = [
                        'label'    => (string) ($l['label'] ?? ''),
                        'quantity' => (string) ($l['quantity'] ?? ''),
                        'unit'     => (string) ($l['unit'] ?? ''),
                    ];
                }

                $settings = new SettingsRepository();
                $app      = (string) $settings->get('app_name', 'CT-Orderlauf');
                $company  = [
                    'name'   => (string) $settings->get('company_name', ''),
                    'street' => (string) $settings->get('company_street', ''),
                    'city'   => (string) $settings->get('company_city', ''),
                    'phone'  => (string) $settings->get('company_phone', ''),
                    'fax'    => (string) $settings->get('company_fax', ''),
                ];

                $supRepo = new SupplierRepository();
                $supRow  = $supRepo->findByName($supplierName);
                $supplierContact = [
                    'phone'  => (string) ($supRow['phone']  ?? ''),
                    'fax'    => (string) ($supRow['fax']    ?? ''),
                    'mobile' => (string) ($supRow['mobile'] ?? ''),
                ];

                $pdf      = new PdfService();
                $pdfBin   = $pdf->renderOrderPdf(
                    $app . ' – Bestellung',
                    $supplierName,
                    $targetDate,
                    $norm,
                    $note,
                    $freeLines,
                    $company,
                    $supplierContact
                );
                $safeName = preg_replace('/[^a-z0-9_-]+/i', '_', $supplierName);
                $attachment = ['data' => $pdfBin, 'filename' => "bestellung-{$safeName}.pdf"];
            }
        }

        $result = $mailer->send($to, $subject, $body, $cc, $attachment);
        if ($result['ok']) {
            Response::jsonOk(['message' => 'Mail gesendet.']);
        } else {
            Response::jsonError($result['error'] ?? 'Versand fehlgeschlagen.', 500);
        }
    }
}
