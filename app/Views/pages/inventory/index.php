<section class="page-section" x-data="inventoryHomePage">
    <?php $inventory_step = 1;
    require __DIR__ . '/../../partials/inventory-stepper.php'; ?>
    <h1 class="page-title">Inventur</h1>
    <p class="page-lead page-lead--compact">Bestände zählen, CSV für Excel – getrennt von der Bestellrunde, offline im Rundgang möglich.</p>

    <p class="toast toast--warn" x-show="!initialized" x-cloak role="status">
        Seite wird vorbereitet …
    </p>
    <p class="toast toast--error" role="alert" x-show="initialized && invError && String(invError).trim()" x-text="invError" x-cloak></p>

    <div class="card card--pad dashboard-order-hero">
        <h2 class="section-header" style="margin-top:0">Aktuelle Inventur</h2>
        <p class="dashboard-order-hero__status" x-show="initialized" x-cloak>
            <span x-show="hasInventory && inventoryStatusLabel" x-text="inventoryStatusLabel"></span>
            <span x-show="!hasInventory">Keine aktive Inventur. Unten <strong>Neue Inventur starten</strong>.</span>
        </p>
        <p class="toast toast--warn inventory-parallel-hint" x-show="initialized && orderRoundActive && hasInventory" x-cloak style="margin-top: var(--space-2);">
            Parallel läuft eine <strong>Bestellrunde</strong> – Bestell- und Inventur-Daten bleiben getrennt.
        </p>

        <div class="button-stack dashboard-order-hero__actions">
            <a href="/inventory/round" class="button button--primary button--block"
               x-show="canContinue">Inventur fortsetzen (Rundgang)</a>
            <a href="/inventory/finalize" class="button button--secondary button--block"
               x-show="canGoFinalize && !isFinalized">Inventur abschließen</a>
            <a href="/inventory/finalize" class="button button--primary button--block"
               x-show="isFinalized">CSV herunterladen</a>
            <button type="button" class="button button--secondary button--block"
                    x-show="isFinalized"
                    @click.prevent="clearLocalInventory()" :disabled="clearing">
                <span class="button__label" x-text="clearing ? 'Beende …' : 'Lokale Inventur löschen'">Lokale Inventur löschen</span>
            </button>
        </div>
    </div>

    <div class="card card--pad dashboard-prepare">
        <h2 class="section-header" style="margin-top:0">Neue Inventur</h2>
        <p class="text-muted">Lädt aktive Artikel und Lagerorte vom Server. Zählungen werden nur lokal für die Inventur gespeichert.</p>
        <p class="text-muted"><strong>Hinweis:</strong> „Inventur starten“ ersetzt eine bestehende lokale Inventur vollständig (auch abgeschlossene).</p>
        <p class="text-muted" x-show="!hasInventory || inventoryStatus === 'finalized'">
            <strong>Inventur starten</strong> lädt eine neue Session vom Server.
        </p>
        <p class="text-muted" x-show="hasInventory && inventoryStatus !== 'finalized'">
            Es läuft bereits eine Inventur – zum Weitermachen oben <strong>Inventur fortsetzen</strong> nutzen, nicht erneut starten.
        </p>

        <div class="form-stack" style="margin-top: var(--space-4);">
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
                <span class="button__label" x-text="invLoading ? 'Lade…' : 'Inventur starten'">Inventur starten</span>
            </button>
        </div>
    </div>
</section>
