<?php
$isEdit = !empty($user['id']);
$uname = (string) ($user['username'] ?? '');
$email = (string) ($user['email'] ?? '');
$role = (string) ($user['role'] ?? \App\Helpers\UserRole::ORDER);
?>
<section class="page-section">
    <h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/admin/users/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label" for="username">Benutzername</label>
            <?php if ($isEdit): ?>
                <input class="input" id="username" name="username" readonly value="<?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?>">
                <p class="form-hint">Benutzername kann nicht geändert werden.</p>
            <?php else: ?>
                <input class="input" id="username" name="username" required autocomplete="username"
                       value="<?= htmlspecialchars($uname, ENT_QUOTES, 'UTF-8') ?>"
                       pattern="[a-zA-Z0-9._\-]{2,64}"
                       title="2–64 Zeichen: Buchstaben, Ziffern, . _ -">
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="email">E-Mail (optional)</label>
            <input class="input" type="email" id="email" name="email" autocomplete="email"
                   value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="role">Rolle</label>
            <select class="input" id="role" name="role" required>
                <?php foreach ($roleLabels as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $role === $val ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <fieldset class="form-fieldset">
            <legend class="form-legend">Passwort</legend>
            <?php if ($isEdit): ?>
                <p class="form-hint" style="margin-top:0">Leer lassen, um das bestehende Passwort beizubehalten.</p>
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label" for="password"><?= $isEdit ? 'Neues Passwort' : 'Passwort' ?></label>
                <input class="input" type="password" id="password" name="password" autocomplete="new-password"
                       <?= $isEdit ? '' : 'required' ?> minlength="8">
            </div>
            <div class="form-group">
                <label class="form-label" for="password_confirm">Passwort wiederholen</label>
                <input class="input" type="password" id="password_confirm" name="password_confirm" autocomplete="new-password"
                       <?= $isEdit ? '' : 'required' ?> minlength="8">
            </div>
        </fieldset>
        <div class="form-actions">
            <button type="submit" class="button button--primary">Speichern</button>
            <a href="/admin/users" class="button button--ghost">Abbrechen</a>
        </div>
    </form>
</section>
