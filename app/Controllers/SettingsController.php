<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\SmtpHost;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\SettingsRepository;
use App\Services\MailSenderService;

final class SettingsController
{
    private const DEFAULT_ORDER_SUBJECT = 'Bestellung {{COMPANY}} {{TARGET_DATE}}';

    public function __construct(
        private SettingsRepository $settings = new SettingsRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireEditor();
        $subjectTpl = trim((string) $this->settings->get('order_email_subject_template', ''));
        if ($subjectTpl === '') {
            $subjectTpl = self::DEFAULT_ORDER_SUBJECT;
        }
        $smtpHost = trim((string) $this->settings->get('smtp_host', ''));
        $smtpUser = trim((string) $this->settings->get('smtp_user', ''));
        $smtpFromDisplay = trim((string) $this->settings->get('smtp_from_email', ''));
        if (SmtpHost::requiresSenderEqualsSmtpUser($smtpHost) && $smtpUser !== '') {
            if ($smtpFromDisplay === '' || strcasecmp($smtpFromDisplay, $smtpUser) !== 0) {
                $smtpFromDisplay = $smtpUser;
            }
        }
        View::layout('layout', 'pages/settings', [
            'title' => 'Einstellungen',
            'order_cc_email' => $this->settings->get('order_cc_email', ''),
            'app_name' => $this->settings->get('app_name', 'CT-Orderlauf'),
            'dev_mode' => $this->settings->get('dev_mode', '0'),
            'dev_email' => $this->settings->get('dev_email', ''),
            'company_name' => $this->settings->get('company_name', ''),
            'order_email_subject_template' => $subjectTpl,
            'company_street' => $this->settings->get('company_street', ''),
            'company_city' => $this->settings->get('company_city', ''),
            'company_phone' => $this->settings->get('company_phone', ''),
            'company_fax' => $this->settings->get('company_fax', ''),
            'ui_show_outlook_export' => $this->settings->get('ui_show_outlook_export', '1'),
            'ui_show_pdf' => $this->settings->get('ui_show_pdf', '1'),
            'send_email_direct' => $this->settings->get('send_email_direct', '0'),
            'smtp_host' => $this->settings->get('smtp_host', ''),
            'smtp_port' => $this->settings->get('smtp_port', '587'),
            'smtp_user' => $this->settings->get('smtp_user', ''),
            'smtp_pass' => '',
            'smtp_from_email' => $smtpFromDisplay,
            'smtp_from_name' => $this->settings->get('smtp_from_name', ''),
            'csrf' => Csrf::token(),
            'smtp_test_ok' => $_SESSION['smtp_test_ok'] ?? null,
            'smtp_test_message' => $_SESSION['smtp_test_message'] ?? '',
        ]);
        unset($_SESSION['smtp_test_ok'], $_SESSION['smtp_test_message']);
    }

