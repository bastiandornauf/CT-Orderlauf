<?php
/** @var list<array{a:array,b:array,score:float,reasons:list<string>,decision:?string}> $pairs */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$label = static fn (array $i): string => \App\Helpers\ItemDisplay::name($i);
?>
<section class="page-section">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <p class="toast toast--success" role="status"><?= $h($_SESSION['flash_ok']) ?></p>
        <?php unset($_SESSION['flash_ok']); ?>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Mögliche Dubletten</h1>
        <a href="/pflege" class="button button--ghost button--small">Zur Datenpflege</a>
    </div>
    <p class="text-muted u-m-0 u-mb-2">Nur Vorschläge – ob zwei Artikel gleich sind, entscheidest du.</p>

    <?php if ($pairs === []): ?>
        <div class="card card--pad"><p class="u-m-0">Keine Vorschläge.</p></div>
    <?php endif; ?>
    <ul class="card-list">
        <?php foreach ($pairs as $p): ?>
            <li class="card card--pad dupe-pair<?= $p['decision'] === 'later' ? ' dupe-pair--later' : '' ?>">
                <div class="dupe-pair__items">
                    <?php foreach (['a', 'b'] as $side): $i = $p[$side]; ?>
                        <div class="dupe-pair__item">
                            <strong><?= $h($label($i)) ?></strong>
                            <span class="text-muted"><?= $h($i['unit']) ?> · <?= $h($i['location']) ?></span>
                            <?php if (trim((string) $i['alias']) !== ''): ?>
                                <span class="text-muted dupe-pair__formal"><?= $h($i['name']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted dupe-pair__why">
                    Ähnlichkeit <?= (int) round($p['score'] * 100) ?> % · <?= $h(implode(' · ', $p['reasons'])) ?>
                    <?= $p['decision'] === 'later' ? ' · zurückgestellt' : '' ?>
                </p>
                <div class="dupe-pair__actions">
                    <a class="button button--primary button--small" href="/pflege/zusammenfuehren?a=<?= (int) $p['a']['id'] ?>&amp;b=<?= (int) $p['b']['id'] ?>">Vergleichen</a>
                    <?php foreach (['separate' => 'Getrennt lassen', 'later' => 'Später'] as $d => $text): ?>
                        <?php if ($d === 'later' && $p['decision'] === 'later') continue; ?>
                        <form method="post" action="/pflege/dubletten/entscheiden">
                            <?= \App\Helpers\Csrf::field() ?>
                            <input type="hidden" name="a" value="<?= (int) $p['a']['id'] ?>">
                            <input type="hidden" name="b" value="<?= (int) $p['b']['id'] ?>">
                            <input type="hidden" name="decision" value="<?= $d ?>">
                            <button type="submit" class="button button--ghost button--small"><?= $text ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
