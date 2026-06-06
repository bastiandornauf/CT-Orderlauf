<?php
$ot = ($supplier['order_type'] ?? 'mail') === 'webshop' ? 'webshop' : 'mail';
$weekdayShort = [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'];
$xDataJson = htmlspecialchars(json_encode(['order_type' => $ot], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
?>
<section class="page-section" x-data="<?= $xDataJson ?>">
    <h1 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/suppliers/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <?php if (!empty($supplier['id'])): ?>
            <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input class="input" id="name" name="name" required
                   value="<?= htmlspecialchars($supplier['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group" x-show="order_type === 'mail'" x-cloak>
            <label class="form-label" for="email">E-Mail</label>
            <input class="input" id="email" name="email" type="email"
                   value="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <p class="form-hint">Für Bestellungen per E-Mail an diesen Lieferanten.</p>
        </div>
        <div class="form-row">
            <div class="form-group grow">
                <label class="form-label" for="street">Straße &amp; Hausnummer</label>
                <input class="input" id="street" name="street"
                       value="<?= htmlspecialchars($supplier['street'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. Musterstraße 12">
            </div>
            <div class="form-group grow">
                <label class="form-label" for="city">PLZ &amp; Ort</label>
                <input class="input" id="city" name="city"
                       value="<?= htmlspecialchars($supplier['city'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. 52070 Aachen">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="phone">Telefon</label>
                <input class="input" id="phone" name="phone" type="tel"
                       value="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="fax">Fax</label>
                <input class="input" id="fax" name="fax" type="tel"
                       value="<?= htmlspecialchars($supplier['fax'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="mobile">Mobil</label>
                <input class="input" id="mobile" name="mobile" type="tel"
                       value="<?= htmlspecialchars($supplier['mobile'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="order_type">Bestelltyp</label>
            <select class="select" id="order_type" name="order_type" x-model="order_type">
                <option value="mail">E-Mail</option>
                <option value="webshop">Webshop (nur Liste)</option>
            </select>
        </div>
        <div class="form-group" x-show="order_type === 'mail'" x-cloak>
            <label class="checkbox-label">
                <input type="checkbox" name="attach_pdf" value="1"
                       <?= !empty($supplier['attach_pdf']) ? 'checked' : '' ?>>
                PDF automatisch anhängen (bei Direktversand)
            </label>
            <p class="form-hint">Wenn der Direktversand in den Einstellungen aktiv ist, wird die Bestellliste als PDF an die Mail angehängt.</p>
        </div>
        <div class="form-group">
            <label class="form-label" for="email_subject_template">Betreff-Vorlage (optional)</label>
            <p class="form-hint">Wenn ausgefüllt, ersetzt dieser Betreff die <a href="/settings">systemweite Betreff-Vorlage</a> nur für diesen Lieferanten. Leer lassen für die globale Vorlage.</p>
            <input class="input" id="email_subject_template" name="email_subject_template" maxlength="512"
                   value="<?= htmlspecialchars($supplier['email_subject_template'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="z.B. Bestellung {{COMPANY}} – {{SUPPLIER}} {{TARGET_DATE}}">
            <div class="placeholder-legend">
                <p class="placeholder-legend__title">Platzhalter wie unter Einstellungen:</p>
                <p class="form-hint"><code>{{COMPANY}}</code>, <code>{{USER}}</code>, <code>{{APP_NAME}}</code>, <code>{{SUPPLIER}}</code>, <code>{{TARGET_DATE}}</code>, <code>{{DATE_TODAY}}</code></p>
            </div>
        </div>
        <fieldset class="form-fieldset">
            <legend class="form-legend">Liefertage</legend>
            <div class="checkbox-grid">
                <?php for ($d = 1; $d <= 7; $d++): ?>
                    <label class="checkbox">
                        <input type="checkbox" name="weekdays[]" value="<?= $d ?>"
                            <?= in_array($d, $weekdays ?? [], true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($weekdayShort[$d] ?? (string) $d, ENT_QUOTES, 'UTF-8') ?>
                    </label>
                <?php endfor; ?>
            </div>
        </fieldset>
        <div class="form-group">
            <label class="form-label" for="email_template">E-Mail-Vorlage (optional)</label>
            <p class="form-hint">Dieses Feld betrifft nur den <strong>Nachrichtentext</strong>. Den Standard-<strong>Betreff</strong> legen Sie unter <a href="/settings">Einstellungen</a> fest; optional pro Lieferant oben überschreibbar.</p>
            <textarea class="textarea" id="email_template" name="email_template" rows="8"><?= htmlspecialchars($supplier['email_template'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="placeholder-legend">
                <p class="placeholder-legend__title">Verfügbare Platzhalter:</p>
                <dl class="placeholder-legend__list">
                    <div class="placeholder-legend__row">
                        <dt><code>{{TARGET_DATE}}</code></dt>
                        <dd>Lieferdatum (z.&thinsp;B. <em>14.04.2026</em>)</dd>
                    </div>
                    <div class="placeholder-legend__row">
                        <dt><code>{{SUPPLIER}}</code></dt>
                        <dd>Name des Lieferanten</dd>
                    </div>
                    <div class="placeholder-legend__row">
                        <dt><code>{{LINES}}</code></dt>
                        <dd>Artikelliste (automatisch befüllt)</dd>
                    </div>
                    <div class="placeholder-legend__row">
                        <dt><code>{{ADDONS}}</code></dt>
                        <dd>Freie Zusatzpositionen, falls vorhanden</dd>
                    </div>
                    <div class="placeholder-legend__row">
                        <dt><code>{{SUPPLIER_NOTE}}</code></dt>
                        <dd>Zusatznotiz für diesen Lieferanten</dd>
                    </div>
                    <div class="placeholder-legend__row">
                        <dt><code>{{DATE_TODAY}}</code></dt>
                        <dd>Heutiges Datum (identisch mit Lieferdatum)</dd>
                    </div>
                    <div class="placeholder-legend__row">
                        <dt><code>{{IF ADDONS}}</code> … <code>{{ENDIF}}</code></dt>
                        <dd>Block nur anzeigen, wenn Zusatzpositionen vorhanden sind</dd>
                    </div>
                </dl>
                <p class="form-hint">Ohne Vorlage wird ein Standard-Text verwendet.</p>
            </div>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="active" <?= !isset($supplier['active']) || (int) $supplier['active'] ? 'checked' : '' ?>>
            aktiv
        </label>
        <button type="submit" class="button button--primary">Speichern</button>
    </form>
</section>
