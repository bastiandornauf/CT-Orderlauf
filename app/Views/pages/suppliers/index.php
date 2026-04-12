<section class="page-section">
    <div class="page-toolbar">
        <h1 class="page-title">Lieferanten</h1>
        <a href="/suppliers/new" class="button button--primary button--small">Neu</a>
    </div>
    <ul class="card-list">
        <?php foreach ($suppliers as $s): ?>
            <li class="card card--pad list-item">
                <div class="list-item__main">
                    <strong><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="text-muted"><?= htmlspecialchars($s['order_type'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($s['email']): ?>
                        <span class="text-muted"><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($s['weekdays'])): ?>
                        <span class="text-muted">Liefertage: <?= htmlspecialchars(implode(',', $s['weekdays']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!(int) $s['active']): ?>
                        <span class="status-badge status-badge--warn">inaktiv</span>
                    <?php endif; ?>
                </div>
                <a href="/suppliers/edit?id=<?= (int) $s['id'] ?>" class="button button--ghost button--small">Bearbeiten</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
