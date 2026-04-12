<section class="page-section">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Artikel</h1>
        <div class="toolbar-actions">
            <a href="/export/items" class="button button--ghost button--small" title="CSV-Export Artikel">&#8681; CSV</a>
            <a href="/export/item-supplier" class="button button--ghost button--small" title="CSV-Export Zuordnungen">&#8681; Zuordnungen</a>
            <a href="/items/new" class="button button--primary button--small">Neu</a>
        </div>
    </div>

    <div class="items-filter-panel">
        <div class="items-filter-panel__header">
            <span class="items-filter-panel__title">Suche &amp; Filter</span>
            <p class="items-filter-panel__hint text-muted">Lagerort, Lieferant, Status und Textsuche kombinierbar.</p>
        </div>
        <form method="get" action="/items" class="items-filter-panel__form">
            <div class="items-filter-grid">
                <div class="form-group">
                    <label class="form-label" for="filter-loc">Lagerort</label>
                    <select class="select" id="filter-loc" name="loc">
                        <option value="0" <?= ($filter_loc ?? 0) === 0 ? 'selected' : '' ?>>Alle</option>
                        <?php foreach ($locations ?? [] as $loc): ?>
                            <option value="<?= (int) $loc['id'] ?>" <?= (int) ($filter_loc ?? 0) === (int) $loc['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter-active">Status</label>
                    <select class="select" id="filter-active" name="active">
                        <?php $fa = $filter_active ?? 'all'; ?>
                        <option value="all" <?= $fa === 'all' ? 'selected' : '' ?>>Alle</option>
                        <option value="1" <?= $fa === '1' ? 'selected' : '' ?>>Aktiv</option>
                        <option value="0" <?= $fa === '0' ? 'selected' : '' ?>>Inaktiv</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filter-supplier">Lieferant</label>
                    <select class="select" id="filter-supplier" name="supplier">
                        <option value="0" <?= (int) ($filter_supplier ?? 0) === 0 ? 'selected' : '' ?>>Alle</option>
                        <?php foreach ($suppliers ?? [] as $sup): ?>
                            <option value="<?= (int) $sup['id'] ?>" <?= (int) ($filter_supplier ?? 0) === (int) $sup['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $sup['name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group items-filter-grid__full">
                    <label class="form-label" for="filter-q">Suche</label>
                    <input class="input" id="filter-q" name="q" type="search" placeholder="Name oder Einheit …"
                           value="<?= htmlspecialchars($filter_q ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                </div>
            </div>
            <div class="items-filter-panel__actions">
                <button type="submit" class="button button--secondary button--small">Anwenden</button>
                <a href="/items" class="button button--ghost button--small">Zurücksetzen</a>
            </div>
        </form>
    </div>

    <p class="text-muted" style="margin:0 0 var(--space-2)"><?= count($items) ?> Artikel<?= (($filter_loc ?? 0) > 0 || ($filter_supplier ?? 0) > 0 || ($filter_active ?? 'all') !== 'all' || (($filter_q ?? '') !== '')) ? ' (gefiltert)' : '' ?></p>

    <?php if (empty($items)): ?>
        <div class="card card--pad">
            <p class="text-muted" style="margin:0">Keine Artikel für diese Filter. <a href="/items/new">Ersten Artikel anlegen</a> oder Filter zurücksetzen.</p>
        </div>
    <?php else: ?>
    <ul class="card-list">
        <?php foreach ($items as $it): ?>
            <li class="card card--pad list-item">
                <div class="list-item__main">
                    <strong><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="text-muted"><?= htmlspecialchars($it['unit'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-muted"><?= htmlspecialchars($it['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($it['supplier_names']) && is_array($it['supplier_names'])): ?>
                        <div class="item-suppliers item-suppliers--list">
                            <?php foreach ($it['supplier_names'] as $si => $sname): ?>
                                <span class="supplier-chip <?= $si === 0 ? 'supplier-chip--primary' : '' ?>"><?= htmlspecialchars($sname, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!(int) $it['active']): ?>
                        <span class="status-badge status-badge--warn">inaktiv</span>
                    <?php endif; ?>
                </div>
                <a href="/items/edit?id=<?= (int) $it['id'] ?>" class="button button--ghost button--small">Bearbeiten</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
