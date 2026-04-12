<section class="page-section" x-data="preparePage()">
    <?php $order_step = 1;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <h1 class="page-title">Bestellung vorbereiten</h1>
    <p class="text-muted">Wunsch-Lieferdatum wählen (z.&nbsp;B. Montag für OGA/Pütz). Lieferanten mit anderen Liefertagen erhalten automatisch ihr nächstmögliches Datum.</p>
    <p class="text-muted">Hinweis: <strong>Bestellrunde laden</strong> startet die Runde neu und löscht alle bisherigen lokalen Bestelleingaben. Eine laufende Runde setzen Sie über <a href="/">Start</a> fort (Rundgang / Kontrolle), nicht über erneutes Laden hier.</p>

    <div class="card card--pad form-stack">
        <div class="form-group">
            <label class="form-label" for="target_date">Ziel-Datum</label>
            <input class="input" type="date" id="target_date" x-model="targetDate">
        </div>
        <button type="button" class="button button--primary" @click="loadRound()" :disabled="loading">
            <span x-show="!loading">Bestellrunde laden</span>
            <span x-show="loading">Lade…</span>
        </button>
        <p class="toast toast--error" x-show="error" x-text="error"></p>
    </div>

    <template x-if="suppliersWithDates.length">
        <div class="card card--pad">
            <h2 class="section-header">Liefertermine dieser Runde</h2>
            <ul class="card-list">
                <template x-for="s in suppliersWithDates" :key="s.id">
                    <li class="list-item">
                        <div class="list-item__main">
                            <strong x-text="s.name"></strong>
                            <span class="text-muted" x-text="s.order_type === 'webshop' ? 'Webshop' : 'E-Mail'"></span>
                        </div>
                        <template x-if="s.deliveryDate">
                            <span class="delivery-badge" x-text="'Lieferung ' + formatDate(s.deliveryDate)"></span>
                        </template>
                        <template x-if="!s.deliveryDate">
                            <span class="status-badge status-badge--warn">Kein Liefertag</span>
                        </template>
                    </li>
                </template>
            </ul>
        </div>
    </template>
</section>
