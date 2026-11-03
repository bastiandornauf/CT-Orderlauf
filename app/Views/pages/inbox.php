<?php
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$kindLabels = ['lieferant' => 'Lieferant', 'beleg' => 'Beleg', 'nachtrag' => 'Nachtrag', 'sonstiges' => 'Sonstiges'];
$kindBadge = ['lieferant' => 'status-badge--warn', 'beleg' => 'status-badge--ok', 'nachtrag' => 'status-badge--neutral', 'sonstiges' => 'status-badge--neutral'];
$fmtDate = static fn (string $d): string => $d !== '' ? date('d.m.y H:i', strtotime($d)) : '';
$fmtSize = static fn (int $b): string => $b >= 1_000_000 ? number_format($b / 1_000_000, 1, ',', '') . ' MB' : max(1, (int) round($b / 1000)) . ' kB';
$tabs = [
    'neu' => 'Neu' . (($counts['lieferant'] + $counts['sonstiges']) > 0 ? ' (' . ($counts['lieferant'] + $counts['sonstiges']) . ')' : ''),
    'belege' => 'Belege' . ($counts['belege'] > 0 ? ' (' . $counts['belege'] . ')' : ''),
    'alle' => 'Alle',
];
?>
<section class="page-section inbox" x-data="inboxPage">
    <div class="page-toolbar">
        <h1 class="page-title">Posteingang</h1>
        <div class="toolbar-actions">
            <button type="button" class="button button--secondary button--small" @click="fetchNow()" :disabled="busy"
                    <?= $configured ? '' : 'disabled' ?>>
                <span x-text="busy ? 'Rufe ab …' : 'Jetzt abrufen'">Jetzt abrufen</span>
            </button>
        </div>
    </div>

    <?php if (!empty($_GET['saved'])): ?>
        <p class="toast toast--success" role="status">Gespeichert.<?php if ((int) ($_GET['neu_eingeordnet'] ?? 0) > 0): ?> <?= (int) $_GET['neu_eingeordnet'] ?> Nachricht(en) neu eingeordnet.<?php endif; ?></p>
    <?php endif; ?>
    <?php if (!$configured): ?>
        <p class="toast toast--warn">Erst unter <a href="/settings">Einstellungen → SMTP</a> Server, Benutzer und Passwort eintragen – der Posteingang nutzt denselben Zugang.</p>
    <?php elseif ($last_error !== ''): ?>
        <p class="toast toast--error u-pre-wrap" role="alert">Letzter Abruf fehlgeschlagen: <?= $h($last_error) ?></p>
    <?php endif; ?>
    <?php if ($own_addresses === ''): ?>
        <p class="toast toast--warn">Noch keine eigenen Adressen eingetragen – deine Antworten und Weiterleitungen werden sonst falsch erkannt. <a href="#einstellungen">Jetzt eintragen</a></p>
    <?php endif; ?>
    <p class="text-muted inbox__meta">
        <?= $h($mailbox) ?><?= $last_fetch ? ' · zuletzt abgerufen ' . $h(\App\Services\Inbox\InboxService::now('d.m. H:i', $last_fetch)) : ' · noch nie abgerufen' ?>
    </p>

    <nav class="tab-bar" aria-label="Filter">
        <?php foreach ($tabs as $key => $label): ?>
            <a href="/posteingang?f=<?= $h($key) ?>" class="tab-bar__btn<?= $filter === $key ? ' tab-bar__btn--active' : '' ?>"
               <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= $h($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($filter === 'belege' && $counts['belege'] > 0): ?>
        <div class="card card--pad inbox__receipts">
            <p><strong><?= (int) $counts['belege'] ?> offene Belege.</strong> Als ZIP laden und Claude „pflege die Belege ein“ sagen. Beim Einspielen des Pflegepakets werden sie abgehakt.</p>
            <a href="/posteingang/belege.zip" class="button button--primary button--small">Offene Belege als ZIP</a>
        </div>
    <?php endif; ?>

    <?php if ($messages === []): ?>
        <p class="round-search__empty">
            <?= $filter === 'neu' ? 'Keine neuen Nachrichten.' : ($filter === 'belege' ? 'Noch keine Belege. Rechnungen und Lieferschein-Fotos von einer eigenen Adresse an ' . $h($mailbox ?: 'das Postfach') . ' weiterleiten.' : 'Noch keine Nachrichten abgerufen.') ?>
        </p>
    <?php endif; ?>

    <ul class="card-list inbox__list">
        <?php foreach ($messages as $m):
            $done = $m['status'] === 'erledigt';
            $sender = $m['kind'] === 'lieferant' && $m['supplier_name'] ? $m['supplier_name'] : ($m['from_name'] !== '' ? $m['from_name'] : $m['from_email']);
            ?>
            <li class="card inbox__item<?= $done ? ' inbox__item--done' : '' ?>">
                <details>
                    <summary class="inbox__summary">
                        <span class="inbox__head">
                            <span class="status-badge <?= $kindBadge[$m['kind']] ?? 'status-badge--neutral' ?>"><?= $h($kindLabels[$m['kind']] ?? $m['kind']) ?></span>
                            <strong class="inbox__sender"><?= $h($sender) ?></strong>
                            <span class="text-muted inbox__date"><?= $h($fmtDate((string) $m['received_at'])) ?></span>
                        </span>
                        <span class="inbox__subject"><?= $h($m['subject']) ?></span>
                        <?php if ($m['attachments'] !== []): ?>
                            <span class="text-muted inbox__att-count"><?= count($m['attachments']) ?> Anhang<?= count($m['attachments']) > 1 ? 'e' : '' ?></span>
                        <?php endif; ?>
                    </summary>
                    <div class="inbox__body">
                        <p class="text-muted"><?= $h($m['from_name'] !== '' ? $m['from_name'] . ' <' . $m['from_email'] . '>' : $m['from_email']) ?></p>
                        <?php if ($m['attachments'] !== []): ?>
                            <ul class="inbox__attachments">
                                <?php foreach ($m['attachments'] as $a): ?>
                                    <li><a href="/posteingang/anhang?id=<?= (int) $m['id'] ?>&amp;file=<?= $h(rawurlencode((string) $a['file'])) ?>" target="_blank" rel="noopener"><?= $h($a['name']) ?></a>
                                        <span class="text-muted"><?= $h($fmtSize((int) $a['size'])) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (trim((string) $m['body_text']) !== ''): ?>
                            <pre class="inbox__text"><?= $h($m['body_text']) ?></pre>
                        <?php endif; ?>
                        <div class="button-row inbox__actions">
                            <?php if ($done): ?>
                                <button type="button" class="button button--ghost button--small" @click="update(<?= (int) $m['id'] ?>, { status: 'neu' })">Wieder öffnen</button>
                            <?php else: ?>
                                <button type="button" class="button button--secondary button--small" @click="update(<?= (int) $m['id'] ?>, { status: 'erledigt' })">Erledigt</button>
                            <?php endif; ?>
                            <label class="inbox__kind">
                                <span class="visually-hidden">Art ändern</span>
                                <select class="select inbox__kind-select" @change="update(<?= (int) $m['id'] ?>, { kind: $event.target.value })">
                                    <?php foreach ($kindLabels as $k => $label): ?>
                                        <option value="<?= $h($k) ?>" <?= $m['kind'] === $k ? 'selected' : '' ?>><?= $h($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                    </div>
                </details>
            </li>
        <?php endforeach; ?>
    </ul>

    <details class="card card--pad inbox__settings" id="einstellungen"<?= $own_addresses === '' ? ' open' : '' ?>>
        <summary class="settings-subsection__summary">Einstellungen Posteingang</summary>
        <form method="post" action="/posteingang/einstellungen" class="form-stack">
            <?= \App\Helpers\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label" for="inbox_own_addresses">Eigene Adressen (Weiterleitungen = Beleg)</label>
                <textarea class="textarea" id="inbox_own_addresses" name="inbox_own_addresses" rows="3"
                          spellcheck="false" placeholder="eine Adresse pro Zeile"><?= $h($own_addresses) ?></textarea>
                <p class="form-hint">Antworten von hier (Betreff „Re:“/„AW:“) gelten als Nachtrag und werden ausgeblendet. Beim Speichern werden alle vorhandenen Nachrichten neu eingeordnet.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="imap_host">IMAP-Server</label>
                <input class="input" id="imap_host" name="imap_host" value="<?= $h($imap_host_custom) ?>" placeholder="automatisch">
                <p class="form-hint">Leer = automatisch (<?= $h($imap_host ?: '–') ?>, Port 993). Benutzer und Passwort wie beim SMTP-Versand.</p>
            </div>
            <button type="submit" class="button button--primary button--small">Speichern</button>
        </form>
    </details>
</section>
