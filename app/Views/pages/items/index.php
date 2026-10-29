<?php
$listParams = [];
if (($filter_loc ?? 0) > 0) {
    $listParams['loc'] = (int) $filter_loc;
}
if (($filter_active ?? 'all') !== 'all') {
    $listParams['active'] = $filter_active;
}
if (($filter_supplier ?? 0) > 0) {
    $listParams['supplier'] = (int) $filter_supplier;
}
if (trim((string) ($filter_q ?? '')) !== '') {
    $listParams['q'] = trim((string) $filter_q);
}
$checkKey = (string) ($check ?? '');
$checkDef = \App\Services\ItemQualityService::CHECKS[$checkKey] ?? null;
if ($checkDef !== null) {
    $listParams['check'] = $checkKey;
}
$listQuery = $listParams === [] ? '' : http_build_query($listParams);
$pageCfg = [
    'locations' => array_map(
        static fn (array $l): array => ['id' => (int) $l['id'], 'name' => (string) $l['name']],
        $locations ?? []
    ),
    'suppliers' => array_map(
        static fn (array $s): array => ['id' => (int) $s['id'], 'name' => (string) $s['name']],
        $suppliers ?? []
    ),
];
$pageCfgJson = json_encode($pageCfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
$pageCfgAttr = htmlspecialchars((string) $pageCfgJson, ENT_QUOTES, 'UTF-8');
?>
<section class="page-section" x-data="itemsListPage()" data-items-page="<?= $pageCfgAttr ?>">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success" role="status"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Artikel</h1>
        <div class="toolbar-actions">
            <a href="/items/pending" class="button button--secondary button--small" title="Freitext aus Bestellung und Inventur">Artikel-Vorschläge</a>
            <a href="/items/new<?= $listQuery !== '' ? '?' . htmlspecialchars($listQuery, ENT_QUOTES, 'UTF-8') : '' ?>" class="button button--primary button--small">Neu</a>
        </div>
    </div>

    <?php if ($checkDef !== null): ?>
        <div class="card card--pad maintenance-banner maintenance-banner--<?= $checkDef[1] ?>">
            <strong><?= htmlspecialchars($checkDef[0], ENT_QUOTES, 'UTF-8') ?></strong>
            <span class="text-muted"><?= htmlspecialchars($checkDef[3], ENT_QUOTES, 'UTF-8') ?></span>
            <a href="/pflege" class="button button--ghost button--small">Zur Datenpflege</a>
        </div>
    <?php endif; ?>

    <div class="items-filter-panel">
        <div class="items-filter-panel__header">
            <span class="items-filter-panel__title">Suche &amp; Filter</span>
            <p class="items-filter-panel__hint text-muted">Lagerort, Lieferant, Status und Textsuche kombinierbar.</p>
        </div>
        <form method="get" action="/items" class="items-filter-panel__form">
            <?php if ($checkDef !== null): ?>
                <input type="hidden" name="check" value="<?= htmlspecialchars($checkKey, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
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
                    <input class="input" id="filter-q" name="q" type="search" placeholder="Name, Alias, Art.-Nr. …"
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

    <p class="text-muted u-m-0 u-mb-2"><?= count($items) ?> Artikel<?= (($filter_loc ?? 0) > 0 || ($filter_supplier ?? 0) > 0 || ($filter_active ?? 'all') !== 'all' || (($filter_q ?? '') !== '')) ? ' (gefiltert)' : '' ?></p>

    <?php if (empty($items)): ?>
        <div class="card card--pad">
            <p class="text-muted u-m-0">Keine Artikel für diese Filter. <a href="/items/new<?= $listQuery !== '' ? '?' . htmlspecialchars($listQuery, ENT_QUOTES, 'UTF-8') : '' ?>">Ersten Artikel anlegen</a> oder Filter zurücksetzen.</p>
        </div>
    <?php else: ?>
    <ul class="card-list">
        <?php foreach ($items as $it): ?>
            <?php
            $itemPayload = [
                'id' => (int) $it['id'],
                'name' => (string) ($it['name'] ?? ''),
                'alias' => (string) ($it['alias'] ?? ''),
                'unit_factor' => $it['unit_factor'] ?? null,
                'base_unit' => $it['base_unit'] ?? null,
                'unit' => (string) ($it['unit'] ?? ''),
                'location_id' => (int) ($it['location_id'] ?? 0),
                'sort_order' => (int) ($it['sort_order'] ?? 0),
                'min_stock' => isset($it['min_stock']) && $it['min_stock'] !== null && $it['min_stock'] !== ''
                    ? (int) $it['min_stock'] : null,
                'max_stock' => isset($it['max_stock']) && $it['max_stock'] !== null && $it['max_stock'] !== ''
                    ? (int) $it['max_stock'] : null,
                'valuation_price' => isset($it['valuation_price']) && $it['valuation_price'] !== null && $it['valuation_price'] !== ''
                    ? (string) $it['valuation_price'] : null,
                'active' => (int) ($it['active'] ?? 0),
                'supplier_links' => $it['supplier_links'] ?? [],
            ];
            $itemJson = json_encode($itemPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
            $itemAttr = htmlspecialchars((string) $itemJson, ENT_QUOTES, 'UTF-8');
            ?>
            <li class="card card--pad list-item" id="item-<?= (int) $it['id'] ?>" data-item-id="<?= (int) $it['id'] ?>" data-item="<?= $itemAttr ?>">
                <div class="list-item__main">
                    <strong data-item-display-name><?= htmlspecialchars(\App\Helpers\ItemDisplay::name($it), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="text-muted" data-item-formal-name <?= trim((string) ($it['alias'] ?? '')) === '' ? 'hidden' : '' ?>><?= htmlspecialchars($it['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-muted" data-item-unit><?= htmlspecialchars($it['unit'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="text-muted" data-item-location><?= htmlspecialchars($it['location_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($it['supplier_names']) && is_array($it['supplier_names'])): ?>
                        <div class="item-suppliers item-suppliers--list" data-item-suppliers>
                            <?php foreach ($it['supplier_names'] as $si => $sname): ?>
                                <span class="supplier-chip <?= $si === 0 ? 'supplier-chip--primary' : '' ?>"><?= htmlspecialchars($sname, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!(int) $it['active']): ?>
                        <span class="status-badge status-badge--warn" data-item-inactive>inaktiv</span>
                    <?php endif; ?>
                    <?php if (($check_details[(int) $it['id']] ?? '') !== ''): ?>
                        <span class="maintenance-detail"><?= htmlspecialchars($check_details[(int) $it['id']], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <a href="/items/edit?id=<?= (int) $it['id'] ?><?= $listQuery !== '' ? '&' . htmlspecialchars($listQuery, ENT_QUOTES, 'UTF-8') : '' ?>"
                   class="button button--ghost button--small"
                   @click="openEdit($event)">Bearbeiten</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <dialog class="item-quick-edit"
            x-ref="itemEditDialog"
            aria-labelledby="item-edit-title"
            @click="if ($event.target === $refs.itemEditDialog) closeEdit()"
            @close="editError = ''">
        <form class="item-quick-edit__panel form-stack" @submit.prevent="submitEdit()">
            <h2 id="item-edit-title" class="section-header">Artikel bearbeiten</h2>
            <p class="toast toast--error" x-show="editError" x-text="editError" role="alert"></p>

            <div class="form-group">
                <label class="form-label" for="ie-name">Name</label>
                <input class="input" id="ie-name" x-ref="ieName" type="text" required x-model="editDraft.name">
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-alias">Alias (optional)</label>
                <input class="input" id="ie-alias" type="text" placeholder="z. B. Pommes" x-model="editDraft.alias">
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-unit">Einheit</label>
                <input class="input" id="ie-unit" type="text" x-model="editDraft.unit">
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-uf">Inhalt je Einheit (optional)</label>
                <div class="form-row unit-content">
                    <input class="input" id="ie-uf" type="text" inputmode="decimal" placeholder="z. B. 10" x-model="editDraft.unit_factor">
                    <select class="select" aria-label="Einheit des Inhalts" x-model="editDraft.base_unit">
                        <option value="">—</option>
                        <option value="kg">kg</option>
                        <option value="l">l</option>
                        <option value="stk">Stück</option>
                    </select>
                </div>
                <p class="form-hint">Für die Inventurbewertung, z. B. 1 Karton = 10 kg. Bei Einheit kg, l oder Stück nicht nötig.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-loc">Lagerort</label>
                <select class="select" id="ie-loc" required x-model="editDraft.location_id">
                    <option value="">— wählen —</option>
                    <template x-for="loc in locations" :key="loc.id">
                        <option :value="String(loc.id)" x-text="loc.name"></option>
                    </template>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-sort">Reihenfolge im Rundgang (optional)</label>
                <input class="input" id="ie-sort" type="number" x-model="editDraft.sort_order">
                <p class="form-hint">Niedrigere Zahl = weiter oben im Lager-Tab; gleiche Zahl = alphabetisch nach Name.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-min">Mindestbestand (optional)</label>
                <input class="input" id="ie-min" type="number" x-model="editDraft.min_stock">
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-max">Maximalbestand (optional)</label>
                <input class="input" id="ie-max" type="number" x-model="editDraft.max_stock">
            </div>
            <div class="form-group">
                <label class="form-label" for="ie-price">Bewertungspreis pro Einheit (optional)</label>
                <input class="input" id="ie-price" type="text" inputmode="decimal" placeholder="z. B. 2,50" x-model="editDraft.valuation_price">
                <p class="form-hint">Nur für Inventur-Export (Wert = Menge × Preis). Bestellung bleibt unberührt.</p>
            </div>

            <h3 class="section-header u-mb-0">Lieferanten &amp; Priorität</h3>
            <p class="form-hint text-muted">Höhere Zahl = bevorzugt bei mehreren Lieferanten am Zieltag.</p>
            <template x-for="(row, idx) in editSupplierRows" :key="idx">
                <div class="form-row item-quick-edit__supplier-row">
                    <div class="form-group">
                        <label class="form-label" :for="'ie-sup-' + idx">Lieferant</label>
                        <select class="select" :id="'ie-sup-' + idx" x-model="row.supplier_id">
                            <option value="">—</option>
                            <template x-for="s in suppliers" :key="s.id">
                                <option :value="String(s.id)" x-text="s.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="form-group item-quick-edit__prio">
                        <label class="form-label" :for="'ie-prio-' + idx">Priorität</label>
                        <input class="input" :id="'ie-prio-' + idx" type="number" x-model.number="row.priority">
                    </div>
                    <button type="button" class="button button--ghost button--small item-quick-edit__remove-row"
                            x-show="editSupplierRows.length > 1"
                            @click="removeEditSupplierRow(idx)"
                            title="Lieferant entfernen">&times;</button>
                    <?php $articleFieldPrefix = 'ie-sa'; $articleFieldNamed = false; $priceItemIdExpr = 'editDraft.id'; $priceSupplierIdExpr = 'editSupplierRows[idx]?.supplier_id';
                    require __DIR__ . '/../../partials/supplier-article-fields.php'; ?>
                </div>
            </template>
            <button type="button" class="button button--ghost button--small" @click="addEditSupplierRow()">+ Lieferant</button>

            <label class="checkbox">
                <input type="checkbox" x-model="editDraft.active">
                aktiv
            </label>

            <div class="item-quick-edit__actions">
                <button type="button" class="button button--ghost" @click="closeEdit()" :disabled="editSaving">Abbrechen</button>
                <?php if ($checkDef !== null): ?>
                <button type="button" class="button button--secondary" @click="submitEdit(true)" :disabled="editSaving">Speichern &amp; nächster</button>
                <?php endif; ?>
                <button type="submit" class="button button--primary" :disabled="editSaving">
                    <span x-show="!editSaving">Speichern</span>
                    <span x-show="editSaving">…</span>
                </button>
            </div>
        </form>
    </dialog>
</section>
