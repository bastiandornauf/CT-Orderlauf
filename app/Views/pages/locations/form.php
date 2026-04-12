<section class="page-section">
    <h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/locations/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <?php if (!empty($location['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $location['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input class="input" id="name" name="name" required value="<?= htmlspecialchars($location['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="sort_order">Sortierung</label>
            <input class="input" type="number" id="sort_order" name="sort_order"
                   value="<?= htmlspecialchars((string) ($location['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="active" <?= !isset($location['active']) || (int) $location['active'] ? 'checked' : '' ?>>
            aktiv
        </label>
        <button type="submit" class="button button--primary">Speichern</button>
    </form>
</section>
