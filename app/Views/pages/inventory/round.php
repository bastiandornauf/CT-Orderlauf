<section class="page-section" x-data="inventoryRoundPage">
    <?php $inventory_step = 2;
    require __DIR__ . '/../../partials/inventory-stepper.php'; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Inventur – Rundgang</h1>
        <div class="toolbar-actions">
            <span class="status-badge status-badge--neutral">offline nutzbar</span>
            <button type="button" class="button button--primary button--small" @click="goFinalize()">Zum Abschluss →</button>
        </div>
    </div>

    <p class="inventory-progress text-muted" x-show="progress.total > 0">
        <span x-text="progress.counted + ' von ' + progress.total + ' gezählt'"></span>
        <span x-show="progress.open > 0"> · <span x-text="progress.open + ' offen'"></span></span>
    </p>

    <p class="toast toast--warn inventory-parallel-hint" x-show="orderRoundActive" x-cloak>
        Es läuft parallel eine <strong>Bestellrunde</strong>. Ihre Bestell-Mengen bleiben unverändert.
    </p>

    <p class="text-muted">Leeres Feld = noch nicht gezählt (ok – offene Artikel klären Sie im Abschluss). „Leer / 0“ = bewusst Bestand null.</p>

    <p class="toast toast--error" role="alert" x-show="pageReady && pageError" x-text="pageError" x-cloak></p>
    <p class="toast toast--warn" x-show="pageReady && !pageError && locations.length === 0 && allItems.length > 0" x-cloak>
        Lager-Tabs fehlen – Artikel werden unten trotzdem angezeigt.
    </p>

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
            <li class="card card--pad list-item list-item--round inventory-line"
                :class="{
                    'inventory-line--counted': isCounted(it.id) && !isCountedZero(it.id),
                    'inventory-line--counted-zero': isCountedZero(it.id),
                    'inventory-line--open': !isCounted(it.id)
                }">
                <div class="list-item__main">
                    <strong x-text="it.name"></strong>
                    <span class="inventory-unit" x-show="it.unit" x-text="it.unit"></span>
                    <span class="text-muted order-item-meta">
                        <span class="inventory-valuation-hint" x-show="valuationHint(it)" x-text="valuationHint(it)"></span>
                    </span>
                    <span class="inventory-status-badge status-badge"
                          :class="isCounted(it.id) ? 'status-badge--ok' : 'status-badge--neutral'"
                          x-text="isCountedZero(it.id) ? 'Gezählt: 0' : (isCounted(it.id) ? 'Gezählt' : 'Offen')"></span>
                </div>
                <div class="inventory-line__actions">
                    <button type="button"
                            class="button button--small button--secondary inventory-zero-btn"
                            :class="{ 'inventory-zero-btn--active': isCountedZero(it.id) }"
                            @click.prevent="markZero(it.id)">
                        <span class="button__label"
                              x-text="isCountedZero(it.id) ? '↩ Offen' : 'Leer / 0'">Leer / 0</span>
                    </button>
                    <div class="qty-stepper inventory-line__qty" x-show="!isCountedZero(it.id)">
                        <button type="button" class="qty-stepper__btn" @click="onQtyStep(it.id, -1)" aria-label="Minus"
                                :disabled="!isCounted(it.id) && !(parseFloat(displayQty[it.id]) > 0)">−</button>
                        <input class="input input--qty" type="text" inputmode="decimal"
                               :value="isCounted(it.id) ? (displayQty[it.id] || '') : (displayQty[it.id] || '')"
                               @blur="onQtyBlur(it.id, $event)"
                               :aria-label="'Menge ' + it.name"
                               placeholder="—">
                        <button type="button" class="qty-stepper__btn" @click="onQtyStep(it.id, 1)" aria-label="Plus">+</button>
                    </div>
                </div>
            </li>
        </template>
    </ul>

    <div class="card card--pad form-stack inventory-free" x-show="search === '' && activeLocId" x-cloak>
        <h2 class="section-header" style="margin-top:0">Artikel nicht im Bestand (dieser Lagerort)</h2>
        <p class="text-muted">Hier zählen Sie Artikel, die noch nicht im Stamm sind. Sie kommen in die CSV und in die Sammelliste „Neue Artikel".</p>

        <ul class="card-list inventory-free__list" x-show="freeItems.length > 0">
            <template x-for="fi in freeItems" :key="fi.id">
                <li class="list-item inventory-free__item">
                    <div class="list-item__main">
                        <strong x-text="fi.name"></strong>
                        <span class="inventory-unit" x-text="freeQtyLabel(fi)"></span>
                    </div>
                    <button type="button" class="button button--small button--ghost"
                            @click.prevent="deleteFreeItem(fi.id)" aria-label="Entfernen">Entfernen</button>
                </li>
            </template>
        </ul>

        <p class="toast toast--error" x-show="freeError" x-text="freeError" x-cloak style="margin:0"></p>
        <div class="form-group">
            <label class="form-label">Bezeichnung</label>
            <input class="input" x-model="freeLabel" placeholder="z. B. Tomatenmark">
        </div>
        <div class="form-group">
            <label class="form-label">Gebinde / Einheit</label>
            <input class="input" x-model="freeUnit" placeholder="z. B. gr. Dose">
        </div>
        <div class="form-group">
            <label class="form-label">Menge</label>
            <input class="input" x-model="freeQty" inputmode="decimal" placeholder="z. B. 3">
        </div>
        <button type="button" class="button button--secondary" @click="addInventoryFree()">Hinzufügen</button>
    </div>

    <div class="round-next-loc" x-show="search === '' && locations.length > 1">
        <button type="button" class="button button--secondary" @click="nextLocation()">
            Nächstes Lager: <span x-text="nextLocationLabel()"></span> →
        </button>
    </div>

    <p class="round-search__empty" x-show="pageReady && filteredItems.length === 0 && search !== ''">
        Kein Artikel gefunden für „<span x-text="search"></span>“
    </p>
    <p class="round-search__empty" x-show="pageReady && !pageError && filteredItems.length === 0 && search === '' && allItems.length === 0">
        Keine Artikel geladen.
        <a href="/inventory" class="button button--secondary button--small" style="margin-top: var(--space-3); display: inline-block;">
            Zur Inventur-Startseite
        </a>
    </p>
</section>
