<?php

use App\Helpers\UserRole;

$canEditMaster = UserRole::canEditMasterData((string) ($_SESSION['role'] ?? ''));
?>
<section class="page-section" x-data="inventoryFinalizePage" data-can-edit-master="<?= $canEditMaster ? '1' : '0' ?>">
    <?php $inventory_step = 3;
    require __DIR__ . '/../../partials/inventory-stepper.php'; ?>
    <h1 class="page-title">Inventur – Abschluss</h1>

    <p class="toast toast--warn inventory-parallel-hint" x-show="orderRoundActive" x-cloak>
        Parallel läuft eine <strong>Bestellrunde</strong> – sie wird durch den Inventur-Export nicht verändert.
    </p>

    <p class="toast toast--ok" x-show="isLocked" x-cloak>
        Diese Inventur ist <strong>abgeschlossen</strong>. Zählungen können nicht mehr geändert werden. CSV erneut laden oder lokal beenden.
    </p>

    <p class="toast toast--error" role="alert" x-show="pageReady && pageError" x-text="pageError" x-cloak></p>
    <p class="toast toast--warn" x-show="pageReady && !session && !pageError" x-cloak>
        Keine Inventur geladen. <a href="/inventory">Zur Startseite</a>
    </p>

    <div class="card card--pad" x-show="pageReady && session">
        <h2 class="section-header" style="margin-top:0">Übersicht</h2>
        <p><strong x-text="session?.label"></strong></p>
        <p class="text-muted">Stichtag: <span x-text="formatDe(session?.stichtag)"></span></p>
        <ul class="inventory-finalize-stats">
            <li><span class="text-muted">Gezählt</span> <strong x-text="stats.counted"></strong></li>
            <li><span class="text-muted">Noch offen</span> <strong x-text="stats.open"></strong></li>
            <li x-show="stats.hasValue">
                <span class="text-muted">Gesamtwert (nur gezählt mit Preis)</span>
                <strong x-text="stats.totalValue.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'"></strong>
            </li>
        </ul>

        <details class="inventory-open-block" x-show="!isLocked && openItems.length > 0" open>
            <summary class="inventory-open-block__summary">
                Noch nicht gezählt (<span x-text="openItems.length"></span>)
            </summary>
            <p class="text-muted inventory-open-block__lead">
                Diese Artikel haben im Rundgang keinen Stand. In der CSV stehen sie mit Status <strong>offen</strong>.
                Noch etwas zählen? Unten „Zurück zum Rundgang“ – oder hier schnell als leer bestätigen.
            </p>
            <button type="button" class="button button--secondary button--block"
                    style="margin-bottom: var(--space-3);"
                    @click.prevent="markAllOpenAsZero()">
                Alle offenen als Leer / 0 erfassen
            </button>
            <ul class="card-list inventory-open-list">
                <template x-for="it in openItems" :key="it.id">
                    <li class="list-item inventory-open-list__row">
                        <div class="list-item__main">
                            <strong x-text="it.name"></strong>
                            <span class="text-muted" x-text="it.location_name + ' · ' + it.unit"></span>
                        </div>
                        <button type="button" class="button button--small button--secondary"
                                @click="markOpenAsZero(it.id)">
                            Leer / 0
                        </button>
                    </li>
                </template>
            </ul>
        </details>

        <div class="button-stack" style="margin-top: var(--space-4);">
            <button type="button" class="button button--primary button--block"
                    @click.prevent="downloadExport()" :disabled="exporting">
                <span class="button__label"
                      x-text="exporting ? 'Export …' : (isLocked ? 'CSV erneut herunterladen' : 'CSV herunterladen & abschließen')">CSV herunterladen &amp; abschließen</span>
            </button>
            <a class="button button--secondary button--block"
               x-show="csvFallbackUrl"
               :href="csvFallbackUrl"
               :download="csvFallbackName"
               @click="setTimeout(() => revokeCsvFallback(), 3000)">
                CSV-Datei speichern (Link tippen)
            </a>
            <a href="/inventory/round" class="button button--secondary button--block"
               x-show="!isLocked">
                Zurück zum Rundgang
            </a>
            <button type="button" class="button button--ghost button--block"
                    x-show="isLocked"
                    @click.prevent="finishAndClear()"
                    :disabled="clearing">
                <span class="button__label"
                      x-text="clearing ? 'Lösche …' : 'Inventur beenden (lokal löschen)'">Inventur beenden (lokal löschen)</span>
            </button>
        </div>
        <p class="form-hint text-muted" style="margin-top: var(--space-3);">
            Die CSV enthält <strong>alle</strong> aktiven Artikel mit Spalte <code>status</code>
            (<code>offen</code>, <code>gezaehlt_0</code>, <code>gezaehlt</code>, <code>frei_gezaehlt</code>). Semikolon, UTF-8 für Excel DE.
        </p>
    </div>

    <div class="card card--pad" x-show="pageReady && hasNewItems" x-cloak>
        <h2 class="section-header" style="margin-top:0">Neue Artikel sammeln</h2>
        <p class="text-muted">
            Es gibt <strong x-text="newItemCount"></strong> per Freitext erfasste Position(en) aus Inventur/Bestellung,
            die noch nicht im Stamm sind.
        </p>
        <a href="/items/pending" class="button button--secondary button--block">Zur Sammelliste „Neue Artikel"</a>
    </div>
</section>
