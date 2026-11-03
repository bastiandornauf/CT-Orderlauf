<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\InboxRepository;
use App\Repositories\SettingsRepository;
use App\Services\Inbox\InboxClassifier;
use App\Services\Inbox\InboxService;
use App\Services\Inbox\ZipWriter;

/** Posteingang des Versand-Postfachs – nur Admins (Rechnungen enthalten Konditionen). */
final class InboxController
{
    private const FILTERS = ['neu', 'belege', 'alle'];

    public function index(): void
    {
        AuthMiddleware::requireAdmin();
        $filter = in_array($_GET['f'] ?? '', self::FILTERS, true) ? (string) $_GET['f'] : 'neu';
        $service = new InboxService();
        $repo = new InboxRepository();
        View::layout('layout', 'pages/inbox', [
            'title' => 'Posteingang',
            'csrf' => Csrf::token(),
            'filter' => $filter,
            'messages' => $repo->list($filter),
            'counts' => $repo->counts(),
            'configured' => $service->isConfigured(),
            'imap_host' => $service->imapHost(),
            'imap_host_custom' => trim((string) (new SettingsRepository())->get('imap_host', '')),
            'mailbox' => trim((string) (new SettingsRepository())->get('smtp_user', '')),
            'own_addresses' => implode("\n", $service->ownAddresses()),
            'last_fetch' => $service->lastFetchAt(),
            'last_error' => $service->lastError(),
        ]);
    }

    /** POST /api/posteingang/fetch {force?: bool} – gedrosselt, liefert die aktuellen Zahlen */
    public function fetch(): void
    {
        AuthMiddleware::requireAdmin();
        $data = self::body();
        $result = (new InboxService())->fetch(!empty($data['force']));
        Response::json($result + ['counts' => (new InboxRepository())->counts()]);
    }

    /** POST /api/posteingang/update {id, status?: neu|erledigt, kind?: …} */
    public function update(): void
    {
        AuthMiddleware::requireAdmin();
        $data = self::body();
        $id = (int) ($data['id'] ?? 0);
        $repo = new InboxRepository();
        if ($id <= 0 || $repo->find($id) === null) {
            Response::jsonError('Nachricht nicht gefunden.', 404);
        }
        if (isset($data['kind'])) {
            if (!in_array($data['kind'], InboxClassifier::KINDS, true)) {
                Response::jsonError('Unbekannte Art.', 422);
            }
            $repo->setKind($id, (string) $data['kind']);
        }
        if (isset($data['status'])) {
            if (!in_array($data['status'], ['neu', 'erledigt'], true)) {
                Response::jsonError('Unbekannter Status.', 422);
            }
            $repo->setStatus($id, (string) $data['status']);
        }
        Response::jsonOk(['message' => $repo->find($id), 'counts' => $repo->counts()]);
    }

    /** POST /posteingang/einstellungen – eigene Absenderadressen */
    public function saveSettings(): void
    {
        AuthMiddleware::requireAdmin();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/posteingang');
        }
        $addresses = InboxClassifier::splitAddresses((string) ($_POST['inbox_own_addresses'] ?? ''));
        $settings = new SettingsRepository();
        $settings->set('inbox_own_addresses', $addresses === [] ? null : implode(', ', $addresses));
        $host = trim((string) ($_POST['imap_host'] ?? ''));
        $settings->set('imap_host', $host === '' ? null : $host);
        Response::redirect('/posteingang?saved=1');
    }

    /** GET /posteingang/anhang?id=…&file=… */
    public function attachment(): void
    {
        AuthMiddleware::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $file = (string) ($_GET['file'] ?? '');
        $msg = (new InboxRepository())->find($id);
        $meta = null;
        foreach ($msg['attachments'] ?? [] as $a) {
            if (($a['file'] ?? '') === $file) {
                $meta = $a;
            }
        }
        $path = $meta !== null ? InboxService::attachmentPath($id, $file) : null;
        if ($path === null) {
            http_response_code(404);
            echo 'Anhang nicht gefunden.';
            exit;
        }
        $mime = (string) ($meta['mime'] ?? 'application/octet-stream');
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'], true);
        header('Content-Type: ' . ($inline ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . "; filename*=UTF-8''" . rawurlencode((string) $meta['name']));
        readfile($path);
        exit;
    }

    /** GET /posteingang/belege.zip – offene Belege + belege.json für die Auswertung */
    public function receiptsZip(): void
    {
        AuthMiddleware::requireAdmin();
        $receipts = (new InboxRepository())->openReceipts();
        $zip = new ZipWriter();
        $index = [];
        foreach ($receipts as $r) {
            $folder = sprintf('%d_%s_%s', $r['id'], substr((string) $r['received_at'], 0, 10), InboxService::safeName(mb_substr((string) $r['subject'], 0, 40)));
            $files = [];
            foreach ($r['attachments'] as $a) {
                $path = InboxService::attachmentPath($r['id'], (string) $a['file']);
                if ($path !== null) {
                    $zip->add($folder . '/' . $a['file'], (string) file_get_contents($path));
                    $files[] = $folder . '/' . $a['file'];
                }
            }
            $index[] = [
                'inbox_message_id' => $r['id'],
                'received_at' => $r['received_at'],
                'from' => $r['from_email'],
                'subject' => $r['subject'],
                'text' => mb_substr((string) $r['body_text'], 0, 4000),
                'files' => $files,
            ];
        }
        $zip->add('belege.json', json_encode([
            'format' => 'orderlauf-belege',
            'version' => 1,
            'exported_at' => (new \DateTimeImmutable())->format('c'),
            'hint' => 'Im Pflegepaket "inbox_message_ids" mit den IDs der ausgewerteten Belege angeben – beim Einspielen werden sie abgehakt.',
            'receipts' => $index,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $bytes = $zip->bytes();
        header('Content-Type: application/zip');
        header('Content-Length: ' . strlen($bytes));
        header('Content-Disposition: attachment; filename="orderlauf-belege-' . date('Y-m-d') . '.zip"');
        echo $bytes;
        exit;
    }

    /** @return array<string, mixed> */
    private static function body(): array
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($data)) {
            Response::jsonError('Ungültiger JSON-Body.', 400);
        }
        if (!Csrf::validate($data['_csrf'] ?? null)) {
            Response::jsonError('CSRF ungültig.', 403);
        }

        return $data;
    }
}
