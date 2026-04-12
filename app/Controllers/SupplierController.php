<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Response;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Repositories\SupplierRepository;

final class SupplierController
{
    public function __construct(
        private SupplierRepository $repo = new SupplierRepository()
    ) {
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $list = $this->repo->all();
        foreach ($list as &$s) {
            $s['weekdays'] = $this->repo->deliveryWeekdays((int) $s['id']);
        }
        unset($s);
        View::layout('layout', 'pages/suppliers/index', [
            'title' => 'Lieferanten',
            'suppliers' => $list,
            'csrf' => Csrf::token(),
        ]);
    }

    public function form(): void
    {
        AuthMiddleware::requireAuth();
        $id = isset($_GET['id']) ? (int) $_GET['id'] : null;
        $row = $id ? $this->repo->find($id) : null;
        if ($id && $row === null) {
            Response::redirect('/suppliers');
            return;
        }
        $weekdays = $id ? $this->repo->deliveryWeekdays($id) : [];
        View::layout('layout', 'pages/suppliers/form', [
            'title' => $id ? 'Lieferant bearbeiten' : 'Lieferant anlegen',
            'supplier' => $row,
            'weekdays' => $weekdays,
            'csrf' => Csrf::token(),
        ]);
    }

    public function save(): void
    {
        AuthMiddleware::requireAuth();
        if (!Csrf::validate($_POST['_csrf'] ?? null)) {
            Response::redirect('/suppliers');
            return;
        }
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? '')) ?: null;
        $phone = trim((string) ($_POST['phone'] ?? '')) ?: null;
        $fax = trim((string) ($_POST['fax'] ?? '')) ?: null;
        $mobile = trim((string) ($_POST['mobile'] ?? '')) ?: null;
        $street = trim((string) ($_POST['street'] ?? '')) ?: null;
        $city = trim((string) ($_POST['city'] ?? '')) ?: null;
        $type = in_array($_POST['order_type'] ?? '', ['mail', 'webshop'], true)
            ? $_POST['order_type'] : 'mail';
        $template = trim((string) ($_POST['email_template'] ?? '')) ?: null;
        $subjectTplRaw = trim((string) ($_POST['email_subject_template'] ?? ''));
        $subjectTpl = $subjectTplRaw !== '' ? mb_substr($subjectTplRaw, 0, 512) : null;
        $active = isset($_POST['active']);
        $attachPdf = isset($_POST['attach_pdf']);
        $days = isset($_POST['weekdays']) && is_array($_POST['weekdays'])
            ? array_map('intval', $_POST['weekdays']) : [];

        $err = Validator::required(['name' => $name], 'name');
        if ($type === 'mail') {
            $err = $err ?? Validator::email($email);
        }
        if ($err) {
            View::layout('layout', 'pages/suppliers/form', [
                'title' => $id ? 'Lieferant bearbeiten' : 'Lieferant anlegen',
                'supplier' => [
                    'id' => $id ?: null,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'fax' => $fax,
                    'mobile' => $mobile,
                    'street' => $street,
                    'city' => $city,
                    'order_type' => $type,
                    'email_template' => $template,
                    'email_subject_template' => $subjectTpl,
                    'active' => $active ? 1 : 0,
                    'attach_pdf' => $attachPdf ? 1 : 0,
                ],
                'weekdays' => $days,
                'error' => $err,
                'csrf' => Csrf::token(),
            ]);
            return;
        }

        if ($id > 0) {
            $this->repo->update($id, $name, $email, $phone, $fax, $mobile, $street, $city, $type, $template, $subjectTpl, $active, $attachPdf);
            $this->repo->setDeliveryWeekdays($id, $days);
        } else {
            $newId = $this->repo->create($name, $email, $phone, $fax, $mobile, $street, $city, $type, $template, $subjectTpl, $active, $attachPdf);
            $this->repo->setDeliveryWeekdays($newId, $days);
        }
        $_SESSION['flash_ok'] = 'Lieferant gespeichert.';
        Response::redirect('/suppliers');
    }
}
