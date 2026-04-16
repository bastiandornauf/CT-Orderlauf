<?php
/** @var int $order_step 1–4 */
$step = isset($order_step) ? (int) $order_step : 0;
if ($step < 1 || $step > 4) {
    return;
}
$labels = ['Vorbereiten', 'Rundgang', 'Kontrolle', 'Ausgabe'];
$urls = [
    1 => '/?open=bestellen',
    2 => '/order/round',
    3 => '/order/review',
    4 => '/order/output',
];
?>
<nav class="order-stepper" aria-label="Bestellablauf">
    <ol class="order-stepper__list">
        <?php for ($i = 1; $i <= 4; $i++): ?>
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
    <p class="order-stepper__hint text-muted">Schritte antippen zum Wechseln (laufende Daten bleiben im Browser gespeichert).</p>
</nav>
