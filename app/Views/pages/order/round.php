<section class="page-section" x-data="roundPage()">
    <div class="page-toolbar">
        <h1 class="page-title">Rundgang</h1>
        <span class="status-badge status-badge--neutral">offline nutzbar</span>
    </div>
    <p class="text-muted">Eingaben werden lokal gespeichert. Leere Felder = keine Bestellung.</p>

    <div class="tab-bar" role="tablist">
        <template x-for="loc in locations" :key="loc.id">
            <button type="button" class="tab-bar__btn" role="tab"
                    :class="{ 'tab-bar__btn--active': activeLocId === loc.id }"
                    @click="activeLocId = loc.id" x-text="loc.name"></button>
        </template>
    </div>

    <ul class="card-list">
        <template x-for="it in items" :key="it.id">
            <li class="card card--pad list-item list-item--round">
                <div class="list-item__main">
                    <strong x-text="it.name"></strong>
                    <span class="text-muted" x-text="it.unit"></span>
                </div>
                <input class="input input--qty" type="text" inputmode="decimal"
                       :value="quantities[it.id] || ''"
                       @blur="onQtyBlur(it.id, $event)"
                       :aria-label="'Menge ' + it.name">
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
