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

    <div class="tab-bar" role="tablist">
        <template x-for="loc in locations" :key="loc.id">
            <button type="button" class="tab-bar__btn" role="tab"
                    :class="{ 'tab-bar__btn--active': activeLocId === loc.id }"
                    @click="activeLocId = loc.id" x-text="tabLabel(loc)"></button>
        </template>
    </div>

    <ul class="card-list">
        <template x-for="it in items" :key="it.id">
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

    <div class="card card--pad form-stack">
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
                <template x-for="s in suppliers" :key="s.id">
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
