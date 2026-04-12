<section class="page-section">
    <div class="page-toolbar">
        <h1 class="page-title">Lagerorte</h1>
        <a href="/locations/new" class="button button--primary button--small">Neu</a>
    </div>
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
</section>
