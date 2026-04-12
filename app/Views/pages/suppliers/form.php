<section class="page-section">
    <h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/suppliers/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <?php if (!empty($supplier['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input class="input" id="name" name="name" required
                   value="<?= htmlspecialchars($supplier['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="email">E-Mail</label>
            <input class="input" id="email" name="email" type="email"
                   value="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="order_type">Bestelltyp</label>
            <select class="select" id="order_type" name="order_type">
                <?php $t = $supplier['order_type'] ?? 'mail'; ?>
                <option value="mail" <?= $t === 'mail' ? 'selected' : '' ?>>E-Mail</option>
                <option value="webshop" <?= $t === 'webshop' ? 'selected' : '' ?>>Webshop (nur Liste)</option>
            </select>
        </div>
        <fieldset class="form-fieldset">
            <legend class="form-legend">Liefertage (1=Mo … 7=So)</legend>
            <div class="checkbox-grid">
                <?php for ($d = 1; $d <= 7; $d++): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="weekdays[]" value="<?= $d ?>"
                            <?= in_array($d, $weekdays ?? [], true) ? 'checked' : '' ?>>
                        <?= $d ?>
                    </label>
                <?php endfor; ?>
            </div>
        </fieldset>
        <div class="form-group">
            <label class="form-label" for="email_template">E-Mail-Vorlage (optional)</label>
            <textarea class="textarea" id="email_template" name="email_template" rows="8"><?= htmlspecialchars($supplier['email_template'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="active" <?= !isset($supplier['active']) || (int) $supplier['active'] ? 'checked' : '' ?>>
            aktiv
        </label>
        <button type="submit" class="button button--primary">Speichern</button>
    </form>
</section>
