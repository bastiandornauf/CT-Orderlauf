<section class="page-section">
    <h1 class="page-title">Import</h1>

    <?php if (!empty($done)): ?>
        <p class="toast toast--success">
            Fertig. Neu: <?= (int) $done['inserted'] ?>,
            aktualisiert: <?= (int) $done['updated'] ?>.
            <?php if (!empty($done['deactivated'])): ?>
                Inaktiv gesetzt: <?= (int) $done['deactivated'] ?>.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($result)): ?>
        <?php if (!$result['ok']): ?>
            <div class="card card--pad">
                <h2 class="section-header">Fehler in der Datei</h2>
                <ul class="error-list">
                    <?php foreach ($result['errors'] as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="card card--pad">
                <h2 class="section-header">Vorschau (<?= count($result['preview']) ?> Zeilen)</h2>
                <?php $missing = $result['items_not_in_csv'] ?? []; ?>
                <form method="post" action="/import/run" class="form-stack">
                    <?= \App\Helpers\Csrf::field() ?>
                    <?php if ($import_type === 'items' && !empty($missing)): ?>
                        <p class="text-muted">
                            <?= count($missing) ?> aktive Artikel fehlen in der CSV.
                        </p>
                        <ul class="text-muted import-missing-list">
                            <?php foreach ($missing as $m): ?>
                                <li>ID <?= (int) $m['id'] ?> — <?= htmlspecialchars((string) $m['name'], ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <label class="checkbox-label">
                            <input type="checkbox" name="deactivate_missing_items" value="1">
                            Fehlende Artikel auf inaktiv setzen
                        </label>
                    <?php endif; ?>
                    <button type="submit" class="button button--primary">Import ausführen</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post" action="/import/preview" enctype="multipart/form-data" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="import_type">Datentyp</label>
            <select class="select" id="import_type" name="import_type" required>
                <option value="locations" <?= ($import_type ?? '') === 'locations' ? 'selected' : '' ?>>Lagerorte</option>
                <option value="suppliers" <?= ($import_type ?? '') === 'suppliers' ? 'selected' : '' ?>>Lieferanten</option>
                <option value="delivery_days" <?= ($import_type ?? '') === 'delivery_days' ? 'selected' : '' ?>>Liefertage</option>
                <option value="items" <?= ($import_type ?? '') === 'items' ? 'selected' : '' ?>>Artikel</option>
                <option value="item_prices" <?= ($import_type ?? '') === 'item_prices' ? 'selected' : '' ?>>Bewertungspreise</option>
                <option value="item_supplier" <?= ($import_type ?? '') === 'item_supplier' ? 'selected' : '' ?>>Artikel–Lieferant</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="csv">CSV-Datei</label>
            <input class="input" type="file" id="csv" name="csv" accept=".csv,.txt" required>
        </div>
        <button type="submit" class="button button--primary">Vorschau</button>
    </form>

    <details class="card card--pad import-format">
        <summary class="import-format__summary">CSV-Format</summary>
        <p class="text-muted">UTF-8, Semikolon. Ein Export aus dieser App ist direkt wieder importierbar.</p>
        <p class="text-muted">Artikel mit <code>id</code> werden aktualisiert, leere ID legt neu an. Spalte <code>bewertungspreis</code> für die Inventur.</p>
        <p class="text-muted">Nur Preise: auf der Artikelliste <strong>↓ Preise</strong>, hier Typ <strong>Bewertungspreise</strong>.</p>
    </details>
</section>
