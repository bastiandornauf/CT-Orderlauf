<?php

use App\Helpers\UserRole;

$canEditMaster = UserRole::canEditMasterData((string) ($_SESSION['role'] ?? ''));
?>
<section class="page-section" x-data="roundPage()" data-can-edit-master="<?= $canEditMaster ? '1' : '0' ?>">
    <?php $order_step = 2;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Rundgang</h1>
        <div class="toolbar-actions">
            <span class="status-badge status-badge--neutral">offline nutzbar</span>
            <button type="button" class="button button--primary button--small" @click="goReview()">Zur Kontrolle →</button>
        </div>
    </div>
    <p class="text-muted">Eingaben werden lokal gespeichert. Leere Felder = keine Bestellung.</p>

    <div class="round-search">
        <span class="round-search__icon" aria-hidden="true">⌕</span>
        <input class="input round-search__input" type="search"
               placeholder="Artikel suchen …"
               x-model="search"
               @keydown.escape="search = ''">
        <button type="button" class="round-search__clear"
                x-show="search !== ''"
                @click="search = ''"
                aria-label="Suche leeren">×</button>
    </div>

    <details class="card card--pad round-supplier-hide" x-show="search === '' && suppliers.length" x-cloak>
        <summary class="round-supplier-hide__summary">Lieferanten ausblenden (nur Anzeige im Rundgang)</summary>
        <p class="form-hint text-muted" style="margin: var(--space-2) 0;">Ausgewählte Lieferanten erscheinen nicht bei den Artikeln und im Feld „Freier Artikel“. Artikel, die nur bei diesen Lieferanten bestellt werden können, werden im Rundgang ausgeblendet. Kontrolle &amp; Ausgabe bleiben vollständig.</p>
        <div class="round-supplier-hide__chips">
            <template x-for="s in suppliers.filter(x => x.active)" :key="s.id">
                <label class="round-supplier-hide__label">
                    <input type="checkbox" :checked="isSupplierHidden(s.id)" @change="toggleSupplierHidden(s.id)">
                    <span x-text="s.name"></span>
                </label>
            </template>
        </div>
    </details>

    <div class="tab-bar" role="tablist" x-show="search === ''">
        <template x-for="loc in locations" :key="loc.id">
            <button type="button" class="tab-bar__btn" role="tab"
                    :class="{ 'tab-bar__btn--active': activeLocId === loc.id }"
                    @click="activeLocId = loc.id">
                <span class="button__label" x-text="tabLabel(loc)">Lager</span>
            </button>
        </template>
    </div>
    <p class="text-muted" x-show="search !== ''" style="margin-bottom: var(--space-2); font-size: var(--text-sm)">
        Alle Lagerorte · <span x-text="filteredItems.length"></span> Treffer
    </p>

    <ul class="card-list">
        <template x-for="it in filteredItems" :key="it.id">
            <li class="card card--pad list-item list-item--round"
                :class="{ 'card--has-qty': hasQty(it.id) }">
                <div class="list-item__main">
                    <div class="list-item__title-row">
                        <strong x-text="it.name"></strong>
                        <button type="button"
                                class="round-item-edit"
                                x-show="canEditMaster"
                                @click="openQuickEdit(it)"
                                title="Artikel bearbeiten"
                                aria-label="Artikel bearbeiten">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                    </div>
                    <span class="text-muted order-item-meta">
                        <span x-text="it.unit"></span><span class="order-stock-hint" x-show="stockHint(it)" x-text="' · ' + stockHint(it)"></span>
                    </span>
                    <div class="item-suppliers" x-show="itemSupplierNames(it.id).length > 0">
                        <template x-for="(sup, idx) in itemSupplierNames(it.id)" :key="sup">
                            <span class="supplier-chip"
                                  :class="{ 'supplier-chip--primary': idx === 0 }"
                                  x-text="sup"></span>
                        </template>
                    </div>
                </div>
                <div class="qty-stepper">
                    <button type="button" class="qty-stepper__btn" @click="onQtyStep(it.id, -1)" aria-label="Minus">−</button>
                    <input class="input input--qty" type="text" inputmode="decimal"
                           :value="quantities[it.id] || ''"
                           @blur="onQtyBlur(it.id, $event)"
                           :aria-label="'Menge ' + it.name">
                    <button type="button" class="qty-stepper__btn" @click="onQtyStep(it.id, 1)" aria-label="Plus">+</button>
                </div>
            </li>
        </template>
    </ul>

    <div class="round-next-loc" x-show="search === '' && locations.length > 1">
        <button type="button" class="button button--secondary" @click="nextLocation()">
            Nächstes Lager: <span x-text="nextLocationLabel()"></span> →
        </button>
    </div>

    <p class="round-search__empty" x-show="filteredItems.length === 0 && search !== ''">
        Kein Artikel gefunden für „<span x-text="search"></span>"
    </p>

    <div class="card card--pad form-stack" x-show="search === ''">
        <h2 class="section-header">Freier Artikel (dieser Lagerort)</h2>
        <div class="form-group">
            <label class="form-label">Bezeichnung</label>
            <input class="input" x-model="freeLabel">
        </div>
        <div class="form-group">
            <label class="form-label">Menge</label>
            <input class="input" x-model="freeQty" inputmode="decimal">
        </div>
        <div class="form-group">
            <label class="form-label">Lieferant (optional)</label>
            <select class="select" x-model="freeSupplierId">
                <option value="">— später in Kontrolle —</option>
                <template x-for="s in suppliersForFree" :key="s.id">
                    <option :value="String(s.id)" x-text="s.name"></option>
                </template>
            </select>
        </div>
        <button type="button" class="button button--secondary" @click="addFree()">Freie Position hinzufügen</button>
    </div>

    <div class="button-stack">
        <button type="button" class="button button--primary button--block" @click="goReview()">Zur Kontrolle</button>
        <a href="/" class="button button--ghost button--block">Zurück zum Start</a>
    </div>

    <dialog class="item-quick-edit"
            x-ref="quickEditDialog"
            aria-labelledby="item-quick-edit-title"
            @click="if ($event.target === $refs.quickEditDialog) closeQuickEdit()"
            @close="editError = ''">
        <form class="item-quick-edit__panel form-stack" @submit.prevent="submitQuickEdit()">
            <h2 id="item-quick-edit-title" class="section-header" style="margin-top:0">Artikel bearbeiten</h2>
            <p class="text-muted" style="margin:0">Änderungen werden auf dem Server gespeichert und lokal übernommen.</p>
            <p class="toast toast--error" x-show="editError" x-text="editError" style="margin:0"></p>

            <div class="form-group">
                <label class="form-label" for="qe-name">Name</label>
                <input class="input" id="qe-name" type="text" required x-model="editDraft.name">
            </div>
            <div class="form-group">
                <label class="form-label" for="qe-unit">Einheit</label>
                <input class="input" id="qe-unit" type="text" x-model="editDraft.unit">
            </div>
            <div class="form-group">
                <label class="form-label" for="qe-loc">Lagerort</label>
                <select class="select" id="qe-loc" required x-model="editDraft.location_id">
                    <option value="">— wählen —</option>
                    <template x-for="loc in locations" :key="loc.id">
                        <option :value="String(loc.id)" x-text="loc.name"></option>
                    </template>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="qe-sort">Reihenfolge im Rundgang</label>
                <input class="input" id="qe-sort" type="number" x-model="editDraft.sort_order">
                <p class="form-hint">Niedrigere Zahl = weiter oben; gleiche Zahl = alphabetisch nach Name.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="qe-min">Mindestbestand (optional)</label>
                <input class="input" id="qe-min" type="number" x-model="editDraft.min_stock">
            </div>
            <div class="form-group">
                <label class="form-label" for="qe-max">Maximalbestand (optional)</label>
                <input class="input" id="qe-max" type="number" x-model="editDraft.max_stock">
            </div>

            <h3 class="section-header" style="margin-bottom:0">Lieferanten &amp; Priorität</h3>
            <p class="form-hint text-muted" style="margin-top:0">Höhere Zahl = bevorzugt bei mehreren Lieferanten am Zieltag.</p>
            <template x-for="(row, idx) in editSupplierRows" :key="idx">
                <div class="form-row item-quick-edit__supplier-row">
                    <div class="form-group" style="flex:1;min-width:0">
                        <label class="form-label" :for="'qe-sup-' + idx">Lieferant</label>
                        <select class="select" :id="'qe-sup-' + idx" x-model="row.supplier_id">
                            <option value="">—</option>
                            <template x-for="s in suppliers.filter(x => x.active)" :key="s.id">
                                <option :value="String(s.id)" x-text="s.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="form-group" style="width:6rem;flex-shrink:0">
                        <label class="form-label" :for="'qe-prio-' + idx">Priorität</label>
                        <input class="input" :id="'qe-prio-' + idx" type="number" x-model.number="row.priority">
                    </div>
                    <button type="button" class="button button--ghost button--small item-quick-edit__remove-row"
                            x-show="editSupplierRows.length > 1"
                            @click="removeEditSupplierRow(idx)"
                            title="Zeile entfernen">&times;</button>
                </div>
            </template>
            <button type="button" class="button button--ghost button--small" @click="addEditSupplierRow()">+ Lieferant</button>

            <label class="checkbox">
                <input type="checkbox" x-model="editDraft.active">
                aktiv
            </label>

            <div class="item-quick-edit__actions">
                <button type="button" class="button button--ghost" @click="closeQuickEdit()" :disabled="editSaving">Abbrechen</button>
                <button type="submit" class="button button--primary" :disabled="editSaving">
                    <span x-show="!editSaving">Speichern</span>
                    <span x-show="editSaving">…</span>
                </button>
            </div>
        </form>
    </dialog>
</section>
