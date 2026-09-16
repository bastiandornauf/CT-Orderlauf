<section class="page-section"
         x-data="outputPage"
         data-show-outlook-export="<?= !empty($output_show_outlook) ? '1' : '0' ?>"
         data-show-pdf-download="<?= !empty($output_show_pdf) ? '1' : '0' ?>"
         data-mail-user-name="<?= htmlspecialchars((string) ($mail_user_name ?? ''), ENT_QUOTES, 'UTF-8') ?>">
    <?php $order_step = 4;
    require __DIR__ . '/../../partials/order-stepper.php'; ?>
    <div class="page-toolbar page-toolbar--compact">
        <h1 class="page-title">Versand</h1>
        <div class="toolbar-actions">
            <button type="button" class="button button--ghost button--small"
                    :disabled="syncBusy" @click="refreshStammdaten()">
                <span x-show="!syncBusy">↻ Aktualisieren</span>
                <span x-show="syncBusy">…</span>
            </button>
        </div>
    </div>

    <p class="toast toast--warn" x-show="devMode" x-cloak>
        Testbetrieb – alle Mails an <strong x-text="devEmail"></strong>
    </p>
    <p class="text-muted output-cc-line" x-show="cc && String(cc).trim()" x-cloak>
        CC: <span class="output-cc-line__addr" x-text="cc"></span>
    </p>

    <div class="button-stack output-bulk" x-show="!finalized && sendableBlocks.length" x-cloak>
        <button type="button" class="button button--primary button--block"
                x-show="directSend"
                :disabled="sendingAll"
                @click="sendAllBlocks()">
            <span class="button__label"
                  x-text="sendingAll ? 'Sende …' : 'Alle senden (' + sendableBlocks.length + ')'">Alle senden</span>
        </button>
        <button type="button"
                class="button button--block"
                :class="directSend ? 'button--secondary' : 'button--primary'"
                @click="startMailtoWizard()">
            Mail-Assistent
        </button>
        <div class="button-row output-bulk__secondary">
            <button type="button" class="button button--ghost button--small" @click="copyAllBlocks()">Alles kopieren</button>
            <button type="button" class="button button--ghost button--small"
                    x-show="showOutlookExport"
                    @click="exportForOutlook()">Outlook-Export</button>
        </div>
    </div>

    <dialog class="output-mailto-wizard"
            x-ref="mailtoWizardDialog"
            aria-labelledby="mailto-wizard-title"
            @click="if ($event.target === $refs.mailtoWizardDialog) closeMailtoWizard()">
        <div class="output-mailto-wizard__panel form-stack">
            <h2 id="mailto-wizard-title" class="section-header">Mail-Assistent</h2>
            <p class="text-muted u-m-0">
                <strong x-text="mailtoWizardStepLabel"></strong>
                · <strong x-text="mailtoWizardSupplierName"></strong>
            </p>
            <p class="form-hint text-muted">
                Öffnen, senden oder speichern, dann <strong>Weiter</strong>.
            </p>
            <div class="output-mailto-wizard__actions">
                <button type="button" class="button button--mailto-urgent button--block"
                        @click="mailtoWizardOpenCurrent()">
                    Im Mail-Programm öffnen
                </button>
                <div class="button-row output-mailto-wizard__nav">
                    <button type="button" class="button button--secondary" @click="mailtoWizardNext()">Weiter</button>
                    <button type="button" class="button button--ghost" @click="closeMailtoWizard()">Schließen</button>
                </div>
            </div>
        </div>
    </dialog>

    <template x-for="block in blocks" :key="block.supplier.id">
        <div class="card card--pad output-supplier"
             :class="{
                'output-supplier-mail-pending': mailtoNeedsAttention(block),
                'output-supplier-mailto-opened': mailtoOpened(block) && isSendableBlock(block),
                'output-supplier--collapsed': blockCollapsed(block)
             }">
            <div class="output-supplier__head">
                <div class="output-supplier__identity">
                    <h2 class="section-header output-supplier__name" x-text="block.supplier.name"></h2>
                    <p class="text-muted output-supplier__meta">
                        <span x-text="block.supplier.order_type === 'webshop' ? 'Webshop' : (block.supplier.email || '—')"></span>
                        <span x-show="block.deliveryDate" x-text="' · ' + formatDate(block.deliveryDate)"></span>
                    </p>
                </div>
                <div class="output-supplier__status">
                    <span class="status-badge"
                          :class="blockStatusClass(block)"
                          x-show="blockStatusLabel(block)"
                          x-text="blockStatusLabel(block)"></span>
                    <button type="button"
                            class="button button--ghost button--small"
                            x-show="blockIsDone(block)"
                            :aria-expanded="String(!blockCollapsed(block))"
                            @click="toggleBlockCollapse(block)"
                            x-text="blockCollapsed(block) ? 'Anzeigen' : 'Zuklappen'">Anzeigen</button>
                </div>
            </div>

            <div class="output-supplier-body" x-show="!blockCollapsed(block)">
                <details class="mail-preview">
                    <summary class="mail-preview__summary">
                        <span class="mail-preview__subj-label">Betreff</span>
                        <span x-text="block.subject"></span>
                    </summary>
                    <pre class="mail-preview__body" x-text="block.body"></pre>
                </details>

                <div class="button-row output-supplier__actions">
                    <template x-if="directSend && (block.supplier.order_type === 'webshop' || (block.supplier.order_type === 'mail' && block.supplier.email))">
                        <button type="button"
                                :class="smtpSendButtonClass(block)"
                                :disabled="blockSendState(block) === 'sending'"
                                @click="sendBlock(block)">
                            <span class="button__label" x-text="smtpSendButtonLabel(block)">Senden</span>
                        </button>
                    </template>
                    <template x-if="block.supplier.order_type === 'mail' && block.supplier.email">
                        <button type="button" :class="mailtoClientButtonClass(block)" @click="mailtoBlock(block)">
                            <span class="button__label" x-text="mailtoClientButtonLabel(block)">Mail öffnen</span>
                        </button>
                    </template>
                    <template x-if="!directSend && block.supplier.order_type === 'webshop'">
                        <button type="button" :class="mailtoNeedsAttention(block) ? 'button button--mailto-urgent' : 'button button--primary'"
                                @click="mailtoBlock(block)">
                            <span class="button__label" x-text="mailtoClientButtonLabel(block)">Liste mailen</span>
                        </button>
                    </template>
                    <button type="button" class="button button--ghost" @click="copyBlock(block)">Kopieren</button>
                    <button type="button" class="button button--ghost" x-show="showPdfDownload" @click="pdfBlock(block)">PDF</button>
                </div>
            </div>
        </div>
    </template>

    <div class="button-stack" x-show="!finalized">
        <button type="button" class="button button--primary button--block" @click="finalizeDone()">Bestellung abschließen</button>
        <p class="form-hint text-muted">Nur lokal – kein Versandnachweis.</p>
    </div>

    <div class="card card--pad" x-show="finalized" x-cloak>
        <p class="toast toast--success">Bestellung abgeschlossen (nur lokal).</p>
        <button type="button" class="button button--primary button--block" @click="newRound()">Neue Bestellung</button>
    </div>

    <a href="/" class="button button--ghost button--block">Zum Start</a>
</section>
