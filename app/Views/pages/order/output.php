<section class="page-section" x-data="outputPage()">
    <h1 class="page-title">Ausgabe</h1>
    <p class="text-muted">Mailtext in Zwischenablage kopieren oder Mail-Programm öffnen. PDF für Anhang erzeugen.</p>

    <template x-for="block in blocks" :key="block.supplier.id">
        <div class="card card--pad">
            <h2 class="section-header" x-text="block.supplier.name"></h2>
            <p class="text-muted" x-text="block.supplier.email || '—'"></p>

            <div class="mail-preview">
                <p class="mail-preview__subj"><strong>Betreff:</strong> <span x-text="block.subject"></span></p>
                <pre class="mail-preview__body" x-text="block.body"></pre>
            </div>

            <div class="button-row">
                <button type="button" class="button button--secondary" @click="copyBlock(block)">Kopieren</button>
                <template x-if="block.supplier.order_type === 'mail' && block.supplier.email">
                    <button type="button" class="button button--primary" @click="mailtoBlock(block)">Mail öffnen</button>
                </template>
                <button type="button" class="button button--secondary" @click="pdfBlock(block)">PDF</button>
            </div>
        </div>
    </template>

    <div class="button-stack" x-show="!finalized">
        <button type="button" class="button button--primary button--block" @click="finalizeDone()">Bestellrunde abschließen</button>
    </div>

    <div class="card card--pad" x-show="finalized">
        <p class="toast toast--success">Diese Runde ist abgeschlossen.</p>
        <button type="button" class="button button--primary button--block" @click="newRound()">Neue Bestellrunde</button>
    </div>

    <a href="/" class="button button--ghost button--block">Zum Start</a>
</section>
