<?php
$appDisplayName = 'CT-Orderlauf';
if (!empty($_SESSION['user_id'])) {
    $appDisplayName = (new \App\Repositories\SettingsRepository())->get('app_name', 'CT-Orderlauf');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a5f4a">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars(($title ?? 'Start') . ' · ' . $appDisplayName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" href="/assets/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/assets/icons/icon.svg">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="app-body">
<?php require __DIR__ . '/partials/header.php'; ?>
<main class="app-main">
    <?= $content ?? '' ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<div id="toast-float" class="toast-float" aria-live="polite"></div>
<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
