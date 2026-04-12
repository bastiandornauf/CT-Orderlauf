<section class="page-section">
    <h1 class="page-title">Einstellungen</h1>
    <?php if (!empty($_GET['saved'])): ?>
        <p class="toast toast--success">Gespeichert.</p>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <p class="toast toast--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
    <form method="post" action="/settings/save" class="form-stack card card--pad">
        <?= \App\Helpers\Csrf::field() ?>
        <div class="form-group">
            <label class="form-label" for="app_name">App-Name</label>
            <input class="input" id="app_name" name="app_name"
                   value="<?= htmlspecialchars($app_name ?? 'CT-Orderlauf', ENT_QUOTES, 'UTF-8') ?>">
            <p class="form-hint">Erscheint in der Kopfzeile, im Browsertitel und im PDF-Titel („… – Bestellung“). Für eingeloggte Nutzer; ohne Anmeldung bleibt der Standardname.</p>
        </div>

        <fieldset class="form-fieldset">
            <legend class="form-legend">Besteller (erscheint auf PDFs)</legend>
            <div class="form-group">
                <label class="form-label" for="company_name">Firmenname</label>
                <input class="input" id="company_name" name="company_name"
                       value="<?= htmlspecialchars($company_name ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. Carolus Thermen Gastronomie">
            </div>
            <div class="form-group">
                <label class="form-label" for="company_street">Straße &amp; Hausnummer</label>
                <input class="input" id="company_street" name="company_street"
                       value="<?= htmlspecialchars($company_street ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. Passstraße 79">
            </div>
            <div class="form-group">
                <label class="form-label" for="company_city">PLZ &amp; Ort</label>
                <input class="input" id="company_city" name="company_city"
                       value="<?= htmlspecialchars($company_city ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. 52070 Aachen">
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
        </fieldset>
        <div class="form-group">
            <label class="form-label" for="order_cc_email">CC für Bestellmails (systemweit)</label>
            <input class="input" id="order_cc_email" name="order_cc_email" type="email"
                   value="<?= htmlspecialchars($order_cc_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="leer = kein CC">
        </div>

        <div class="form-group">
            <label class="form-label" for="order_email_subject_template">Betreff-Vorlage für Bestellmails</label>
            <input class="input" id="order_email_subject_template" name="order_email_subject_template"
                   value="<?= htmlspecialchars($order_email_subject_template ?? 'Bestellung {{COMPANY}} {{TARGET_DATE}}', ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="off">
            <p class="form-hint">
                Platzhalter: <code>{{COMPANY}}</code> (Firmenname aus „Besteller“, sonst App-Name),
                <code>{{APP_NAME}}</code>, <code>{{SUPPLIER}}</code> (Lieferantenname),
                <code>{{TARGET_DATE}}</code> / <code>{{DATE_TODAY}}</code> (Lieferdatum im Block).
                Standard: <code>Bestellung {{COMPANY}} {{TARGET_DATE}}</code> – ohne redundanten Lieferantennamen im Betreff.
            </p>
        </div>

        <fieldset class="form-fieldset">
            <legend class="form-legend">Ausgabe-Seite</legend>
            <p class="form-hint">Legt fest, welche Aktionen auf der Seite „Ausgabe“ erscheinen. Eine technische Versandbestätigung gibt es nicht – siehe Hinweis dort.</p>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="ui_show_outlook_export" value="1"
                           <?= ($ui_show_outlook_export ?? '1') === '1' ? 'checked' : '' ?>>
                    Outlook-Workflow anzeigen (Hinweistext, „Für Outlook exportieren“)
                </label>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="ui_show_pdf" value="1"
                           <?= ($ui_show_pdf ?? '1') === '1' ? 'checked' : '' ?>>
                    PDF-Button pro Lieferant anzeigen
                </label>
                <p class="form-hint">Wenn der Outlook-Export aktiv ist, werden dort weiterhin PDFs für das Makro erzeugt – unabhängig von diesem Schalter.</p>
            </div>
        </fieldset>

        <fieldset class="form-fieldset">
            <legend class="form-legend">E-Mail-Direktversand</legend>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="send_email_direct" value="1"
                           <?= ($send_email_direct ?? '0') === '1' ? 'checked' : '' ?>>
                    Mails direkt vom Server senden (statt mailto-Links)
                </label>
                <p class="form-hint">Wenn aktiv, werden Bestellmails direkt versendet. Ohne SMTP-Konfiguration wird PHP <code>mail()</code> verwendet (Absender = Serveradresse). Mit SMTP werden Mails über den konfigurierten Mailserver gesendet.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="smtp_host">SMTP-Server (optional)</label>
                <input class="input" id="smtp_host" name="smtp_host"
                       value="<?= htmlspecialchars($smtp_host ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. smtp.gmail.com oder mail.dein-hoster.de">
                <p class="form-hint">Leer = PHP <code>mail()</code> nutzen. Ausgefüllt = Versand über diesen SMTP-Server.</p>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="smtp_port">Port</label>
                    <input class="input" id="smtp_port" name="smtp_port" type="number"
                           value="<?= htmlspecialchars($smtp_port ?? '587', ENT_QUOTES, 'UTF-8') ?>">
                    <p class="form-hint">587 (STARTTLS) oder 465 (SSL)</p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="smtp_user">Benutzername</label>
                    <input class="input" id="smtp_user" name="smtp_user"
                           value="<?= htmlspecialchars($smtp_user ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           autocomplete="off">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="smtp_pass">Passwort</label>
                <input class="input" id="smtp_pass" name="smtp_pass" type="password"
                       value="<?= htmlspecialchars($smtp_pass ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="new-password">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="smtp_from_email">Absender-Adresse</label>
                    <input class="input" id="smtp_from_email" name="smtp_from_email" type="email"
                           value="<?= htmlspecialchars($smtp_from_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="z.B. bestellung@firma.de">
                    <p class="form-hint">Leer = CC-Adresse wird als Absender verwendet</p>
                </div>
                <div class="form-group">
                    <label class="form-label" for="smtp_from_name">Absendername</label>
                    <input class="input" id="smtp_from_name" name="smtp_from_name"
                           value="<?= htmlspecialchars($smtp_from_name ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="z.B. Küche CT">
                    <p class="form-hint">Leer = Firmenname</p>
                </div>
            </div>
        </fieldset>

        <fieldset class="form-fieldset">
            <legend class="form-legend">Testbetrieb</legend>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="dev_mode" value="1"
                           <?= ($dev_mode ?? '0') === '1' ? 'checked' : '' ?>>
                    Testbetrieb aktiv – alle Mails gehen nur an die Dev-E-Mail
                </label>
            </div>
            <div class="form-group">
                <label class="form-label" for="dev_email">Dev-E-Mail (Empfänger im Testbetrieb)</label>
                <input class="input" id="dev_email" name="dev_email" type="email"
                       value="<?= htmlspecialchars($dev_email ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="z.B. dev@example.com">
                <p class="form-hint">Im Testbetrieb werden TO und CC aller Mails durch diese Adresse ersetzt. Die originalen Empfänger erscheinen nur im Betreff.</p>
            </div>
        </fieldset>

        <button type="submit" class="button button--primary">Speichern</button>
    </form>
</section>
