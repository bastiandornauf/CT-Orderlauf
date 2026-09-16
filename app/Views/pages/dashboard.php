<section class="page-section dashboard" x-data="dashboardPage">
    <?php $order_step = 1;
    require __DIR__ . '/../partials/order-stepper.php'; ?>
    <h1 class="visually-hidden">Start</h1>

    <div class="card card--pad dashboard-order-hero" x-show="initialized && roundInProgress" x-cloak>
        <h2 class="section-header">Laufende Bestellung</h2>
        <p class="dashboard-order-hero__status" x-text="roundStatusLabel"></p>

        <div class="button-stack dashboard-order-hero__actions">
            <template x-for="(action, idx) in roundActions" :key="action.href">
                <a :href="action.href"
                   class="button button--block"
                   :class="idx === 0 ? 'button--primary' : 'button--secondary'"
                   x-text="action.label"></a>
            </template>
        </div>
    </div>

    <div class="card card--pad dashboard-order-hero" x-show="initialized && hasRound && roundStatus === 'finalized'" x-cloak>
        <h2 class="section-header">Letzte Bestellung abgeschlossen</h2>
        <p class="dashboard-order-hero__status">Nur lokal auf diesem Gerät – kein Versandnachweis.</p>
        <div class="button-stack dashboard-order-hero__actions">
            <a href="/order/output" class="button button--secondary button--block">Zum Versand</a>
        </div>
    </div>

    <div id="bestellen" class="card card--pad dashboard-prepare">
        <h2 class="section-header">Neue Bestellung</h2>

        <div class="form-stack">
            <div class="form-group">
                <label class="form-label" for="target_date">Wunsch-Lieferdatum</label>
                <input class="input" type="date" id="target_date" name="target_date"
                       value="<?= htmlspecialchars($default_target_date ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       x-model="targetDate">
            </div>

            <button type="button" class="button button--primary button--block" @click="loadRound()" :disabled="loading">
                <span class="button__label" x-text="loading ? 'Einen Moment …' : 'Bestellung beginnen'">Bestellung beginnen</span>
            </button>

            <p class="toast toast--warn" x-show="initialized && roundInProgress" x-cloak>
                Überschreibt die laufende Bestellung. Zum Weiterarbeiten oben fortsetzen.
            </p>
            <p class="toast toast--error" x-show="error && String(error).trim()" x-text="error" x-cloak></p>

            <div class="delivery-preview" x-show="initialized" x-cloak>
                <p class="text-muted" x-show="previewOffline">Liefertermine nur mit Verbindung.</p>
                <p class="text-muted" x-show="previewLoading && !previewOffline">Lade Liefertermine …</p>
                <p class="toast toast--error" x-show="previewError && String(previewError).trim()" x-text="previewError"></p>

                <details class="delivery-preview__details" x-show="deliveryPreview.length && !previewLoading">
                    <summary class="delivery-preview__summary" x-text="deliverySummary"></summary>
                    <ul class="card-list">
                        <template x-for="s in deliveryPreview" :key="s.id">
                            <li class="list-item" :class="s.onTarget ? 'list-item--delivery-on-target' : ''">
                                <div class="list-item__main">
                                    <strong x-text="s.name"></strong>
                                    <span class="text-muted" x-text="s.order_type === 'webshop' ? 'Webshop' : 'E-Mail'"></span>
                                </div>
                                <template x-if="s.onTarget">
                                    <span class="status-badge status-badge--ok" x-text="formatDate(s.deliveryDate)"></span>
                                </template>
                                <template x-if="s.deliveryDate && !s.onTarget">
                                    <span class="delivery-badge" x-text="'erst ' + formatDate(s.deliveryDate)"></span>
                                </template>
                                <template x-if="!s.deliveryDate">
                                    <span class="status-badge status-badge--warn">Kein Liefertag</span>
                                </template>
                            </li>
                        </template>
                    </ul>
                </details>
            </div>
        </div>
    </div>

    <div class="card card--pad dashboard-elsewhere">
        <h2 class="section-header">Weitere Bereiche</h2>
        <ul class="dashboard-quick__list">
            <li><a href="/inventory" class="dashboard-quick__link">Inventur</a></li>
            <li><a href="/help" class="dashboard-quick__link">Hilfe</a></li>
        </ul>
    </div>

    <?php if (!empty($canEditMaster)): ?>
    <details class="dashboard-stammdaten card card--pad">
        <summary class="dashboard-stammdaten__summary">Stammdaten pflegen</summary>
        <ul class="dashboard-quick__list">
            <li><a href="/items" class="dashboard-quick__link">Artikel <span class="dashboard-quick__count"><?= (int) ($counts['items'] ?? 0) ?></span></a></li>
            <li><a href="/items/pending" class="dashboard-quick__link">Artikel-Vorschläge</a></li>
            <li><a href="/suppliers" class="dashboard-quick__link">Lieferanten <span class="dashboard-quick__count"><?= (int) ($counts['suppliers'] ?? 0) ?></span></a></li>
            <li><a href="/locations" class="dashboard-quick__link">Lagerorte <span class="dashboard-quick__count"><?= (int) ($counts['locations'] ?? 0) ?></span></a></li>
            <li><a href="/import" class="dashboard-quick__link">Import / Export</a></li>
        </ul>
    </details>
    <?php endif; ?>
</section>
