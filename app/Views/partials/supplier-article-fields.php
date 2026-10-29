<?php
/**
 * Bestelldaten je Lieferant (Alpine: `row`, `idx`).
 * $articleFieldPrefix: ID-Präfix; $articleFieldNamed: true = Formularfelder mit name="…[idx]"
 */
$p = $articleFieldPrefix ?? 'sa';
$named = $articleFieldNamed ?? true;
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
</details>
