<section class="page-section" x-data="{ q: '' }">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Lagerorte</h1>
        <div class="toolbar-actions">
            <a href="/locations/new" class="button button--primary button--small">Neu</a>
        </div>
    </div>
    <?php if (empty($locations)): ?>
        <div class="card card--pad">
            <p class="text-muted u-m-0">Noch keine Lagerorte. <a href="/locations/new">Ersten Lagerort anlegen</a>.</p>
        </div>
    <?php else: ?>
    <div class="round-search">
        <span class="round-search__icon" aria-hidden="true">⌕</span>
        <input class="input round-search__input" type="search" placeholder="Lagerort suchen …"
               x-model="q" @keydown.escape="q = ''" aria-label="Lagerorte suchen">
        <button type="button" class="round-search__clear"
                x-show="q !== ''"
                @click="q = ''"
                aria-label="Suche leeren">×</button>
    </div>
    <ul class="card-list">
        <?php foreach ($locations as $loc): ?>
            <?php $hay = mb_strtolower((string) ($loc['name'] ?? ''), 'UTF-8'); ?>
            <li class="card card--pad list-item"
                data-search="<?= htmlspecialchars($hay, ENT_QUOTES, 'UTF-8') ?>"
                x-show="!String(q).trim() || ($el.dataset.search || '').includes(String(q).trim().toLowerCase())">
                <div class="list-item__main">
                    <strong><?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="text-muted">Sortierung <?= (int) $loc['sort_order'] ?></span>
                    <?php if (!(int) $loc['active']): ?>
                        <span class="status-badge status-badge--warn">inaktiv</span>
                    <?php endif; ?>
                </div>
                <a href="/locations/edit?id=<?= (int) $loc['id'] ?>" class="button button--ghost button--small">Bearbeiten</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
