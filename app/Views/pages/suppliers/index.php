<section class="page-section">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
        <?php $flashOk = $_SESSION['flash_ok'];
        unset($_SESSION['flash_ok']); ?>
        <p class="toast toast--success"><?= htmlspecialchars((string) $flashOk, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <div class="page-toolbar">
        <h1 class="page-title">Lieferanten</h1>
        <div class="toolbar-actions">
            <a href="/export/suppliers" class="button button--ghost button--small" title="CSV-Export Lieferanten">&#8681; CSV</a>
            <a href="/export/delivery-days" class="button button--ghost button--small" title="CSV-Export Liefertage">&#8681; Liefertage</a>
            <a href="/suppliers/new" class="button button--primary button--small">Neu</a>
        </div>
    </div>
    <?php
    $weekdayShort = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
    ?>
    <?php if (empty($suppliers)): ?>
        <div class="card card--pad">
            <p class="text-muted" style="margin:0">Noch keine Lieferanten. <a href="/suppliers/new">Ersten Lieferanten anlegen</a>.</p>
        </div>
    <?php else: ?>
    <ul class="card-list">
        <?php foreach ($suppliers as $s): ?>
            <li class="card card--pad list-item">
                <div class="list-item__main">
                    <strong><?= htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php $addr = array_filter([$s['street'] ?? null, $s['city'] ?? null]); ?>
                    <?php if ($addr): ?>
                        <span class="text-muted"><?= htmlspecialchars(implode(', ', $addr), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if ($s['email']): ?>
                        <span class="text-muted"><?= htmlspecialchars($s['email'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php $contacts = array_filter([$s['phone'] ?? null, $s['mobile'] ?? null, $s['fax'] ? 'Fax: ' . $s['fax'] : null]); ?>
                    <?php if ($contacts): ?>
                        <span class="text-muted"><?= htmlspecialchars(implode(' · ', $contacts), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <span class="text-muted"><?= $s['order_type'] === 'webshop' ? 'Webshop' : 'E-Mail' ?><?= !empty($s['attach_pdf']) ? ' · PDF-Anhang' : '' ?></span>
                    <?php if (!empty($s['weekdays'])): ?>
                        <?php
                        $wdLabels = array_map(
                            static function ($w) use ($weekdayShort) {
                                $i = (int) $w;
                                return $weekdayShort[$i] ?? (string) $w;
                            },
                            $s['weekdays']
                        );
                        ?>
                        <span class="text-muted">Liefertage: <?= htmlspecialchars(implode(', ', $wdLabels), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                    <?php if (!(int) $s['active']): ?>
                        <span class="status-badge status-badge--warn">inaktiv</span>
                    <?php endif; ?>
                </div>
                <a href="/suppliers/edit?id=<?= (int) $s['id'] ?>" class="button button--ghost button--small">Bearbeiten</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
