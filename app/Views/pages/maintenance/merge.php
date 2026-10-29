<?php
/** @var array $a @var array $b @var string $target */
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static function (array $i, string $f): string {
    return match ($f) {
        'location_id' => (string) $i['location_name'],
        'unit_factor' => $i['unit_factor'] !== null ? str_replace('.', ',', (string) (0 + $i['unit_factor'])) . ' ' . ($i['base_unit'] === 'stk' ? 'Stück' : $i['base_unit']) : '',
        'valuation_price' => $i['valuation_price'] !== null ? str_replace('.', ',', (string) $i['valuation_price']) . ' €' : '',
        default => (string) ($i[$f] ?? ''),
    };
};
$differing = array_filter(array_keys(\App\Services\ItemMergeService::FIELDS), static fn (string $f): bool => $fmt($a, $f) !== $fmt($b, $f));
$picks = [];
foreach ($differing as $f) {
    // Vorbelegung: Wert des Ziels, außer er ist leer
    $picks[$f] = $fmt(${$target}, $f) === '' ? ($target === 'a' ? 'b' : 'a') : $target;
}
$state = htmlspecialchars((string) json_encode(['target' => $target, 'picks' => $picks, 'touched' => new stdClass()]), ENT_QUOTES, 'UTF-8');
?>
<section class="page-section" data-state="<?= $state ?>"
         x-data="{ target: '', picks: {}, touched: {}, init() {
             Object.assign(this, JSON.parse(this.$el.dataset.state));
             this.$watch('target', (t) => { for (const f in this.picks) if (!this.touched[f]) this.picks[f] = t; });
         } }">
    <div class="page-toolbar">
        <h1 class="page-title">Artikel zusammenführen</h1>
        <a href="/pflege/dubletten" class="button button--ghost button--small">Abbrechen</a>
    </div>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error" role="alert"><?= $h($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/pflege/zusammenfuehren" class="form-stack">
        <?= \App\Helpers\Csrf::field() ?>
        <input type="hidden" name="a" value="<?= (int) $a['id'] ?>">
        <input type="hidden" name="b" value="<?= (int) $b['id'] ?>">

        <div class="merge-grid">
            <?php foreach (['a' => $a, 'b' => $b] as $side => $i): ?>
                <div class="card card--pad merge-col" :class="{ 'merge-col--target': target === '<?= $side ?>' }">
                    <label class="checkbox-label merge-col__target">
                        <input type="radio" name="target" value="<?= $side ?>" x-model="target">
                        <span x-text="target === '<?= $side ?>' ? 'bleibt erhalten' : 'wird stillgelegt'"></span>
                    </label>
                    <strong class="merge-col__name"><?= $h(\App\Helpers\ItemDisplay::name($i)) ?></strong>
                    <span class="text-muted">ID <?= (int) $i['id'] ?> · <?= $h($i['name']) ?></span>
                    <ul class="merge-col__links">
                        <?php foreach ($i['links'] as $l): ?>
                            <li><?= $h($l['supplier_name']) ?> (Prio <?= (int) $l['priority'] ?>)<?= $l['supplier_label'] ? ' · ' . $h($l['supplier_label']) : '' ?><?= $l['supplier_sku'] ? ' · Art.-Nr. ' . $h($l['supplier_sku']) : '' ?></li>
                        <?php endforeach; ?>
                        <?php if ($i['links'] === []): ?><li class="text-muted">kein Lieferant</li><?php endif; ?>
                    </ul>
                    <span class="text-muted"><?= (int) $i['price_count'] ?> Preise im Verlauf</span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($differing !== []): ?>
            <h2 class="section-header">Unterschiedliche Angaben – welcher Wert gilt?</h2>
            <div class="card card--pad merge-fields">
                <?php foreach ($differing as $f): ?>
                    <fieldset class="merge-field">
                        <legend class="form-label"><?= $h(\App\Services\ItemMergeService::FIELDS[$f]) ?></legend>
                        <?php foreach (['a' => $a, 'b' => $b] as $side => $i): ?>
                            <label class="checkbox-label">
                                <input type="radio" name="field[<?= $f ?>]" value="<?= $side ?>" x-model="picks['<?= $f ?>']" @change="touched['<?= $f ?>'] = true">
                                <?= $fmt($i, $f) === '' ? '<span class="text-muted">(leer)</span>' : $h($fmt($i, $f)) ?>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card card--pad merge-warning" role="note">
            <strong>Bitte prüfen – das lässt sich nicht per Knopf rückgängig machen.</strong>
            <ul>
                <li>Der stillgelegte Artikel wird inaktiv und erscheint nicht mehr im Rundgang. Gelöscht wird nichts.</li>
                <li>Lieferanten, Bestelldaten und alle Preise wandern zum bleibenden Artikel. Lieferanten, die bisher nur der stillgelegte Artikel hatte, bestellen weiter unter seinem bisherigen Namen.</li>
                <li>Laufende Bestellrunden und Inventuren auf den Geräten behalten bis zum Abschluss den alten Stand.</li>
                <li>Der Vorher-Stand beider Artikel wird protokolliert. Ein Fehlgriff lässt sich daraus von Hand wiederherstellen – oder der Artikel wird neu angelegt.</li>
            </ul>
            <label class="checkbox-label">
                <input type="checkbox" name="confirm" value="1" required>
                Geprüft – diese beiden Artikel sind dasselbe Produkt.
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="button button--primary">Zusammenführen</button>
            <a href="/pflege/dubletten" class="button button--ghost">Abbrechen</a>
        </div>
    </form>
</section>
