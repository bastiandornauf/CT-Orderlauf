<?php
$item = $item ?? [];
$linkRows = [];
foreach ($links ?? [] as $l) {
    $linkRows[] = [
        'supplier_id' => (string) (int) $l['supplier_id'],
        'priority' => (int) $l['priority'],
    ];
}
if ($linkRows === []) {
    $linkRows = [['supplier_id' => '', 'priority' => 0]];
}
$supplierNamesMap = [];
foreach ($suppliers ?? [] as $s) {
    $supplierNamesMap[(string) (int) $s['id']] = $s['name'];
}
$formState = [
    'rows' => $linkRows,
    'supplierNames' => $supplierNamesMap,
];
$formStateJson = json_encode($formState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
$formStateAttr = htmlspecialchars((string) $formStateJson, ENT_QUOTES, 'UTF-8');
?>
<section
    class="page-section"
    data-item-form="<?= $formStateAttr ?>"
    x-data="{
        rows: [],
        supplierNames: {},
        init() {
            const raw = this.$el.dataset.itemForm;
            if (!raw) return;
            try {
                const cfg = JSON.parse(raw);
                this.rows = Array.isArray(cfg.rows) ? cfg.rows : [];
                this.supplierNames = cfg.supplierNames && typeof cfg.supplierNames === 'object' ? cfg.supplierNames : {};
            } catch (e) {
                console.error('Artikel-Form: JSON', e);
            }
        },
    }"
>
    <h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/items/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <?php if (!empty($item['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input class="input" id="name" name="name" required value="<?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="unit">Einheit</label>
            <input class="input" id="unit" name="unit" value="<?= htmlspecialchars($item['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="location_id">Lagerort</label>
            <select class="select" id="location_id" name="location_id" required>
                <option value="">— wählen —</option>
                <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int) $loc['id'] ?>" <?= isset($item['location_id']) && (int) $item['location_id'] === (int) $loc['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($loc['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="min_stock">Mindestbestand (optional)</label>
            <input class="input" id="min_stock" name="min_stock" type="number"
                   value="<?= isset($item['min_stock']) && $item['min_stock'] !== null ? htmlspecialchars((string) $item['min_stock'], ENT_QUOTES, 'UTF-8') : '' ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="max_stock">Maximalbestand (optional)</label>
            <input class="input" id="max_stock" name="max_stock" type="number"
                   value="<?= isset($item['max_stock']) && $item['max_stock'] !== null ? htmlspecialchars((string) $item['max_stock'], ENT_QUOTES, 'UTF-8') : '' ?>">
        </div>

        <h2 class="section-header">Lieferanten &amp; Priorität</h2>
        <p class="text-muted">Höhere Zahl = bevorzugt bei mehreren Lieferanten am Zieltag.</p>

        <div class="item-supplier-overview">
            <span class="item-supplier-overview__label">Zuordnung</span>
            <div class="item-suppliers item-suppliers--form">
                <template x-for="(row, idx) in rows" :key="'chip-' + idx">
                    <span class="supplier-chip supplier-chip--form"
                          x-show="row.supplier_id"
                          x-text="(supplierNames[row.supplier_id] || '?') + ' · Prio ' + row.priority"></span>
                </template>
            </div>
            <p class="form-hint item-supplier-overview__empty text-muted" x-show="!rows.some(r => r.supplier_id)">Noch kein Lieferant gewählt.</p>
        </div>

        <template x-for="(row, idx) in rows" :key="idx">
            <div class="supplier-link-row">
                <div class="supplier-link-row__head">
                    <div class="supplier-link-row__chip" x-show="row.supplier_id">
                        <span class="supplier-chip supplier-chip--form supplier-chip--inline" x-text="supplierNames[row.supplier_id] || '—'"></span>
                    </div>
                    <button type="button" class="button button--small button--ghost supplier-link-row__remove"
                            x-show="rows.length > 1"
                            @click="rows.splice(idx, 1)"
                            title="Lieferant entfernen">&times;</button>
                </div>
                <div class="form-row supplier-link-row__fields">
                    <div class="form-group supplier-link-row__select">
                        <label class="form-label" :for="'supplier_id_' + idx">Lieferant</label>
                        <select class="select" :id="'supplier_id_' + idx" :name="'supplier_id[' + idx + ']'" x-model="row.supplier_id">
                            <option value="">—</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group supplier-link-row__prio">
                        <label class="form-label" :for="'supplier_priority_' + idx">Priorität</label>
                        <input class="input" type="number" :id="'supplier_priority_' + idx" :name="'supplier_priority[' + idx + ']'" x-model.number="row.priority">
                    </div>
                </div>
            </div>
        </template>
        <button type="button" class="button button--ghost button--small" @click="rows.push({ supplier_id: '', priority: 0 })">+ Lieferant</button>

        <label class="checkbox">
            <input type="checkbox" name="active" <?= !isset($item['active']) || (int) $item['active'] ? 'checked' : '' ?>>
            aktiv
        </label>
        <button type="submit" class="button button--primary">Speichern</button>
    </form>
</section>
