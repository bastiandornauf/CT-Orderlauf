<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a5f4a">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars(($title ?? 'Start') . ' · CT-Orderlauf', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-body">
<?php require __DIR__ . '/partials/header.php'; ?>
<main class="app-main">
    <?= $content ?? '' ?>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
