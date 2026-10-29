<?php
/** @var array<string, list<array{key:string,title:string,severity:string,help:string,count:int}>> $groups */
?>
<section class="page-section">
    <div class="page-toolbar">
        <h1 class="page-title">Datenpflege</h1>
    </div>
    <p class="text-muted u-m-0 u-mb-2">
        <?= (int) $errors === 0 ? 'Keine Fehler.' : (int) $errors . ' Fehler zu beheben.' ?>
        Hinweise sind optionale Verbesserungen.
    </p>

    <?php foreach ($groups as $group => $checks): ?>
        <h2 class="section-header"><?= htmlspecialchars($group, ENT_QUOTES, 'UTF-8') ?></h2>
        <div class="maintenance-grid">
            <?php foreach ($checks as $c): ?>
                <?php $empty = $c['count'] === 0; ?>
                <a class="card card--pad maintenance-card maintenance-card--<?= $c['severity'] ?><?= $empty ? ' maintenance-card--done' : '' ?>"
                   href="<?= $empty ? '#' : '/items?active=1&check=' . urlencode($c['key']) ?>"
                   <?= $empty ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <span class="maintenance-card__count"><?= $empty ? '✓' : (int) $c['count'] ?></span>
                    <span class="maintenance-card__title"><?= htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="maintenance-card__help text-muted"><?= htmlspecialchars($c['help'], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
