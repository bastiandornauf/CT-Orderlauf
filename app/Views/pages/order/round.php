<section class="page-section" x-data="roundPage()">
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
                    @click="activeLocId = loc.id" x-text="tabLabel(loc)"></button>
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
                    <strong x-text="it.name"></strong>
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
</section>
