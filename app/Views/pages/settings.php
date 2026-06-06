<section class="page-section">
    <h1 class="page-title">Einstellungen</h1>
    <?php if (!empty($_GET['saved'])): ?>
        <p class="toast toast--success">Gespeichert.</p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/settings/save" class="settings-form">
        <?= \App\Helpers\Csrf::field() ?>

        <!-- ── Allgemein ──────────────────────────────────── -->
        <div class="card card--pad settings-section">
            <h2 class="settings-section__title">Allgemein</h2>
            <div class="form-group">
                <label class="form-label" for="app_name">App-Name</label>
                <input class="input" id="app_name" name="app_name"
                       value="<?= htmlspecialchars($app_name ?? 'CT-Orderlauf', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="CT-Orderlauf">
                <p class="form-hint">Kopfzeile, Browsertitel und PDF-Titel.</p>
            </div>
        </div>

        <!-- ── Besteller ──────────────────────────────────── -->
        <div class="card card--pad settings-section">
            <h2 class="settings-section__title">Besteller <span class="settings-section__sub">erscheint auf PDFs</span></h2>
            <div class="form-group">
                <label class="form-label" for="company_name">Firmenname</label>
                <input class="input" id="company_name" name="company_name"
                       value="<?= htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="company_street">Straße &amp; Hausnummer</label>
                <input class="input" id="company_street" name="company_street"
                       value="<?= htmlspecialchars($company_street ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="company_city">PLZ &amp; Ort</label>
                <input class="input" id="company_city" name="company_city"
                       value="<?= htmlspecialchars($company_city ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="company_phone">Telefon</label>
                    <input class="input" id="company_phone" name="company_phone" type="tel"
                           value="<?= htmlspecialchars($company_phone ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="company_fax">Fax</label>
                    <input class="input" id="company_fax" name="company_fax" type="tel"
                           value="<?= htmlspecialchars($company_fax ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </div>

        <!-- ── E-Mail ─────────────────────────────────────── -->
        <div class="card card--pad settings-section">
            <h2 class="settings-section__title">E-Mail-Versand</h2>
            <div class="form-group">
                <label class="form-label" for="order_cc_email">CC für Bestellmails</label>
                <input class="input" id="order_cc_email" name="order_cc_email" type="email"
                       value="<?= htmlspecialchars($order_cc_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="leer = kein CC">
            </div>
            <div class="form-group">
                <label class="form-label" for="order_email_subject_template">Betreff-Vorlage</label>
                <input class="input" id="order_email_subject_template" name="order_email_subject_template"
                       value="<?= htmlspecialchars($order_email_subject_template ?? 'Bestellung {{COMPANY}} {{TARGET_DATE}}', ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="off">
                <p class="form-hint"><code>{{COMPANY}}</code> · <code>{{USER}}</code> · <code>{{SUPPLIER}}</code> · <code>{{TARGET_DATE}}</code> · <code>{{DATE_TODAY}}</code></p>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="send_email_direct" value="1"
                           <?= ($send_email_direct ?? '0') === '1' ? 'checked' : '' ?>>
                    Mails direkt vom Server senden
                </label>
                <p class="form-hint">Sonst werden mailto-Links geöffnet.</p>
            </div>

            <!-- SMTP -->
            <details class="settings-subsection" <?= (!empty($smtp_host) || isset($smtp_test_ok)) ? 'open' : '' ?>>
                <summary class="settings-subsection__summary">SMTP-Server konfigurieren</summary>
                <div class="settings-subsection__body">
                    <p class="form-hint">Leer = PHP <code>mail()</code> verwenden. Ausgefüllt = Versand über diesen SMTP-Server.</p>
                    <div class="form-row">
                        <div class="form-group" style="flex:3">
                            <label class="form-label" for="smtp_host">Server</label>
                            <input class="input" id="smtp_host" name="smtp_host"
                                   value="<?= htmlspecialchars($smtp_host ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="z.B. smtp.ionos.de">
                        </div>
                        <div class="form-group" style="flex:1;min-width:6rem">
                            <label class="form-label" for="smtp_port">Port</label>
                            <input class="input" id="smtp_port" name="smtp_port" type="number"
                                   value="<?= htmlspecialchars($smtp_port ?? '587', ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group" style="flex:2">
                            <label class="form-label" for="smtp_user">Benutzername</label>
                            <input class="input" id="smtp_user" name="smtp_user" type="email"
                                   value="<?= htmlspecialchars($smtp_user ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   autocomplete="username" inputmode="email" spellcheck="false"
                                   placeholder="name@domain.de">
                        </div>
                        <div class="form-group" style="flex:1">
                            <label class="form-label" for="smtp_pass">Passwort</label>
                            <input class="input" id="smtp_pass" name="smtp_pass" type="password"
                                   value="" autocomplete="new-password"
                                   placeholder="unverändert">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="smtp_from_email">Absender-Adresse</label>
                            <input class="input" id="smtp_from_email" name="smtp_from_email" type="email"
                                   value="<?= htmlspecialchars($smtp_from_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="z. B. bestellung@ihre-domain.de" autocomplete="email">
                            <p class="form-hint"><strong>Pflichtfeld</strong>, sobald ein SMTP-Server eingetragen ist: dieselbe Adresse wie der SMTP-Benutzer, eine noreply@-Adresse Ihrer Domain o. ä. – nicht die CC-Kopf-Adresse als Absender wählen (Microsoft 365 kann sonst Ihre CC unterdrücken). Ausnahme: bei Ionos wird der Absender automatisch auf den SMTP-Benutzer gesetzt. Ohne Eintrag liefert der Direktversand eine <strong>Fehlermeldung</strong>.</p>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="smtp_from_name">Absendername</label>
                            <input class="input" id="smtp_from_name" name="smtp_from_name"
                                   value="<?= htmlspecialchars($smtp_from_name ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="leer = Firmenname">
                        </div>
                    </div>
                    <div style="margin-top:var(--space-2)">
                        <button type="submit" form="smtp-test-form" class="button button--secondary button--small">Verbindung testen</button>
                    </div>
                    <?php if (isset($smtp_test_ok) && $smtp_test_ok === true): ?>
                        <p class="toast toast--success" style="margin-top:var(--space-3)"><?= htmlspecialchars((string) ($smtp_test_message ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php elseif (isset($smtp_test_ok) && $smtp_test_ok === false): ?>
                        <p class="toast toast--error" style="margin-top:var(--space-3);white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars((string) ($smtp_test_message ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($smtp_test_log)): ?>
                        <details <?= (isset($smtp_test_ok) && $smtp_test_ok === false) ? 'open' : '' ?> style="margin-top:var(--space-2)">
                            <summary style="cursor:pointer;font-weight:600;font-size:var(--text-sm)">Protokoll</summary>
                            <pre class="settings-smtp-log"><?= htmlspecialchars($smtp_test_log, ENT_QUOTES, 'UTF-8') ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <!-- ── Ausgabe ────────────────────────────────────── -->
        <div class="card card--pad settings-section">
            <h2 class="settings-section__title">Ausgabe-Seite</h2>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="ui_show_outlook_export" value="1"
                           <?= ($ui_show_outlook_export ?? '1') === '1' ? 'checked' : '' ?>>
                    Outlook-Workflow anzeigen
                </label>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="ui_show_pdf" value="1"
                           <?= ($ui_show_pdf ?? '1') === '1' ? 'checked' : '' ?>>
                    PDF-Button pro Lieferant
                </label>
            </div>
        </div>

        <!-- ── Testbetrieb ────────────────────────────────── -->
        <div class="card card--pad settings-section settings-section--test<?= ($dev_mode ?? '0') === '1' ? ' settings-section--test-active' : '' ?>">
            <h2 class="settings-section__title">Testbetrieb</h2>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="dev_mode" value="1"
                           <?= ($dev_mode ?? '0') === '1' ? 'checked' : '' ?>>
                    Testbetrieb aktiv
                </label>
                <p class="form-hint">Alle Mails gehen nur an die Dev-E-Mail.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="dev_email">Dev-E-Mail</label>
                <input class="input" id="dev_email" name="dev_email" type="email"
                       value="<?= htmlspecialchars($dev_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. dev@example.com">
            </div>
        </div>

        <button type="submit" class="button button--primary button--block">Speichern</button>
    </form>

    <form id="smtp-test-form" method="post" action="/settings/smtp-test" style="display:none">
        <?= \App\Helpers\Csrf::field() ?>
    </form>
</section>
