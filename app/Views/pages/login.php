<section class="page-section">
    <h1 class="page-title">Anmelden</h1>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/login" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="username">Benutzername</label>
            <input class="input" type="text" id="username" name="username" required autocomplete="username">
        </div>
        <div class="form-group">
            <label class="form-label" for="password">Passwort</label>
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="button button--primary button--block">Anmelden</button>
    </form>
</section>
