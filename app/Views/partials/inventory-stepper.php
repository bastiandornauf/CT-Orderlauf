<?php
/** @var int $inventory_step 1–3 */
$step = isset($inventory_step) ? (int) $inventory_step : 0;
if ($step < 1 || $step > 3) {
    return;
}
$labels = ['Vorbereiten', 'Rundgang', 'Abschluss'];
$urls = [
    1 => '/inventory',
    2 => '/inventory/round',
    3 => '/inventory/finalize',
];
?>
<nav class="order-stepper order-stepper--inventory" aria-label="Inventurablauf">
    <ol class="order-stepper__list">
        <?php for ($i = 1; $i <= 3; $i++): ?>
            <?php
            $itemClass = 'order-stepper__item';
            if ($i === $step) {
                $itemClass .= ' order-stepper__item--active';
            }
            if ($i < $step) {
                $itemClass .= ' order-stepper__item--done';
            }
            $href = $urls[$i];
            $ariaCurrent = $i === $step ? ' aria-current="step"' : '';
            ?>
            <li class="order-stepper__cell">
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') ?>"<?= $ariaCurrent ?>>
                    <span class="order-stepper__num" aria-hidden="true"><?= $i ?></span>
                    <span class="order-stepper__label"><?= htmlspecialchars($labels[$i - 1], ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </li>
        <?php endfor; ?>
    </ol>
    <p class="order-stepper__hint text-muted">Getrennt von der Bestellrunde – lokale Daten nur für die Inventur.</p>
</nav>
