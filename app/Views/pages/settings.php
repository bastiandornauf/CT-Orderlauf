<section class="page-section">
    <h1 class="page-title">Einstellungen</h1>
    <?php if (!empty($_GET['saved'])): ?>
        <p class="toast toast--success">Gespeichert.</p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/settings/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="app_name">App-Name</label>
            <input class="input" id="app_name" name="app_name"
                   value="<?= htmlspecialchars($app_name ?? 'CT-Orderlauf', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="order_cc_email">CC für Bestellmails (systemweit)</label>
            <input class="input" id="order_cc_email" name="order_cc_email" type="email"
                   value="<?= htmlspecialchars($order_cc_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="leer = kein CC">
        </div>
        <button type="submit" class="button button--primary">Speichern</button>
    </form>
</section>
