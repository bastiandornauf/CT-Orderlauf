<?php
/**
 * Bestelldaten je Lieferant (Alpine: `row`, `idx`).
 * $articleFieldPrefix: ID-Präfix; $articleFieldNamed: true = Formularfelder mit name="…[idx]"
 */
$p = $articleFieldPrefix ?? 'sa';
$named = $articleFieldNamed ?? true;
// JS-Ausdruck für die Artikel-ID (0 = noch nicht gespeichert → keine Preise)
$itemIdExpr = $priceItemIdExpr ?? '0';
// Lieferanten-ID über das Zeilen-Array der Seite lesen: Ein eingebettetes x-data bemerkt nicht,
// wenn x-for die Zeile `row` austauscht (Dialog wird für wechselnde Artikel wiederverwendet).
$supplierIdExpr = $priceSupplierIdExpr ?? 'row.supplier_id';
$nameAttr = static fn (string $f): string => $named ? ":name=\"'{$f}[' + idx + ']'\"" : '';
?>
<details class="supplier-article" x-show="row.supplier_id" :open="!!(row.supplier_label || row.supplier_sku)">
    <summary class="supplier-article__summary">
        <span>Bestelldaten</span>
        <span class="text-muted supplier-article__preview"
              x-text="[row.supplier_label, row.supplier_sku ? 'Art.-Nr. ' + row.supplier_sku : '', row.pack_size && row.pack_unit ? row.pack_size + ' ' + row.pack_unit : ''].filter(Boolean).join(' · ')"></span>
    </summary>
    <div class="form-group">
        <label class="form-label" :for="'<?= $p ?>-label-' + idx">Bezeichnung beim Lieferanten</label>
        <input class="input" type="text" :id="'<?= $p ?>-label-' + idx" <?= $nameAttr('supplier_label') ?> x-model="row.supplier_label" placeholder="leer = Name">
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" :for="'<?= $p ?>-sku-' + idx">Art.-Nr.</label>
            <input class="input" type="text" :id="'<?= $p ?>-sku-' + idx" <?= $nameAttr('supplier_sku') ?> x-model="row.supplier_sku">
        </div>
        <div class="form-group">
            <label class="form-label" :for="'<?= $p ?>-ou-' + idx">Bestelleinheit</label>
            <input class="input" type="text" :id="'<?= $p ?>-ou-' + idx" <?= $nameAttr('order_unit') ?> x-model="row.order_unit" placeholder="leer = Einheit">
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label class="form-label" :for="'<?= $p ?>-ps-' + idx">Gebinde</label>
            <input class="input" type="text" inputmode="decimal" :id="'<?= $p ?>-ps-' + idx" <?= $nameAttr('pack_size') ?> x-model="row.pack_size" placeholder="z.&nbsp;B. 4,25">
        </div>
        <div class="form-group">
            <label class="form-label" :for="'<?= $p ?>-pu-' + idx">Gebinde-Einheit</label>
            <select class="select" :id="'<?= $p ?>-pu-' + idx" <?= $nameAttr('pack_unit') ?> x-model="row.pack_unit">
                <option value="">—</option>
                <option value="kg">kg</option>
                <option value="g">g</option>
                <option value="l">l</option>
                <option value="ml">ml</option>
                <option value="stk">Stück</option>
            </select>
        </div>
    </div>
    <div class="supplier-prices" x-data="supplierPrices()" x-effect="watch(<?= $itemIdExpr ?>, <?= $supplierIdExpr ?>)">
        <div class="supplier-prices__head">
            <span class="form-label u-m-0">Preis</span>
            <span class="supplier-prices__latest" x-show="latest" x-text="latest ? label(latest) : ''"></span>
            <span class="text-muted" x-show="!latest && itemId && !loading">unbekannt</span>
            <span class="text-muted" x-show="!itemId">nach dem ersten Speichern</span>
        </div>
        <p class="text-muted supplier-prices__purchase" x-show="latestPurchase && latestPurchase !== latest"
           x-text="latestPurchase ? 'Letzter Einkauf: ' + label(latestPurchase, false) : ''"></p>
        <p class="toast toast--error" x-show="error" x-text="error"></p>
        <div class="supplier-prices__actions" x-show="itemId && !showForm">
            <button type="button" class="button button--ghost button--small" @click="openForm()">Preis eintragen</button>
            <button type="button" class="button button--ghost button--small" x-show="prices.length > 1"
                    @click="showHistory = !showHistory" x-text="showHistory ? 'Verlauf ausblenden' : 'Verlauf (' + prices.length + ')'"></button>
        </div>
        <div class="supplier-prices__form" x-show="showForm" @keydown.enter.prevent="save()">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" :for="'<?= $p ?>-pa-' + idx">Betrag €</label>
                    <input class="input" type="text" inputmode="decimal" :id="'<?= $p ?>-pa-' + idx" x-model="draft.amount" placeholder="24,80">
                </div>
                <div class="form-group supplier-prices__qty">
                    <label class="form-label" :for="'<?= $p ?>-pq-' + idx">je</label>
                    <input class="input" type="text" inputmode="decimal" :id="'<?= $p ?>-pq-' + idx" x-model="draft.per_qty">
                </div>
                <div class="form-group">
                    <label class="form-label" :for="'<?= $p ?>-pu2-' + idx">Einheit</label>
                    <select class="select" :id="'<?= $p ?>-pu2-' + idx" x-model="draft.per_unit">
                        <option value="order">Bestelleinheit</option>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="l">l</option>
                        <option value="ml">ml</option>
                        <option value="stk">Stück</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" :for="'<?= $p ?>-pd-' + idx">Datum</label>
                    <input class="input" type="date" :id="'<?= $p ?>-pd-' + idx" x-model="draft.price_date">
                </div>
                <div class="form-group">
                    <label class="form-label" :for="'<?= $p ?>-pt-' + idx">Art</label>
                    <select class="select" :id="'<?= $p ?>-pt-' + idx" x-model="draft.price_type">
                        <option value="known">Listenpreis</option>
                        <option value="purchase">Einkauf (bestätigt)</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" :for="'<?= $p ?>-ps2-' + idx">Herkunft</label>
                    <select class="select" :id="'<?= $p ?>-ps2-' + idx" x-model="draft.source">
                        <option value="manual">Manuell</option>
                        <option value="shop">Webshop</option>
                        <option value="pricelist">Preisliste</option>
                        <option value="invoice">Rechnung</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" :for="'<?= $p ?>-pr-' + idx">Beleg-Nr. (optional)</label>
                    <input class="input" type="text" :id="'<?= $p ?>-pr-' + idx" x-model="draft.document_ref">
                </div>
            </div>
            <p class="form-hint">Netto. Ein neuer Preis ersetzt den alten nicht – er kommt in den Verlauf.</p>
            <div class="supplier-prices__actions">
                <button type="button" class="button button--ghost button--small" @click="showForm = false" :disabled="busy">Abbrechen</button>
                <button type="button" class="button button--primary button--small" @click="save()" :disabled="busy">Preis speichern</button>
            </div>
        </div>
        <ul class="supplier-prices__history" x-show="showHistory">
            <template x-for="pr in prices" :key="pr.id">
                <li>
                    <span x-text="label(pr)"></span>
                    <span class="text-muted" x-show="pr.document_ref" x-text="'Beleg ' + pr.document_ref"></span>
                    <button type="button" class="button button--ghost button--small" @click="remove(pr)" title="Tippfehler löschen">&times;</button>
                </li>
            </template>
        </ul>
    </div>
</details>
