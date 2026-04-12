<section class="page-section">
    <div class="page-toolbar">
        <h1 class="page-title">Artikel</h1>
        <a href="/items/new" class="button button--primary button--small">Neu</a>
    </div>
    <ul class="card-list">
        <?php foreach ($items as $it): ?>
            <li class="card card--pad list-item">
                <div class="list-item__main">
                    <strong><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="text-muted"><?= htmlspecialchars($it['unit'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-muted"><?= htmlspecialchars($it['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!(int) $it['active']): ?>
                        <span class="status-badge status-badge--warn">inaktiv</span>
                    <?php endif; ?>
                </div>
                <a href="/items/edit?id=<?= (int) $it['id'] ?>" class="button button--ghost button--small">Bearbeiten</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
