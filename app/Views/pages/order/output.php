<section class="page-section" x-data="outputPage({ showOutlookExport: <?= !empty($output_show_outlook) ? 'true' : 'false' ?>, showPdfDownload: <?= !empty($output_show_pdf) ? 'true' : 'false' ?> })">
    <?php $order_step = 4;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <h1 class="page-title">Ausgabe</h1>

    <template x-if="directSend">
        <p class="text-muted output-disclaimer">
            Mails werden <strong>direkt vom Server</strong> versendet. Pro Lieferant erscheint ein „Senden"-Button.
            Bereits gesendete Blöcke werden mit einem Häkchen markiert.
            Ob CC und Empfang bei Ihnen oder beim Lieferanten ankommen, hängt von Mail-Server und Spam-Filtern ab (die App zeigt nur den SMTP-Versuch).
            Schlägt ein Versand fehl, erscheint ein <strong>oranger Hinweis</strong> und der Button <strong>„Nochmal senden“</strong>.
        </p>
    </template>
    <template x-if="!directSend">
        <p class="text-muted output-disclaimer">
            Es gibt <strong>keine automatische Rückmeldung</strong>, ob E-Mails wirklich versendet wurden.
            Pro Lieferant sehen Sie hier nur den vorgeschlagenen Text; „Bestellrunde abschließen" bestätigt nur lokal, dass Sie fertig sind – nicht den Versand.
            <strong>Mehrere Mails gleichzeitig</strong> kann der Browser technisch nicht zuverlässig – nutzen Sie den Assistenten „Schritt für Schritt“ oder nacheinander die Buttons pro Lieferant.
            CC und Zustellung (z.&nbsp;B. Microsoft 365, Spam) sind im Mail-Programm bzw. bei der IT zu prüfen.
        </p>
    </template>

    <template x-if="devMode">
        <p class="toast toast--warn">Testbetrieb aktiv – Alle Mails gehen an <strong x-text="devEmail"></strong></p>
    </template>

    <template x-if="cc && String(cc).trim()">
        <p class="text-muted output-cc-line">
            <strong>CC</strong> für Bestellmails (Lieferanten per E-Mail / Serverversand): <span class="output-cc-line__addr" x-text="cc"></span>
        </p>
    </template>

    <p class="text-muted order-stammdaten-hint">
        Nach Änderungen an Lieferanten oder Einstellungen (z.&nbsp;B. Betreff-Vorlagen):
        <button type="button" class="button button--ghost button--small order-stammdaten-hint__btn"
                :disabled="syncBusy" @click="refreshStammdaten()">Vorschau vom Server aktualisieren</button>
        <span x-show="syncBusy" class="text-muted"> …</span>
    </p>

    <!-- Bulk actions: Direct send mode -->
    <template x-if="directSend">
        <div class="card card--pad">
            <div class="button-row">
                <template x-if="sendableBlocks.length > 0">
                    <button type="button" class="button button--primary"
                            :disabled="sendingAll"
                            @click="sendAllBlocks()">
                        <span class="button__label"
                              x-text="sendingAll ? 'Sende...' : 'Alle senden (' + sendableBlocks.length + ')'">Alle senden</span>
                    </button>
                </template>
                <template x-if="sendableBlocks.length > 0">
                    <button type="button" class="button button--ghost" @click="startMailtoWizard()">
                        <span class="button__label"
                              x-text="'Mail-Assistent (' + sendableBlocks.length + ')'">Mail-Assistent</span>
                    </button>
                </template>
                <button type="button" class="button button--ghost" @click="copyAllBlocks()">Alles kopieren</button>
                <template x-if="showOutlookExport">
                    <button type="button" class="button button--ghost" @click="exportForOutlook()"
                            title="XML-Datei + alle PDFs herunterladen">Outlook-Export</button>
                </template>
            </div>
        </div>
    </template>

    <!-- Bulk actions: Mailto mode with Outlook -->
    <template x-if="!directSend && showOutlookExport">
        <div class="card card--pad">
            <p class="text-muted" style="margin-bottom: var(--space-3)">
                <strong>Outlook-Workflow:</strong> „Für Outlook exportieren" lädt XML + alle PDFs herunter.
                Dann einmalig das Outlook-Makro ausführen → Entwürfe mit PDF-Anhang werden automatisch erstellt.
            </p>
            <div class="button-row">
                <template x-if="sendableBlocks.length > 0">
                    <button type="button" class="button button--primary" @click="startMailtoWizard()">
                        <span class="button__label"
                              x-text="'Mail-Assistent (' + sendableBlocks.length + ')'">Mail-Assistent</span>
                    </button>
                </template>
                <button type="button" class="button button--secondary" @click="exportForOutlook()"
                        title="XML-Datei + alle PDFs herunterladen, dann Outlook-Makro ausführen">
                    Für Outlook exportieren
                </button>
                <button type="button" class="button button--ghost" @click="copyAllBlocks()">Alles kopieren</button>
            </div>
        </div>
    </template>

    <!-- Bulk actions: Mailto mode without Outlook -->
    <template x-if="!directSend && !showOutlookExport">
        <div class="card card--pad">
            <div class="button-row">
                <template x-if="sendableBlocks.length > 0">
                    <button type="button" class="button button--primary" @click="startMailtoWizard()">
                        <span class="button__label"
                              x-text="'Mail-Assistent (' + sendableBlocks.length + ')'">Mail-Assistent</span>
                    </button>
                </template>
                <button type="button" class="button button--ghost" @click="copyAllBlocks()">Alles kopieren</button>
            </div>
        </div>
    </template>

    <dialog class="output-mailto-wizard"
            x-ref="mailtoWizardDialog"
            aria-labelledby="mailto-wizard-title"
            @click="if ($event.target === $refs.mailtoWizardDialog) closeMailtoWizard()">
        <div class="output-mailto-wizard__panel form-stack">
            <h2 id="mailto-wizard-title" class="section-header" style="margin-top:0">Schritt für Schritt: Mail-Programm</h2>
            <p class="text-muted" style="margin:0">
                Schritt <strong x-text="mailtoWizardStepLabel"></strong> · Lieferant
                <strong x-text="mailtoWizardSupplierName"></strong>
            </p>
            <p class="form-hint text-muted" style="margin:0">
                Öffnen Sie die Mail im Programm, senden oder speichern Sie sie, wechseln Sie zurück in diesen Tab und klicken Sie auf <strong>Weiter</strong> zum nächsten Lieferanten.
            </p>
            <div class="output-mailto-wizard__actions">
                <button type="button" class="button button--mailto-urgent button--block"
                        @click="mailtoWizardOpenCurrent()">
                    Diese Mail im Programm öffnen
                </button>
                <div class="button-row" style="margin-top: var(--space-2)">
                    <button type="button" class="button button--secondary" @click="mailtoWizardNext()">
                        Weiter zum nächsten
                    </button>
                    <button type="button" class="button button--ghost" @click="closeMailtoWizard()">Schließen</button>
                </div>
            </div>
        </div>
    </dialog>

    <template x-for="block in blocks" :key="block.supplier.id">
        <div class="card card--pad"
             :class="{
                'output-supplier-mail-pending': mailtoNeedsAttention(block),
                'output-supplier-mailto-opened': mailtoOpened(block) && isSendableBlock(block),
                'output-supplier--collapsed': blockCollapsed(block)
             }">
            <div class="page-toolbar">
                <div>
                    <h2 class="section-header" x-text="block.supplier.name"></h2>
                    <template x-if="isSendableBlock(block) && mailtoOpened(block) && directSend">
                        <span class="status-badge status-badge--ok">Mail-Programm geöffnet (lokal)</span>
                    </template>
                    <template x-if="isSendableBlock(block) && mailtoOpened(block) && !directSend">
                        <span class="status-badge status-badge--ok">Mail-Programm geöffnet</span>
                    </template>
                    <template x-if="directSend && blockSendState(block) === 'sent'">
                        <span class="status-badge status-badge--ok">Gesendet</span>
                    </template>
                    <template x-if="directSend && blockSendState(block) === 'error'">
                        <span class="status-badge status-badge--warn">Senden fehlgeschlagen – erneut mit hervorgehobenem Button</span>
                    </template>
                    <template x-if="directSend && blockSendState(block) === 'sending'">
                        <span class="status-badge status-badge--neutral">Sende…</span>
                    </template>
                </div>
                <div class="output-supplier-meta">
                    <span class="delivery-badge" x-text="'Lieferung ' + formatDate(block.deliveryDate)"></span>
                    <template x-if="blockIsDone(block)">
                        <button type="button"
                                class="button button--ghost button--small output-supplier-toggle"
                                :aria-expanded="String(!blockCollapsed(block))"
                                @click="toggleBlockCollapse(block)">
                            <span class="button__label" x-text="blockCollapseLabel(block)">Details verbergen</span>
                        </button>
                    </template>
                </div>
            </div>

            <div class="output-supplier-body" x-show="!blockCollapsed(block)">
                <p class="text-muted">
                    <template x-if="block.supplier.order_type === 'webshop'">
                        <span>Webshop · Liste an <span x-text="cc || '(keine CC-Adresse)'"></span></span>
                    </template>
                    <template x-if="block.supplier.order_type !== 'webshop'">
                        <span x-text="block.supplier.email || '—'"></span>
                    </template>
                </p>

                <div class="output-mailto-alert" role="status"
                     x-show="!directSend && mailtoNeedsAttention(block) && isSendableBlock(block)">
                    <strong>Mail noch nicht über den Button geöffnet</strong>
                    <p class="output-mailto-alert__hint">
                        Lieferanten mit orangem Rand: hier oder im Assistenten oben das Mail-Programm öffnen.
                        Ob Empfang oder CC in Ihrer Firma klappt, sehen Sie nur im Postfach / Junk – die App meldet das nicht zurück.
                    </p>
                </div>

                <div class="mail-preview">
                    <p class="mail-preview__subj"><strong>Betreff:</strong> <span x-text="block.subject"></span></p>
                    <pre class="mail-preview__body" x-text="block.body"></pre>
                </div>

                <div class="button-row">
                    <button type="button" class="button button--secondary" @click="copyBlock(block)">Kopieren</button>

                    <!-- Direct send (SMTP): ein Button, Label aus Alpine-Methode -->
                    <template x-if="directSend && (block.supplier.order_type === 'webshop' || (block.supplier.order_type === 'mail' && block.supplier.email))">
                        <button type="button"
                                :class="smtpSendButtonClass(block)"
                                :disabled="blockSendState(block) === 'sending'"
                                @click="sendBlock(block)">
                            <span class="button__label" x-text="smtpSendButtonLabel(block)">Senden</span>
                        </button>
                    </template>

                    <!-- Mailto fallback buttons (also shown alongside direct send as secondary option) -->
                    <template x-if="block.supplier.order_type === 'mail' && block.supplier.email">
                        <button type="button" :class="mailtoClientButtonClass(block)" @click="mailtoBlock(block)">
                            <span class="button__label" x-text="mailtoClientButtonLabel(block)">Mail</span>
                        </button>
                    </template>
                    <template x-if="!directSend && block.supplier.order_type === 'webshop'">
                        <button type="button" :class="mailtoNeedsAttention(block) ? 'button button--mailto-urgent' : 'button button--primary'"
                                @click="mailtoBlock(block)">
                            <span class="button__label" x-text="mailtoClientButtonLabel(block)">Webshop</span>
                        </button>
                    </template>

                    <button type="button" class="button button--secondary" x-show="showPdfDownload" @click="pdfBlock(block)">PDF</button>
                </div>
            </div>
        </div>
    </template>

    <div class="button-stack" x-show="!finalized">
        <button type="button" class="button button--primary button--block" @click="finalizeDone()">Bestellrunde abschließen</button>
        <p class="form-hint text-muted" style="margin:0">Nur lokale Bestätigung – kein Versandnachweis.</p>
    </div>

    <div class="card card--pad" x-show="finalized">
        <p class="toast toast--success">Diese Runde ist abgeschlossen (nur lokal gespeichert, kein Versandnachweis).</p>
        <button type="button" class="button button--primary button--block" @click="newRound()">Neue Bestellrunde</button>
    </div>

    <a href="/" class="button button--ghost button--block">Zum Start</a>
</section>
