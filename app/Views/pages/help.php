<section class="page-section">
    <h1 class="page-title">Hilfe</h1>
    <?php if (!empty($manualMissing)): ?>
        <p class="toast toast--error">Die Anleitung konnte nicht geladen werden. Bitte <code>app/Data/ANLEITUNG.md</code> (oder <code>docs/ANLEITUNG.md</code>) auf den Server legen.</p>
    <?php else: ?>
        <p class="page-lead text-muted">Benutzeranleitung (Stand: Datei im Projektordner).</p>
        <article class="help-doc card card--pad">
            <?= $helpHtml ?>
        </article>
    <?php endif; ?>
</section>
