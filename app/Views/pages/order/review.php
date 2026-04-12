<section class="page-section" x-data="reviewPage()">
    <h1 class="page-title">Kontrolle</h1>
    <p class="text-muted">Nur bestellte Positionen. Lieferant kann angepasst werden, sofern der Artikel dort angelegt ist.</p>

    <template x-if="loading">
        <p>Lade…</p>
    </template>

    <template x-if="!loading && problemLines.length">
        <div class="card card--pad">
            <h2 class="section-header">Problemartikel</h2>
            <ul class="error-list">
                <template x-for="(p, idx) in problemLines" :key="idx">
                    <li>
                        <strong x-text="p.itemLabel"></strong>
                        <span class="text-muted" x-text="p.reason"></span>
                    </li>
                </template>
            </ul>
        </div>
    </template>

    <template x-for="g in groups" :key="g.supplierId">
        <div class="card card--pad">
            <div class="page-toolbar">
                <div>
                    <h2 class="section-header" x-text="g.supplier?.name"></h2>
                    <span class="text-muted" x-text="g.supplier?.order_type"></span>
                </div>
                <button type="button" class="button button--small button--ghost" @click="addFreeToSupplier(g.supplierId)">+ Frei</button>
            </div>

            <ul class="card-list">
                <template x-for="line in g.lines" :key="line.entryId">
                    <li class="list-item list-item--stack">
                        <div class="list-item__row">
                            <span class="grow" x-text="line.label"></span>
                            <input class="input input--qty" type="text" :value="line.quantity"
                                   @change="updateQty(line, $event)">
                            <span class="text-muted" x-text="line.unit"></span>
                            <button type="button" class="button button--small button--ghost" @click="removeLine(line)">Entfernen</button>
                        </div>
                        <template x-if="line.candidates.length > 1">
                            <div class="form-group">
                                <label class="form-label">Lieferant</label>
                                <select class="select" :value="String(line.supplierId)"
                                        @change="onSupplierChange(line, $event.target.value)">
                                    <template x-for="s in supplierOptions(line)" :key="s.id">
                                        <option :value="String(s.id)" x-text="s.name"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </li>
                </template>
            </ul>

            <div class="form-group">
                <label class="form-label">Zusatz für diesen Lieferanten</label>
                <textarea class="textarea" rows="3" x-model="g.note"
                          @blur="saveNote(g.supplierId, g.note)"></textarea>
            </div>
        </div>
    </template>

    <div class="button-stack">
        <button type="button" class="button button--primary button--block" @click="goOutput()">Weiter zur Ausgabe</button>
        <a href="/order/round" class="button button--ghost button--block">Zurück zum Rundgang</a>
    </div>
</section>
