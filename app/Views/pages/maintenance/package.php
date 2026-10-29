<section class="page-section page-section--wide" x-data="packageImport()">
    <div class="page-toolbar">
        <h1 class="page-title">Pflegepaket einspielen</h1>
        <a href="/pflege" class="button button--ghost button--small">Zur Datenpflege</a>
    </div>

    <div class="card card--pad form-stack">
        <p class="u-m-0">
            Paket (JSON) wählen – z.&nbsp;B. von Claude aus Rechnungsfotos erstellt. Vor der Übernahme siehst du jede Änderung mit altem und neuem Wert.
        </p>
        <input type="file" accept="application/json,.json" @change="onFile($event)" aria-label="Pflegepaket wählen">
        <p class="form-hint u-m-0">
            Grundlage für ein neues Paket: <a href="/pflege/export">aktuellen Artikelstamm exportieren</a> (JSON mit Bestelldaten und letzten Preisen).
        </p>
    </div>

    <p class="toast toast--error" x-show="error" x-text="error" role="alert"></p>
    <p class="toast toast--success" x-show="result" x-text="result ? result.applied + ' übernommen, ' + result.skipped + ' waren schon so.' : ''"></p>

    <template x-if="rows.length > 0">
        <div>
            <h2 class="section-header" x-text="title || fileName"></h2>
            <p class="text-muted u-m-0 u-mb-2"
               x-text="`${counts.change} neu/geändert · ${counts.warn} prüfen · ${counts.same} schon so · ${counts.error} Fehler`"></p>
            <label class="checkbox-label u-mb-2">
                <input type="checkbox" @change="selectAll($event.target.checked)"> alle Änderungen auswählen
            </label>
            <div class="package-table-wrap">
                <table class="package-table">
                    <thead>
                        <tr><th></th><th>Artikel</th><th>Lieferant</th><th>Was</th><th>Bisher</th><th>Neu</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <template x-for="r in rows" :key="r.index">
                            <tr :class="'package-row--' + r.status">
                                <td>
                                    <input type="checkbox" x-model="selected[r.index]" :disabled="r.status === 'error' || r.status === 'same'"
                                           :aria-label="'Zeile ' + (r.index + 1) + ' übernehmen'">
                                </td>
                                <td x-text="r.item"></td>
                                <td x-text="r.supplier"></td>
                                <td x-text="r.field"></td>
                                <td class="text-muted" x-text="r.old || '—'"></td>
                                <td x-text="r.new"></td>
                                <td>
                                    <span class="status-badge" :class="{ 'status-badge--ok': r.status === 'change', 'status-badge--warn': r.status === 'warn' || r.status === 'error' }"
                                          x-text="statusLabel(r.status)"></span>
                                    <span class="package-msg" x-show="r.message" x-text="r.message"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="button" class="button button--primary" @click="apply()" :disabled="busy || selectedCount === 0"
                        x-text="busy ? '…' : selectedCount + ' Änderungen übernehmen'"></button>
            </div>
        </div>
    </template>
</section>
