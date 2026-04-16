<section class="page-section" x-data="outputPage({ showOutlookExport: <?= !empty($output_show_outlook) ? 'true' : 'false' ?>, showPdfDownload: <?= !empty($output_show_pdf) ? 'true' : 'false' ?> })">
    <?php $order_step = 4;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <h1 class="page-title">Ausgabe</h1>

    <template x-if="directSend">
        <p class="text-muted output-disclaimer">
            Mails werden <strong>direkt vom Server</strong> versendet. Pro Lieferant erscheint ein „Senden"-Button.
            Bereits gesendete Blöcke werden mit einem Häkchen markiert.
        </p>
    </template>
    <template x-if="!directSend">
        <p class="text-muted output-disclaimer">
            Es gibt <strong>keine automatische Rückmeldung</strong>, ob E-Mails wirklich versendet wurden.
            Pro Lieferant sehen Sie hier nur den vorgeschlagenen Text; „Bestellrunde abschließen" bestätigt nur lokal, dass Sie fertig sind – nicht den Versand.
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
                            @click="sendAllBlocks()"
                            x-text="sendingAll ? 'Sende…' : 'Alle senden (' + sendableBlocks.length + ')'"></button>
                </template>
                <template x-if="sendableBlocks.length > 0">
                    <button type="button" class="button button--ghost" @click="openAllMails()"
                            x-text="'Alle Mails öffnen (' + sendableBlocks.length + ')'"></button>
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
                    <button type="button" class="button button--primary" @click="openAllMails()"
                            x-text="'Alle Mails öffnen (' + sendableBlocks.length + ')'"></button>
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
                    <button type="button" class="button button--primary" @click="openAllMails()"
                            x-text="'Alle Mails öffnen (' + sendableBlocks.length + ')'"></button>
                </template>
                <button type="button" class="button button--ghost" @click="copyAllBlocks()">Alles kopieren</button>
            </div>
        </div>
    </template>

    <template x-for="block in blocks" :key="block.supplier.id">
        <div class="card card--pad">
            <div class="page-toolbar">
                <div>
                    <h2 class="section-header" x-text="block.supplier.name"></h2>
                    <template x-if="directSend && blockSendState(block) === 'sent'">
                        <span class="status-badge status-badge--ok">Gesendet</span>
                    </template>
                    <template x-if="directSend && blockSendState(block) === 'error'">
                        <span class="status-badge status-badge--offline">Fehler</span>
                    </template>
                    <template x-if="directSend && blockSendState(block) === 'sending'">
                        <span class="status-badge status-badge--neutral">Sende…</span>
                    </template>
                </div>
                <span class="delivery-badge" x-text="'Lieferung ' + formatDate(block.deliveryDate)"></span>
            </div>
            <p class="text-muted">
                <template x-if="block.supplier.order_type === 'webshop'">
                    <span>Webshop · Liste an <span x-text="cc || '(keine CC-Adresse)'"></span></span>
                </template>
                <template x-if="block.supplier.order_type !== 'webshop'">
                    <span x-text="block.supplier.email || '—'"></span>
                </template>
            </p>

            <div class="mail-preview">
                <p class="mail-preview__subj"><strong>Betreff:</strong> <span x-text="block.subject"></span></p>
                <pre class="mail-preview__body" x-text="block.body"></pre>
            </div>

            <div class="button-row">
                <button type="button" class="button button--secondary" @click="copyBlock(block)">Kopieren</button>

                <!-- Direct send buttons -->
                <template x-if="directSend && block.supplier.order_type === 'mail' && block.supplier.email">
                    <button type="button" class="button button--primary"
                            :disabled="blockSendState(block) === 'sending'"
                            @click="sendBlock(block)"
                            x-text="blockSendState(block) === 'sent' ? 'Erneut senden' : blockSendState(block) === 'sending' ? 'Sende…' : 'Senden'"></button>
                </template>
                <template x-if="directSend && block.supplier.order_type === 'webshop'">
                    <button type="button" class="button button--primary"
                            :disabled="blockSendState(block) === 'sending'"
                            @click="sendBlock(block)"
                            x-text="blockSendState(block) === 'sent' ? 'Erneut senden' : blockSendState(block) === 'sending' ? 'Sende…' : 'Webshop-Liste senden'"></button>
                </template>

                <!-- Mailto fallback buttons (also shown alongside direct send as secondary option) -->
                <template x-if="block.supplier.order_type === 'mail' && block.supplier.email">
                    <button type="button" :class="directSend ? 'button button--ghost' : 'button button--primary'" @click="mailtoBlock(block)"
                            x-text="directSend ? 'Im Mail-Client' : 'Mail öffnen'"></button>
                </template>
                <template x-if="!directSend && block.supplier.order_type === 'webshop'">
                    <button type="button" class="button button--primary" @click="mailtoBlock(block)">Webshop-Liste mailen</button>
                </template>

                <button type="button" class="button button--secondary" x-show="showPdfDownload" @click="pdfBlock(block)">PDF</button>
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
