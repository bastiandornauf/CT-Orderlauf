<?php if (!empty($_SESSION['user_id'])): ?>
<footer class="app-footer">
    <form method="post" action="/logout" class="app-footer__form">
        <?= \App\Helpers\Csrf::field() ?>
        <button type="submit" class="button button--ghost button--small">Abmelden</button>
    </form>
</footer>
<?php endif; ?>
