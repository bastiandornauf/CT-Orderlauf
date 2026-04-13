<section class="page-section">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_err'])): ?>
        <?php $flashErr = $_SESSION['flash_err'];
        unset($_SESSION['flash_err']); ?>
        <p class="toast toast--error"><?= htmlspecialchars((string) $flashErr, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Benutzer</h1>
        <div class="toolbar-actions">
            <a href="/admin/users/new" class="button button--primary button--small">Neu</a>
        </div>
    </div>
    <p class="text-muted">Administrator: Nutzer anlegen und Rollen vergeben. <strong>Stammdaten</strong> bearbeiten Lagerorte, Lieferanten und Artikel. <strong>Nur Bestellen</strong> sieht den Bestellablauf ohne Stammdaten-Menü.</p>
    <?php if (empty($users)): ?>
        <div class="card card--pad">
            <p class="text-muted" style="margin:0">Noch keine Benutzer außer dem System.</p>
        </div>
    <?php else: ?>
    <ul class="card-list">
        <?php foreach ($users as $u): ?>
            <li class="card card--pad list-item">
                <div class="list-item__main">
                    <strong><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="text-muted"><?= htmlspecialchars(\App\Helpers\UserRole::label($u['role']), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!empty($u['email'])): ?>
                        <span class="text-muted"><?= htmlspecialchars((string) $u['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                </div>
                <div class="list-item__actions">
                    <a href="/admin/users/edit?id=<?= (int) $u['id'] ?>" class="button button--ghost button--small">Bearbeiten</a>
                    <?php if ((int) $u['id'] !== (int) ($currentUserId ?? 0)): ?>
                    <form method="post" action="/admin/users/delete" class="inline-form"
                          onsubmit="return confirm('Benutzer wirklich löschen?');">
                        <?= \App\Helpers\Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="button button--ghost button--small" style="color:var(--danger,#b00020)">Löschen</button>
                    </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
