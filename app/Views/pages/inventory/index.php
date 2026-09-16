<section class="page-section" x-data="inventoryHomePage">
    <?php $inventory_step = 1;
    require __DIR__ . '/../../partials/inventory-stepper.php'; ?>
    <h1 class="page-title">Inventur</h1>

    <div class="card card--pad dashboard-order-hero" x-show="initialized && hasInventory" x-cloak>
        <h2 class="section-header">Aktuelle Inventur</h2>
        <p class="dashboard-order-hero__status" x-text="inventoryStatusLabel"></p>
        <p class="toast toast--warn inventory-parallel-hint" x-show="orderRoundActive" x-cloak>
            Parallel läuft eine Bestellung – die Daten bleiben getrennt.
        </p>

        <div class="button-stack dashboard-order-hero__actions">
            <a href="/inventory/round" class="button button--primary button--block"
               x-show="canContinue">Rundgang fortsetzen</a>
            <a href="/inventory/finalize" class="button button--secondary button--block"
               x-show="canGoFinalize && !isFinalized">Abschluss</a>
            <a href="/inventory/finalize" class="button button--primary button--block"
               x-show="isFinalized">CSV herunterladen</a>
            <button type="button" class="button button--secondary button--block"
                    x-show="isFinalized"
                    @click.prevent="clearLocalInventory()" :disabled="clearing">
                <span class="button__label" x-text="clearing ? 'Beende …' : 'Lokal löschen'">Lokal löschen</span>
            </button>
        </div>
    </div>

    <p class="toast toast--error" role="alert" x-show="initialized && invError && String(invError).trim()" x-text="invError" x-cloak></p>
    <p class="toast toast--warn" x-show="!initialized" x-cloak role="status">Seite wird vorbereitet …</p>

    <div class="card card--pad dashboard-prepare">
        <h2 class="section-header">Neue Inventur</h2>
        <div class="form-stack">
            <div class="form-group">
                <label class="form-label" for="inv_stichtag">Stichtag</label>
                <input class="input" type="date" id="inv_stichtag" x-model="invStichtag">
            </div>
            <div class="form-group">
                <label class="form-label" for="inv_label">Bezeichnung (optional)</label>
                <input class="input" type="text" id="inv_label" x-model="invLabel" placeholder="z.&nbsp;B. Inventur Juni 2026">
            </div>
            <button type="button" class="button button--primary button--block"
                    @click="loadInventory()"
                    :disabled="invLoading || (hasInventory && inventoryStatus !== 'finalized' && inventoryStatus !== 'idle')">
                <span class="button__label" x-text="invLoading ? 'Einen Moment …' : 'Inventur starten'">Inventur starten</span>
            </button>
            <p class="toast toast--warn" x-show="initialized && hasInventory && inventoryStatus !== 'finalized'" x-cloak>
                Überschreibt die laufende Inventur. Zum Weiterarbeiten oben fortsetzen.
            </p>
        </div>
    </div>
</section>
