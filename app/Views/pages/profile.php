<section class="page-section">
    <h1 class="page-title">Mein Konto</h1>
    <p class="text-muted">Angemeldet als <strong><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
    <?php if (!empty($_GET['saved'])): ?>
        <p class="toast toast--success">Gespeichert.</p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/profile/save" class="form-stack card card--pad" style="margin-bottom: var(--space-4);">
        <?= \App\Helpers\Csrf::field() ?>
        <fieldset class="form-fieldset">
            <legend class="form-legend">Profil</legend>
            <div class="form-group">
                <label class="form-label" for="display_name">Anzeigename (für Bestell-Mails)</label>
                <input class="input" type="text" id="display_name" name="display_name" maxlength="128"
                       value="<?= htmlspecialchars($display_name ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z. B. Bastian Dornauf">
                <p class="form-hint">Wird in E-Mail-Vorlagen als <code>{{USER}}</code> eingesetzt. Leer = Benutzername.</p>
            </div>
        </fieldset>
        <button type="submit" class="button button--secondary">Profil speichern</button>
    </form>

    <form method="post" action="/profile/password" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <fieldset class="form-fieldset">
            <legend class="form-legend">Passwort ändern</legend>
            <div class="form-group">
                <label class="form-label" for="current_password">Aktuelles Passwort</label>
                <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Neues Passwort</label>
                <input class="input" type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirm">Neues Passwort wiederholen</label>
                <input class="input" type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password" minlength="8">
            </div>
        </fieldset>
        <button type="submit" class="button button--primary">Passwort speichern</button>
    </form>
</section>
