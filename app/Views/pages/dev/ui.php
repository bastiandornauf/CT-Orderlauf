<?php
$swatches = [
    ['bg', '--color-bg', 'Hintergrund'],
    ['surface', '--color-surface', 'Fläche'],
    ['text', '--color-text', 'Text'],
    ['muted', '--color-muted', 'Nebentext'],
    ['accent', '--color-accent', 'Akzent'],
    ['danger', '--color-danger', 'Fehler'],
    ['warn', '--color-warn', 'Hinweis'],
    ['ok', '--color-ok', 'Ok'],
    ['border', '--color-border', 'Rahmen'],
];
?>
<section class="page-section ui-kit">
    <div class="page-toolbar page-toolbar--compact">
        <h1 class="page-title">Komponenten</h1>
    </div>
    <p class="text-muted u-m-0">Internes UI-Kit für Phase 3. Nicht im Menü, nur für Administratoren.</p>

    <div class="card card--pad stack">
        <h2 class="section-header">Farben</h2>
        <div class="ui-kit__swatch-row">
            <?php foreach ($swatches as [$key, $var, $label]): ?>
                <div class="ui-kit__swatch">
                    <div class="ui-kit__swatch-chip ui-kit__swatch-chip--<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"></div>
                    <p class="ui-kit__swatch-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><br><code><?= htmlspecialchars($var, ENT_QUOTES, 'UTF-8') ?></code></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card card--pad stack">
        <h2 class="section-header">Schrift</h2>
        <h1 class="page-title ui-kit__type">Seitentitel · 1.35rem</h1>
        <h2 class="section-header ui-kit__type">Abschnitt · 1.125rem</h2>
        <p class="ui-kit__type">Fließtext · 1rem, Zeilenabstand 1.45</p>
        <p class="text-muted ui-kit__type">Nebentext · 0.875rem</p>
        <p class="form-hint">Formularhinweis</p>
    </div>

    <div class="card card--pad stack">
        <h2 class="section-header">Buttons</h2>
        <div class="cluster">
            <button type="button" class="button button--primary">Primary</button>
            <button type="button" class="button button--secondary">Secondary</button>
            <button type="button" class="button button--ghost">Ghost</button>
            <button type="button" class="button button--mailto-urgent">Dringend</button>
            <button type="button" class="button button--ghost button--danger">Löschen</button>
            <button type="button" class="button button--primary button--small">Klein</button>
            <button type="button" class="button button--primary" disabled>Disabled</button>
        </div>
        <button type="button" class="button button--primary button--block">Volle Breite</button>
    </div>

    <div class="card card--pad stack">
        <h2 class="section-header">Status</h2>
        <div class="cluster">
            <span class="status-badge">Neutral</span>
            <span class="status-badge status-badge--ok">Ok</span>
            <span class="status-badge status-badge--warn">Hinweis</span>
            <span class="status-badge status-badge--offline">Offline</span>
        </div>
        <p class="toast toast--success">Gespeichert.</p>
        <p class="toast toast--warn">Überschreibt die laufende Bestellung.</p>
        <p class="toast toast--error">Versand fehlgeschlagen.</p>
    </div>

    <div class="card card--pad stack">
        <h2 class="section-header">Formulare</h2>
        <div class="form-stack">
            <div class="form-group">
                <label class="form-label" for="kit-input">Feld</label>
                <input class="input" id="kit-input" value="Beispiel">
                <p class="form-hint">Kurzer Hinweis unter dem Feld.</p>
            </div>
            <div class="form-row">
                <div class="form-group u-flex">
                    <label class="form-label" for="kit-select">Auswahl</label>
                    <select class="select" id="kit-select">
                        <option>OGA</option>
                        <option>Pütz</option>
                    </select>
                </div>
                <div class="form-group u-w-6rem">
                    <label class="form-label" for="kit-qty">Menge</label>
                    <input class="input" id="kit-qty" value="2">
                </div>
            </div>
            <label class="checkbox-label">
                <input type="checkbox" checked>
                Direktversand
            </label>
        </div>
    </div>

    <div class="card card--pad stack">
        <h2 class="section-header">Layout</h2>
        <p class="text-muted u-m-0"><code>.stack</code> senkrecht, <code>.cluster</code> in der Zeile.</p>
        <div class="cluster">
            <span class="status-badge status-badge--ok">Cluster</span>
            <span class="status-badge">nebeneinander</span>
            <span class="status-badge status-badge--warn">wrap</span>
        </div>
        <div class="stack stack--sm">
            <div class="card card--pad">Stack-Karte 1</div>
            <div class="card card--pad">Stack-Karte 2</div>
        </div>
    </div>

    <a href="/settings" class="button button--ghost button--block">Zu den Einstellungen</a>
</section>
