<?php
$loggedIn = !empty($_SESSION['user_id']);
$appName = 'CT-Orderlauf';
?>
<header class="app-header">
    <div class="app-header__inner">
        <a href="/" class="app-header__brand"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a>
        <div class="app-header__status" data-online-indicator aria-live="polite">
            <span class="status-badge status-badge--offline" data-offline-badge>Offline</span>
        </div>
    </div>
    <?php if ($loggedIn): ?>
    <nav class="app-nav" aria-label="Hauptnavigation">
        <a href="/" class="app-nav__link">Start</a>
        <a href="/order/prepare" class="app-nav__link">Bestellen</a>
        <a href="/locations" class="app-nav__link">Lagerorte</a>
        <a href="/suppliers" class="app-nav__link">Lieferanten</a>
        <a href="/items" class="app-nav__link">Artikel</a>
        <a href="/import" class="app-nav__link">Import</a>
        <a href="/settings" class="app-nav__link">Einstellungen</a>
    </nav>
    <?php endif; ?>
</header>