    /** POST: SMTP nur Verbindung + Anmeldung testen (gespeicherte Werte aus der Datenbank). */
    public function smtpTest(): void
    {
        AuthMiddleware::requireEditor();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/settings');
            return;
        }
        $result = (new MailSenderService($this->settings))->testSmtpConnection();
        if ($result['ok']) {
            $_SESSION['smtp_test_ok'] = true;
            $_SESSION['smtp_test_message'] = (string) ($result['detail'] ?? 'OK');
        } else {
            $_SESSION['smtp_test_ok'] = false;
            $_SESSION['smtp_test_message'] = (string) ($result['error'] ?? 'Fehler');
        }
        Response::redirect('/settings');
    }

    public function save(): void
    {
        AuthMiddleware::requireEditor();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/settings');
            return;
        }
        $cc = trim((string) ($_POST['order_cc_email'] ?? ''));
        $app = trim((string) ($_POST['app_name'] ?? 'CT-Orderlauf'));
        $devMode = isset($_POST['dev_mode']) ? '1' : '0';
        $devEmail = trim((string) ($_POST['dev_email'] ?? ''));
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $companyStreet = trim((string) ($_POST['company_street'] ?? ''));
        $companyCity = trim((string) ($_POST['company_city'] ?? ''));
        $companyPhone = trim((string) ($_POST['company_phone'] ?? ''));
        $companyFax = trim((string) ($_POST['company_fax'] ?? ''));
        $uiOutlook = isset($_POST['ui_show_outlook_export']) ? '1' : '0';
        $uiPdf = isset($_POST['ui_show_pdf']) ? '1' : '0';
        $orderSubjectTpl = trim((string) ($_POST['order_email_subject_template'] ?? ''));
        $sendDirect = isset($_POST['send_email_direct']) ? '1' : '0';
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = trim((string) ($_POST['smtp_port'] ?? '587'));
        $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
        $smtpPass = trim((string) ($_POST['smtp_pass'] ?? ''));
        $smtpFromEmail = trim((string) ($_POST['smtp_from_email'] ?? ''));
        $smtpFromName = trim((string) ($_POST['smtp_from_name'] ?? ''));

        $err = $cc !== '' ? Validator::email($cc) : null;
        if (!$err && $devMode === '1' && $devEmail === '') {
            $err = 'Im Testbetrieb muss eine Dev-E-Mail angegeben werden.';
        }
        if (!$err && $devEmail !== '') {
            $err = Validator::email($devEmail);
        }
        if (!$err && $smtpHost !== '' && $smtpUser !== '') {
            $smtpUserEmailErr = Validator::email($smtpUser);
            if ($smtpUserEmailErr !== null) {
                $err = 'SMTP-Benutzer muss eine vollständige E-Mail-Adresse sein (z. B. name@domain.de) – nicht abgeschnitten oder nur der lokale Teil.';
            }
        }
        if (!$err && $smtpHost !== '' && SmtpHost::requiresSenderEqualsSmtpUser($smtpHost) && $smtpUser !== '') {
            if ($smtpFromEmail === '') {
                $smtpFromEmail = $smtpUser;
            } elseif (strcasecmp($smtpFromEmail, $smtpUser) !== 0) {
                $err = 'Absender-Adresse und SMTP-Benutzer müssen identisch sein (Vorgabe dieses SMTP-Anbieters, z. B. IONOS).';
            }
        }
        if ($err) {
            View::layout('layout', 'pages/settings', [
                'title' => 'Einstellungen',
                'order_cc_email' => $cc,
                'app_name' => $app,
                'dev_mode' => $devMode,
                'dev_email' => $devEmail,
                'company_name' => $companyName,
                'company_street' => $companyStreet,
                'company_city' => $companyCity,
                'company_phone' => $companyPhone,
                'company_fax' => $companyFax,
                'order_email_subject_template' => $orderSubjectTpl === '' ? self::DEFAULT_ORDER_SUBJECT : $orderSubjectTpl,
                'ui_show_outlook_export' => $uiOutlook,
                'ui_show_pdf' => $uiPdf,
                'send_email_direct' => $sendDirect,
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_user' => $smtpUser,
                'smtp_pass' => $smtpPass,
                'smtp_from_email' => $smtpFromEmail,
                'smtp_from_name' => $smtpFromName,
                'error' => $err,
                'csrf' => Csrf::token(),
            ]);
            return;
        }
        $this->settings->set('order_cc_email', $cc === '' ? null : $cc);
        $this->settings->set('app_name', $app);
        $this->settings->set('dev_mode', $devMode);
        $this->settings->set('dev_email', $devEmail === '' ? null : $devEmail);
        $this->settings->set('company_name', $companyName === '' ? null : $companyName);
        $this->settings->set('company_street', $companyStreet === '' ? null : $companyStreet);
        $this->settings->set('company_city', $companyCity === '' ? null : $companyCity);
        $this->settings->set('company_phone', $companyPhone === '' ? null : $companyPhone);
        $this->settings->set('company_fax', $companyFax === '' ? null : $companyFax);
        $this->settings->set(
            'order_email_subject_template',
            $orderSubjectTpl === '' || $orderSubjectTpl === self::DEFAULT_ORDER_SUBJECT ? null : $orderSubjectTpl
        );
        $this->settings->set('ui_show_outlook_export', $uiOutlook);
        $this->settings->set('ui_show_pdf', $uiPdf);
        $this->settings->set('send_email_direct', $sendDirect);
        $this->settings->set('smtp_host', $smtpHost === '' ? null : $smtpHost);
        $this->settings->set('smtp_port', $smtpPort);
        $this->settings->set('smtp_user', $smtpUser === '' ? null : $smtpUser);
        if ($smtpPass !== '') {
            $this->settings->set('smtp_pass', $smtpPass);
        } elseif ($smtpHost === '') {
            $this->settings->set('smtp_pass', null);
        }
        $this->settings->set('smtp_from_email', $smtpFromEmail === '' ? null : $smtpFromEmail);
        $this->settings->set('smtp_from_name', $smtpFromName === '' ? null : $smtpFromName);
        Response::redirect('/settings?saved=1');
    }
}
