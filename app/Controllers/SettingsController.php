<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\SettingsRepository;

final class SettingsController
{
    public function __construct(
        private SettingsRepository $settings = new SettingsRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        View::layout('layout', 'pages/settings', [
            'title' => 'Einstellungen',
            'order_cc_email' => $this->settings->get('order_cc_email', ''),
            'app_name' => $this->settings->get('app_name', 'CT-Orderlauf'),
            'csrf' => Csrf::token(),
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/settings');
            return;
        }
        $cc = trim((string) ($_POST['order_cc_email'] ?? ''));
        $app = trim((string) ($_POST['app_name'] ?? 'CT-Orderlauf'));
        $err = $cc !== '' ? Validator::email($cc) : null;
        if ($err) {
            View::layout('layout', 'pages/settings', [
                'title' => 'Einstellungen',
                'order_cc_email' => $cc,
                'app_name' => $app,
                'error' => $err,
                'csrf' => Csrf::token(),
            ]);
            return;
        }
        $this->settings->set('order_cc_email', $cc === '' ? null : $cc);
        $this->settings->set('app_name', $app);
        Response::redirect('/settings?saved=1');
    }
}
