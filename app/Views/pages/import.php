<section class="page-section">
    <h1 class="page-title">CSV-Import</h1>
    <p class="text-muted">UTF-8, Semikolon. Kopfzeilen exakt wie in der technischen Spezifikation.</p>

    <?php if (!empty($done)): ?>
        <p class="toast toast--success">
            Import abgeschlossen. Neu: <?= (int) $done['inserted'] ?>,
            Aktualisiert: <?= (int) $done['updated'] ?>.
        </p>
    <?php endif; ?>

    <?php if (!empty($result)): ?>
        <?php if (!$result['ok']): ?>
            <div class="card card--pad">
                <h2 class="section-header">Validierungsfehler</h2>
                <ul class="error-list">
                    <?php foreach ($result['errors'] as $e): ?>
                        <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="card card--pad">
                <h2 class="section-header">Vorschau (<?= count($result['preview']) ?> Zeilen)</h2>
                <p class="text-muted">Erste Einträge werden importiert. Bitte bestätigen.</p>
                <form method="post" action="/import/run">
                    <?= \App\Helpers\Csrf::field() ?>
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
                <option value="item_supplier" <?= ($import_type ?? '') === 'item_supplier' ? 'selected' : '' ?>>Artikel–Lieferant</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="csv">CSV-Datei</label>
            <input class="input" type="file" id="csv" name="csv" accept=".csv,.txt" required>
        </div>
        <button type="submit" class="button button--secondary">Vorschau &amp; Prüfung</button>
    </form>
</section>
