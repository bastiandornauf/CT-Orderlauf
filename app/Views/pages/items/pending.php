<?php

use App\Helpers\UserRole;

$canEditMaster = UserRole::canEditMasterData((string) ($_SESSION['role'] ?? ''));
$locJson = [];
foreach ($locations ?? [] as $loc) {
    $locJson[] = ['id' => (int) $loc['id'], 'name' => (string) $loc['name']];
}
?>
<section class="page-section"
         x-data="pendingItemsPage"
         data-can-edit-master="<?= $canEditMaster ? '1' : '0' ?>"
         data-locations="<?= htmlspecialchars(json_encode($locJson, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <div class="page-toolbar">
        <h1 class="page-title">Neue Artikel</h1>
        <div class="toolbar-actions">
            <a href="/items" class="button button--ghost button--small">Zur Artikelliste</a>
        </div>
    </div>

    <p class="text-muted">
        Hier sammeln sich alle per <strong>Freitext</strong> erfassten Positionen aus <strong>Inventur</strong> und <strong>Bestellung</strong>.
        <span x-show="canTransfer">Bezeichnung/Lagerort prüfen und in die Stammdaten übernehmen (online).</span>
        <span x-show="!canTransfer">Übernahme in die Stammdaten benötigt Stammdaten-Recht.</span>
    </p>

    <p class="toast toast--warn" x-show="ready && newItems.length === 0" x-cloak>
        Keine offenen Freitext-Artikel. Neue entstehen im <a href="/inventory/round">Inventur-Rundgang</a> oder in der <a href="/order/round">Bestellung</a>.
    </p>

    <div class="button-row" x-show="ready && canTransfer && newItems.length > 1" x-cloak style="margin-bottom: var(--space-3);">
        <button type="button" class="button button--secondary button--small"
                :disabled="bulkBusy"
                @click.prevent="transferAll()">
            <span x-text="bulkBusy ? 'Übernehme …' : 'Alle übernehmen'">Alle übernehmen</span>
        </button>
    </div>

    <ul class="card-list inventory-newitems__list" x-show="ready && newItems.length > 0" x-cloak>
        <template x-for="ni in newItems" :key="ni.key">
            <li class="card card--pad list-item--stack inventory-newitems__item">
                <div class="inventory-newitems__head">
                    <span class="status-badge status-badge--neutral" x-text="ni.sourceLabel"></span>
                    <span class="text-muted" x-show="ni.quantity" x-text="'Menge: ' + String(ni.quantity).replace('.', ',')"></span>
                </div>
                <div class="form-group">
                    <label class="form-label">Bezeichnung</label>
                    <input class="input" x-model="ni.name" :disabled="!canTransfer || ni.busy">
                </div>
                <div class="form-group">
                    <label class="form-label">Gebinde / Einheit</label>
                    <input class="input" x-model="ni.unit" :disabled="!canTransfer || ni.busy" placeholder="optional">
                </div>
                <div class="form-group">
                    <label class="form-label">Lagerort</label>
                    <select class="select" x-model.number="ni.location_id" :disabled="!canTransfer || ni.busy">
                        <option value="">— wählen —</option>
                        <template x-for="loc in transferLocations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.name"></option>
                        </template>
                    </select>
                </div>
                <div class="button-row">
                    <button type="button" class="button button--primary button--small"
                            x-show="canTransfer"
                            :disabled="ni.busy"
                            @click.prevent="transferNewItem(ni)">
                        <span x-text="ni.busy ? 'Lege an …' : 'In Stammdaten übernehmen'">In Stammdaten übernehmen</span>
                    </button>
                    <button type="button" class="button button--ghost button--small"
                            :disabled="ni.busy"
                            @click.prevent="dismissNewItem(ni)">Ausblenden</button>
                </div>
            </li>
        </template>
    </ul>
</section>
