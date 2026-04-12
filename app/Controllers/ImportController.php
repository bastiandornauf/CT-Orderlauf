<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Services\CsvImportService;

final class ImportController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/import', [
            'title' => 'CSV-Import',
            'csrf' => Csrf::token(),
            'result' => null,
        ]);
    }

    public function preview(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/import');
            return;
        }
        $type = (string) ($_POST['import_type'] ?? '');
        $content = '';
        if (!empty($_FILES['csv']['tmp_name']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
            $content = (string) file_get_contents($_FILES['csv']['tmp_name']);
        }
        $svc = new CsvImportService();
        $rows = $svc->parse($content);
        $result = $svc->preview($type, $rows);
        $_SESSION['import_preview'] = [
            'type' => $type,
            'rows' => $result['preview'],
            'ok' => $result['ok'],
        ];
        View::layout('layout', 'pages/import', [
            'title' => 'CSV-Import',
            'csrf' => Csrf::token(),
            'result' => $result,
            'import_type' => $type,
        ]);
    }

    public function run(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/import');
            return;
        }
        $data = $_SESSION['import_preview'] ?? null;
        if ($data === null || empty($data['ok'])) {
            Response::redirect('/import');
            return;
        }
        $svc = new CsvImportService();
        $stats = $svc->execute((string) $data['type'], $data['rows']);
        unset($_SESSION['import_preview']);
        View::layout('layout', 'pages/import', [
            'title' => 'CSV-Import',
            'csrf' => Csrf::token(),
            'done' => $stats,
        ]);
    }
}
