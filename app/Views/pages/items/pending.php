<?php

use App\Helpers\UserRole;

$canEditMaster = UserRole::canEditMasterData((string) ($_SESSION['role'] ?? ''));
$locJson = [];
foreach ($locations ?? [] as $loc) {
    $locJson[] = ['id' => (int) $loc['id'], 'name' => (string) $loc['name']];
}
$supJson = [];
foreach ($suppliers ?? [] as $sup) {
    $supJson[] = ['id' => (int) $sup['id'], 'name' => (string) $sup['name']];
}
?>
<section class="page-section"
         x-data="pendingItemsPage"
         data-can-edit-master="<?= $canEditMaster ? '1' : '0' ?>"
         data-locations="<?= htmlspecialchars(json_encode($locJson, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
         data-suppliers="<?= htmlspecialchars(json_encode($supJson, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">
    <div class="page-toolbar">
        <h1 class="page-title">Artikel-Vorschläge</h1>
        <div class="toolbar-actions">
            <a href="/items" class="button button--ghost button--small">Zur Artikelliste</a>
        </div>
    </div>

    <p class="text-muted">
        Freitext aus <strong>Bestellung</strong> und <strong>Inventur</strong>, von allen Nutzern.
        Häufig getippte stehen oben.
        <span x-show="canTransfer">Lagerort und Lieferant aus der Erfassung prüfen, dann übernehmen (online).</span>
    </p>
    <p class="text-muted">
        <strong>Verwerfen</strong> blendet aus, bis derselbe Name wieder getippt wird.
    </p>

    <p class="toast toast--error" role="alert" x-show="loadError" x-text="loadError" x-cloak></p>

    <p class="toast toast--warn" x-show="ready && !loadError && newItems.length === 0" x-cloak>
        Keine offenen Freitext-Artikel. Neue entstehen im <a href="/inventory/round">Inventur-Rundgang</a> oder in der <a href="/order/round">Bestellung</a>.
    </p>

    <div class="button-row u-mb-3" x-show="ready && canTransfer && newItems.length > 1" x-cloak>
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
                    <span class="status-badge status-badge--ok" x-show="ni.seenCount > 1"
                          x-text="ni.seenCount + '× erfasst'"></span>
                    <span class="text-muted" x-show="ni.quantity" x-text="'Menge: ' + String(ni.quantity).replace('.', ',')"></span>
                </div>
                <p class="text-muted" x-show="ni.firstSeenAt">
                    <span x-text="'Zuerst: ' + formatSeen(ni.firstSeenAt)"></span>
                    <span x-show="ni.lastSeenAt && ni.lastSeenAt !== ni.firstSeenAt"
                          x-text="' · Zuletzt: ' + formatSeen(ni.lastSeenAt)"></span>
                    <span x-show="ni.lastSeenByName" x-text="' · von ' + ni.lastSeenByName"></span>
                </p>
                <div class="form-group">
                    <label class="form-label" :for="'ni-name-' + ni.key">Bezeichnung</label>
                    <input class="input" :id="'ni-name-' + ni.key" x-model="ni.name" :disabled="!canTransfer || ni.busy">
                </div>
                <div class="form-group">
                    <label class="form-label" :for="'ni-unit-' + ni.key">Gebinde / Einheit</label>
                    <input class="input" :id="'ni-unit-' + ni.key" x-model="ni.unit" :disabled="!canTransfer || ni.busy" placeholder="optional">
                </div>
                <div class="form-group">
                    <label class="form-label" :for="'ni-loc-' + ni.key">Lagerort</label>
                    <select class="select" :id="'ni-loc-' + ni.key" x-model.number="ni.location_id" :disabled="!canTransfer || ni.busy">
                        <option value="">— wählen —</option>
                        <template x-for="loc in transferLocations" :key="loc.id">
                            <option :value="loc.id" x-text="loc.name"></option>
                        </template>
                    </select>
                    <p class="form-hint text-muted" x-show="ni.location_from_source" x-text="'Aus Erfassung: ' + ni.location_from_source"></p>
                </div>
                <div class="form-group" x-show="ni.source === 'order' || ni.supplier_id">
                    <label class="form-label" :for="'ni-sup-' + ni.key">Lieferant</label>
                    <select class="select" :id="'ni-sup-' + ni.key" x-model.number="ni.supplier_id" :disabled="!canTransfer || ni.busy">
                        <option value="">— keiner —</option>
                        <template x-for="sup in transferSuppliers" :key="sup.id">
                            <option :value="sup.id" x-text="sup.name"></option>
                        </template>
                    </select>
                    <p class="form-hint text-muted" x-show="ni.supplier_from_source" x-text="'Aus Bestellung: ' + ni.supplier_from_source"></p>
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
                            @click.prevent="dismissNewItem(ni)">Verwerfen</button>
                </div>
            </li>
        </template>
    </ul>
</section>
