<section class="page-section" x-data="dashboardPage">
    <h1 class="page-title">Start</h1>
    <p class="page-lead page-lead--compact">Rundgang, Kontrolle, Ausgabe – alles lokal im Browser bis zum Versand.</p>

    <div class="card card--pad dashboard-order-hero">
        <h2 class="section-header" style="margin-top:0">Bestellrunde</h2>
        <p class="dashboard-order-hero__status" x-show="initialized" x-cloak>
            <span x-show="hasRound && roundStatusLabel" x-text="roundStatusLabel"></span>
            <span x-show="!hasRound">Keine aktive Runde. Zum Starten unten <strong>Neue Bestellrunde</strong> nutzen.</span>
        </p>

        <div class="button-stack dashboard-order-hero__actions">
            <a href="/order/round" class="button button--primary button--block" x-show="hasRound && (roundStatus === 'prepared' || roundStatus === 'active' || roundStatus === 'paused')">Rundgang fortsetzen</a>
            <a href="/order/review" class="button button--secondary button--block" x-show="hasRound && (roundStatus === 'ready_for_review' || roundStatus === 'active' || roundStatus === 'paused')">Kontrolle / Abschluss</a>
            <a href="/order/output" class="button button--secondary button--block" x-show="hasRound && roundStatus === 'ready_for_review'">Ausgabe</a>
        </div>
    </div>

    <div id="bestellen" class="card card--pad dashboard-prepare">
        <h2 class="section-header" style="margin-top:0">Neue Bestellrunde</h2>
        <p class="text-muted">Wunsch-Lieferdatum wählen (z.&nbsp;B. Montag für OGA/Pütz). Lieferanten mit anderen Liefertagen erhalten automatisch ihr nächstmögliches Datum.</p>
        <p class="text-muted"><strong>Bestellrunde laden</strong> startet eine <strong>neue</strong> Runde und löscht dabei alle bisherigen lokalen Eingaben dieser Runde. Laufende Runden setzen Sie mit <strong>Rundgang fortsetzen</strong> oder <strong>Kontrolle</strong> fort – nicht durch erneutes Laden.</p>

        <div class="form-stack" style="margin-top: var(--space-4);">
            <div class="form-group">
                <label class="form-label" for="target_date">Ziel-Datum</label>
                <input class="input" type="date" id="target_date" name="target_date"
                       value="<?= htmlspecialchars($default_target_date ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       x-model="targetDate">
            </div>
            <button type="button" class="button button--primary" @click="loadRound()" :disabled="loading">
                <span x-text="loading ? 'Lade…' : 'Bestellrunde laden'"></span>
            </button>
            <p class="toast toast--error" x-show="error" x-text="error"></p>
        </div>

        <template x-if="suppliersWithDates.length">
            <div style="margin-top: var(--space-4);">
                <h3 class="section-header">Liefertermine dieser Runde</h3>
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
    </div>

    <?php if (!empty($canEditMaster)): ?>
    <details class="dashboard-stammdaten card card--pad">
        <summary class="dashboard-stammdaten__summary">Stammdaten pflegen</summary>
        <p class="text-muted" style="margin-top:0">Artikel, Lieferanten, Lagerorte – für den Bestellablauf und die Ausgabe.</p>
        <ul class="dashboard-quick__list">
            <li><a href="/items" class="dashboard-quick__link">Artikel <span class="dashboard-quick__count"><?= (int) ($counts['items'] ?? 0) ?></span></a></li>
            <li><a href="/suppliers" class="dashboard-quick__link">Lieferanten <span class="dashboard-quick__count"><?= (int) ($counts['suppliers'] ?? 0) ?></span></a></li>
            <li><a href="/locations" class="dashboard-quick__link">Lagerorte <span class="dashboard-quick__count"><?= (int) ($counts['locations'] ?? 0) ?></span></a></li>
        </ul>
    </details>
    <?php endif; ?>

    <p class="text-muted dashboard-hint">Weitere Bereiche über das <strong>Menü</strong> oben.</p>
</section>
