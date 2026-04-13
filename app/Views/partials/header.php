<?php
$loggedIn = !empty($_SESSION['user_id']);
$appName = $appDisplayName ?? 'CT-Orderlauf';
$_devMode = false;
if ($loggedIn) {
    $_settingsRepo = new \App\Repositories\SettingsRepository();
    $_devMode = $_settingsRepo->get('dev_mode', '0') === '1';
}
$_role = (string) ($_SESSION['role'] ?? '');
$_canEditMaster = \App\Helpers\UserRole::canEditMasterData($_role);
$_isAdmin = \App\Helpers\UserRole::isAdmin($_role);

$navPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navPath = rtrim($navPath, '/') ?: '/';
$navActive = static function (string $prefix, bool $exact = false) use ($navPath): bool {
    if ($exact) {
        return $navPath === $prefix || ($prefix === '/' && ($navPath === '' || $navPath === '/'));
    }
    return str_starts_with($navPath, $prefix);
};
?>
<?php if ($_devMode): ?>
<div class="dev-banner">Testbetrieb – E-Mails werden umgeleitet</div>
<?php endif; ?>
<header class="app-header" <?php if ($loggedIn): ?>x-data="appHeader()" @keydown.escape.window="closeNav()"<?php endif; ?>>
    <div class="app-header__inner">
        <a href="/" class="app-header__brand"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a>
        <div class="app-header__end">
            <div class="app-header__status" data-online-indicator aria-live="polite">
                <span class="status-badge status-badge--offline" data-offline-badge>Offline</span>
            </div>
            <?php if ($loggedIn): ?>
            <a href="/profile" class="app-header__user" title="Mein Konto"><?= htmlspecialchars((string) ($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
            <button type="button"
                    class="app-header__menu-btn"
                    aria-label="Menü"
                    :aria-expanded="navOpen"
                    aria-controls="main-nav"
                    @click="toggleNav()">
                <span class="app-header__menu-bars" aria-hidden="true"></span>
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($loggedIn): ?>
    <nav id="main-nav" class="app-nav" aria-label="Hauptnavigation"
         :class="{ 'app-nav--drawer-open': navOpen }">
        <div class="app-nav__backdrop" @click="closeNav()"></div>
        <div class="app-nav__sheet">
            <div class="app-nav__sheet-head">
                <span class="app-nav__sheet-title">Menü</span>
                <button type="button" class="app-nav__close" aria-label="Menü schließen" @click="closeNav()">&times;</button>
            </div>
            <div class="app-nav__links">
                <a href="/" class="app-nav__link<?= $navActive('/', true) ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Start</a>
                <a href="/order/prepare" class="app-nav__link<?= $navActive('/order') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Bestellen</a>
                <?php if ($_canEditMaster): ?>
                <a href="/locations" class="app-nav__link<?= $navActive('/locations') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Lagerorte</a>
                <a href="/suppliers" class="app-nav__link<?= $navActive('/suppliers') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Lieferanten</a>
                <a href="/items" class="app-nav__link<?= $navActive('/items') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Artikel</a>
                <a href="/import" class="app-nav__link<?= $navActive('/import') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Import</a>
                <a href="/settings" class="app-nav__link<?= $navActive('/settings') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Einstellungen</a>
                <?php endif; ?>
                <?php if ($_isAdmin): ?>
                <a href="/admin/users" class="app-nav__link<?= $navActive('/admin/users') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Benutzer</a>
                <?php endif; ?>
                <a href="/profile" class="app-nav__link<?= $navActive('/profile') ? ' app-nav__link--active' : '' ?>" @click="closeNav()">Mein Konto</a>
                <form method="post" action="/logout" class="app-nav__logout">
                    <?= \App\Helpers\Csrf::field() ?>
                    <button type="submit" class="app-nav__link app-nav__link--logout">Abmelden</button>
                </form>
            </div>
        </div>
    </nav>
    <?php endif; ?>
</header>
