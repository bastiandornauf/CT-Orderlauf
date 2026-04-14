<section class="page-section" x-data="reviewPage()">
    <?php $order_step = 3;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Kontrolle</h1>
        <button type="button" class="button button--ghost button--small"
                :disabled="syncBusy || loading" @click="refreshStammdaten()">
            <span x-show="!syncBusy">↻ Aktualisieren</span>
            <span x-show="syncBusy">…</span>
        </button>
    </div>
    <p class="text-muted order-stammdaten-hint">Nur bestellte Positionen. Bei mehreren Lieferanten am gleichen Tag erscheinen Auswahlkacheln.</p>

    <template x-if="loading">
        <p class="text-muted">Lade…</p>
    </template>

    <!-- Problemartikel -->
    <template x-if="!loading && problemLines.length">
        <div class="card card--pad review-block review-block--problems">
            <h2 class="review-block__title">
                <span class="review-block__icon">⚠</span>
                Problemartikel
                <span class="review-block__count" x-text="problemLines.length"></span>
            </h2>
            <ul class="review-problem-list">
                <template x-for="(p, idx) in problemLines" :key="idx">
                    <li class="review-problem-list__item">
                        <strong x-text="p.itemLabel"></strong>
                        <span class="text-muted" x-text="p.reason"></span>
                    </li>
                </template>
            </ul>
        </div>
    </template>

    <!-- Freie Positionen ohne Lieferant -->
    <template x-if="!loading && pendingFreeLines.length">
        <div class="card card--pad review-block review-block--pending">
            <h2 class="review-block__title">
                <span class="review-block__icon">!</span>
                Freie Positionen – Lieferant wählen
                <span class="review-block__count" x-text="pendingFreeLines.length"></span>
            </h2>
            <ul class="card-list" style="margin-top: var(--space-3)">
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

    <!-- Lieferanten-Blöcke -->
    <template x-for="g in groups" :key="g.supplierId">
        <div class="card review-supplier-card">
            <!-- Lieferant-Header -->
            <div class="review-supplier-card__header">
                <div class="review-supplier-card__header-main">
                    <span class="review-supplier-card__name" x-text="g.supplier?.name"></span>
                    <div class="review-supplier-card__badges">
                        <span class="review-badge review-badge--type"
                              x-text="g.supplier?.order_type === 'mail' ? 'E-Mail' : g.supplier?.order_type === 'webshop' ? 'Webshop' : (g.supplier?.order_type || '')">
                        </span>
                        <template x-if="g.deliveryDate">
                            <span class="review-badge review-badge--date" x-text="'Lieferung ' + formatDate(g.deliveryDate)"></span>
                        </template>
                    </div>
                </div>
                <button type="button" class="button button--small button--ghost" @click="addFreeToSupplier(g.supplierId)">+ Frei</button>
            </div>

            <!-- Artikel-Zeilen -->
            <ul class="review-lines">
                <template x-for="line in g.lines" :key="line.entryId">
                    <li class="review-line">
                        <div class="review-line__row">
                            <div class="review-line__info">
                                <span class="review-line__name" x-text="line.label"></span>
                                <span class="review-line__meta text-muted" x-show="line.unit || line.stockHint">
                                    <span x-text="line.unit"></span><span x-show="line.stockHint" x-text="' · ' + line.stockHint"></span>
                                </span>
                            </div>
                            <div class="review-line__actions">
                                <input class="input input--qty" type="text" :value="line.quantity"
                                       @change="updateQty(line, $event)">
                                <button type="button" class="button button--small button--ghost review-line__remove"
                                        @click="removeLine(line)" aria-label="Entfernen">×</button>
                            </div>
                        </div>

                        <!-- Lieferanten-Auswahl bei Alternativen -->
                        <template x-if="line.candidates.length > 1">
                            <div class="review-supplier-choice">
                                <div class="review-supplier-choice__head">
                                    <span class="review-supplier-choice__title">Lieferant wählen</span>
                                    <span class="review-supplier-choice__badge">Alternativen</span>
                                </div>
                                <p class="review-supplier-choice__hint">Aktive Zuordnung antippen zum Wechseln.</p>
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

            <!-- Zusatznotiz -->
            <div class="review-supplier-card__note">
                <label class="form-label">Zusatz für diesen Lieferanten</label>
                <textarea class="textarea" rows="2" x-model="g.note"
                          @blur="saveNote(g.supplierId, g.note)"></textarea>
            </div>
        </div>
    </template>

    <div class="button-stack">
        <button type="button" class="button button--primary button--block"
                :disabled="pendingFreeLines.length > 0 || problemLines.length > 0"
                @click="goOutput()">Weiter zur Ausgabe</button>
        <p class="form-hint text-muted" style="margin:0" x-show="pendingFreeLines.length > 0 || problemLines.length > 0">
            Freie Positionen ohne Lieferant oder Problemartikel auflösen um fortzufahren.
        </p>
        <a href="/order/round" class="button button--ghost button--block">Zurück zum Rundgang</a>
    </div>
</section>
