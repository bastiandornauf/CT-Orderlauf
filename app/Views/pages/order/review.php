<section class="page-section" x-data="reviewPage()">
    <?php $order_step = 3;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <h1 class="page-title">Kontrolle</h1>
    <p class="text-muted">Nur bestellte Positionen. Gibt es für einen Artikel mehrere Lieferanten am Bestelltag, erscheint eine <strong>auswählbare Lieferantenzeile</strong> – die erste Option entspricht der Priorität aus den Artikelstammdaten.</p>
    <p class="text-muted order-stammdaten-hint">
        Wenn Sie zwischendurch Lieferanten oder Einstellungen geändert haben: zurück auf diese Seite wechseln (Tab/Fokus) lädt die Daten neu – oder
        <button type="button" class="button button--ghost button--small order-stammdaten-hint__btn"
                :disabled="syncBusy || loading" @click="refreshStammdaten()">vom Server aktualisieren</button>
        <span x-show="syncBusy" class="text-muted"> …</span>
    </p>

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

    <template x-if="!loading && pendingFreeLines.length">
        <div class="card card--pad review-pending-free">
            <h2 class="section-header">Freie Positionen – Lieferant wählen</h2>
            <p class="text-muted">Diese Zeilen wurden im Rundgang ohne Lieferant erfasst („später in Kontrolle“). Bitte einen Lieferanten zuordnen.</p>
            <ul class="card-list">
                <template x-for="line in pendingFreeLines" :key="line.entryId">
                    <li class="list-item list-item--stack card card--pad">
                        <div class="list-item__row">
                            <span class="grow"><strong x-text="line.label"></strong></span>
                            <input class="input input--qty" type="text" :value="line.quantity"
                                   @change="updateQty(line, $event)">
                            <button type="button" class="button button--small button--ghost" @click="removeLine(line)">Entfernen</button>
                        </div>
                        <div class="form-group" style="margin-top: var(--space-2); margin-bottom: 0;">
                            <label class="form-label" :for="'pending-sup-' + line.entryId">Lieferant</label>
                            <select class="select" :id="'pending-sup-' + line.entryId"
                                    @change="assignPendingFreeSupplier(line, $event.target.value)">
                                <option value="">— Lieferant wählen —</option>
                                <template x-for="s in supplierOptions(line)" :key="s.id">
                                    <option :value="String(s.id)" x-text="s.label"></option>
                                </template>
                            </select>
                        </div>
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
                    <span class="text-muted">
                        <span x-text="(g.supplier?.order_type === 'mail' ? 'E-Mail' : g.supplier?.order_type === 'webshop' ? 'Webshop' : (g.supplier?.order_type || ''))"></span>
                        <template x-if="g.deliveryDate">
                            &nbsp;·&nbsp;<span class="delivery-badge" x-text="'Lieferung ' + formatDate(g.deliveryDate)"></span>
                        </template>
                    </span>
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
                            <span class="text-muted order-item-meta">
                                <span x-text="line.unit"></span><span class="order-stock-hint" x-show="line.stockHint" x-text="' · ' + line.stockHint"></span>
                            </span>
                            <button type="button" class="button button--small button--ghost" @click="removeLine(line)">Entfernen</button>
                        </div>
                        <template x-if="line.candidates.length > 1">
                            <div class="review-supplier-choice">
                                <div class="review-supplier-choice__head">
                                    <span class="review-supplier-choice__title">Lieferant wählen</span>
                                    <span class="review-supplier-choice__badge">Alternativen</span>
                                </div>
                                <p class="review-supplier-choice__hint">Zum Wechseln den gewünschten Lieferanten antippen. Die hervorgehobene Kachel ist die aktive Zuordnung für diese Position.</p>
                                <div class="review-supplier-choice__chips" role="group" :aria-label="'Lieferant für ' + line.label">
                                    <template x-for="(s, cidx) in supplierOptions(line)" :key="s.id">
                                        <button type="button"
                                                class="supplier-chip supplier-chip--choice"
                                                :class="{
                                                    'supplier-chip--choice-active': Number(line.supplierId) === Number(s.id),
                                                    'supplier-chip--choice-prio': cidx === 0
                                                }"
                                                :aria-pressed="Number(line.supplierId) === Number(s.id)"
                                                @click="onSupplierChange(line, String(s.id))">
                                            <span class="supplier-chip--choice__name" x-text="s.name"></span>
                                            <span class="supplier-chip--choice__date text-muted" x-show="s.dateLabel" x-text="s.dateLabel"></span>
                                            <span class="supplier-chip--choice__prio" x-show="cidx === 0">Priorität</span>
                                        </button>
                                    </template>
                                </div>
                                <div class="form-group review-supplier-choice__fallback">
                                    <label class="form-label" :for="'rev-sup-' + line.entryId">Oder per Liste</label>
                                    <select class="select" :id="'rev-sup-' + line.entryId"
                                            :value="String(line.supplierId)"
                                            @change="onSupplierChange(line, $event.target.value)">
                                        <template x-for="s in supplierOptions(line)" :key="s.id">
                                            <option :value="String(s.id)" x-text="s.label"></option>
                                        </template>
                                    </select>
                                </div>
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
        <button type="button" class="button button--primary button--block"
                :disabled="pendingFreeLines.length > 0 || problemLines.length > 0"
                @click="goOutput()">Weiter zur Ausgabe</button>
        <p class="form-hint text-muted" style="margin:0" x-show="pendingFreeLines.length > 0 || problemLines.length > 0">
            Solange freie Positionen ohne Lieferant oder Problemartikel bestehen, ist die Ausgabe gesperrt.
        </p>
        <a href="/order/round" class="button button--ghost button--block">Zurück zum Rundgang</a>
    </div>
</section>
