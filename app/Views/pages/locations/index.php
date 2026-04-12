<section class="page-section">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Lagerorte</h1>
        <div class="toolbar-actions">
            <a href="/export/locations" class="button button--ghost button--small" title="CSV-Export">&#8681; CSV</a>
            <a href="/locations/new" class="button button--primary button--small">Neu</a>
        </div>
    </div>
    <?php if (empty($locations)): ?>
        <div class="card card--pad">
            <p class="text-muted" style="margin:0">Noch keine Lagerorte. <a href="/locations/new">Ersten Lagerort anlegen</a>.</p>
        </div>
    <?php else: ?>
    <ul class="card-list">
        <?php foreach ($locations as $loc): ?>
            <li class="card card--pad list-item">
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
