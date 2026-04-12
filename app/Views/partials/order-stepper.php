<?php
/** @var int $order_step 1–4 */
$step = isset($order_step) ? (int) $order_step : 0;
if ($step < 1 || $step > 4) {
    return;
}
$labels = ['Vorbereiten', 'Rundgang', 'Kontrolle', 'Ausgabe'];
?>
<nav class="order-stepper" aria-label="Bestellablauf">
    <ol class="order-stepper__list">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <li class="order-stepper__item<?= $i === $step ? ' order-stepper__item--active' : '' ?><?= $i < $step ? ' order-stepper__item--done' : '' ?>">
                <span class="order-stepper__num" aria-hidden="true"><?= $i ?></span>
                <span class="order-stepper__label"><?= htmlspecialchars($labels[$i - 1], ENT_QUOTES, 'UTF-8') ?></span>
            </li>
        <?php endfor; ?>
    </ol>
</nav>
