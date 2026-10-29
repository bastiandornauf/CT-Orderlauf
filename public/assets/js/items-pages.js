import * as api from './api.js';
import { showToast } from './toast.js';

function emptyDraft() {
  return {
    id: 0,
    name: '',
    alias: '',
    unit: '',
    unit_factor: '',
    base_unit: '',
    location_id: '',
    sort_order: '0',
    min_stock: '',
    max_stock: '',
    valuation_price: '',
    active: true,
  };
}

function emptySupplierRow() {
  return {
    supplier_id: '',
    priority: 0,
    supplier_label: '',
    supplier_sku: '',
    pack_size: '',
    pack_unit: '',
    order_unit: '',
  };
}

/** Lieferantenzeile aus Server-Daten → Formularzeile (Strings, Komma als Dezimaltrenner). */
function supplierRowFromLink(l) {
  return {
    supplier_id: String(l.supplier_id ?? ''),
    priority: Number(l.priority) || 0,
    supplier_label: l.supplier_label ?? '',
    supplier_sku: l.supplier_sku ?? '',
    pack_size: l.pack_size != null && l.pack_size !== '' ? String(Number(l.pack_size)).replace('.', ',') : '',
    pack_unit: l.pack_unit ?? '',
    order_unit: l.order_unit ?? '',
  };
}

function linkPayload(l) {
  return {
    supplier_id: Number(l.supplier_id) || 0,
    priority: Number(l.priority) || 0,
    supplier_label: l.supplier_label ?? null,
    supplier_sku: l.supplier_sku ?? null,
    pack_size: l.pack_size ?? null,
    pack_unit: l.pack_unit ?? null,
    order_unit: l.order_unit ?? null,
  };
}

/** DECIMAL aus der DB ("10.0000") → "10", "2.5000" → "2,5" */
function formatDecimal(v) {
  if (v == null || v === '') return '';
  const n = Number(v);
  return Number.isFinite(n) ? String(n).replace('.', ',') : String(v);
}

function formatValuation(v) {
  if (v == null || v === '') return '';
  const n = Number(v);
  if (!Number.isFinite(n)) return String(v).replace('.', ',');
  return String(n).replace('.', ',');
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function parseItemPayload(raw) {
  if (!raw) return null;
  try {
    const data = JSON.parse(raw);
    return data && typeof data === 'object' ? data : null;
  } catch {
    return null;
  }
}

export function registerItemsAlpine(Alpine) {
  Alpine.data('itemsListPage', () => ({
    locations: [],
    suppliers: [],
    locationNameById: {},
    supplierNameById: {},
    editError: '',
    editSaving: false,
    editDraft: emptyDraft(),
    editSupplierRows: [emptySupplierRow()],
    rootEl: null,
    init() {
      // $el zeigt in Event-Handlern auf das auslösende Element (z. B. das Dialog-Formular),
      // daher die Seitenwurzel einmalig merken.
      this.rootEl = this.$el;
      const raw = this.$el.dataset.itemsPage;
      if (!raw) return;
      try {
        const cfg = JSON.parse(raw);
        this.locations = Array.isArray(cfg.locations) ? cfg.locations : [];
        this.suppliers = Array.isArray(cfg.suppliers) ? cfg.suppliers : [];
      } catch (e) {
        console.error('Artikelliste: JSON', e);
        return;
      }
      this.locationNameById = Object.fromEntries(
        this.locations.map((l) => [String(l.id), l.name]),
      );
      this.supplierNameById = Object.fromEntries(
        this.suppliers.map((s) => [String(s.id), s.name]),
      );
    },
    openEdit(event) {
      const dialog = this.$refs.itemEditDialog;
      if (!dialog || typeof dialog.showModal !== 'function') return;
      event.preventDefault();
      const li = event.currentTarget.closest('[data-item]');
      const item = parseItemPayload(li?.dataset?.item);
      if (!item || !item.id) {
        showToast('Artikel konnte nicht geladen werden.', 4000, 'error');
        return;
      }
      this.editError = '';
      this.editDraft = {
        id: Number(item.id),
        name: item.name ?? '',
        alias: item.alias ?? '',
        unit: item.unit ?? '',
        unit_factor: formatDecimal(item.unit_factor),
        base_unit: item.base_unit ?? '',
        location_id: String(item.location_id ?? ''),
        sort_order: String(item.sort_order ?? 0),
        min_stock: item.min_stock != null && item.min_stock !== '' ? String(item.min_stock) : '',
        max_stock: item.max_stock != null && item.max_stock !== '' ? String(item.max_stock) : '',
        valuation_price: formatValuation(item.valuation_price),
        active: Number(item.active) !== 0,
      };
      const links = Array.isArray(item.supplier_links) ? item.supplier_links : [];
      const sorted = [...links].sort((a, b) => (Number(b.priority) || 0) - (Number(a.priority) || 0));
      this.editSupplierRows =
        sorted.length === 0 ? [emptySupplierRow()] : sorted.map(supplierRowFromLink);
      this.$nextTick(() => {
        dialog.showModal();
        const panel = dialog.querySelector('.item-quick-edit__panel');
        if (panel) panel.scrollTop = 0;
        this.$refs.ieName?.focus?.({ preventScroll: true });
      });
    },
    closeEdit() {
      this.$refs.itemEditDialog?.close?.();
      this.editError = '';
      this.editDraft = emptyDraft();
    },
    addEditSupplierRow() {
      this.editSupplierRows.push(emptySupplierRow());
    },
    removeEditSupplierRow(idx) {
      if (this.editSupplierRows.length <= 1) return;
      this.editSupplierRows.splice(idx, 1);
    },
    async submitEdit() {
      if (!navigator.onLine) {
        this.editError = 'Nur online möglich.';
        return;
      }
      const lid = Number(this.editDraft.location_id);
      if (!lid) {
        this.editError = 'Lagerort wählen.';
        return;
      }
      this.editSaving = true;
      this.editError = '';
      try {
        const supplier_links = this.editSupplierRows
          .filter((r) => r.supplier_id && String(r.supplier_id).trim() !== '')
          .map(linkPayload);
        const minStr = String(this.editDraft.min_stock ?? '').trim();
        const maxStr = String(this.editDraft.max_stock ?? '').trim();
        const data = await api.saveItem({
          id: this.editDraft.id,
          name: this.editDraft.name.trim(),
          alias: String(this.editDraft.alias ?? '').trim(),
          unit: this.editDraft.unit.trim(),
          unit_factor: String(this.editDraft.unit_factor ?? '').trim(),
          base_unit: this.editDraft.base_unit || null,
          location_id: lid,
          sort_order: Number(this.editDraft.sort_order) || 0,
          min_stock: minStr === '' ? null : Number(minStr),
          max_stock: maxStr === '' ? null : Number(maxStr),
          valuation_price: String(this.editDraft.valuation_price ?? '').trim(),
          active: this.editDraft.active,
          supplier_links,
        });
        this.patchRow(data.item, data.item_supplier_links || []);
        this.closeEdit();
        showToast('Artikel gespeichert.');
      } catch (e) {
        this.editError = e?.message || 'Speichern fehlgeschlagen';
      } finally {
        this.editSaving = false;
      }
    },
    patchRow(item, links) {
      const id = Number(item?.id);
      if (!id) return;
      const li = this.rootEl?.querySelector(`[data-item-id="${id}"]`);
      if (!li) return;

      const locName = this.locationNameById[String(item.location_id)] || '';
      const sorted = [...(links || [])].sort(
        (a, b) => (Number(b.priority) || 0) - (Number(a.priority) || 0),
      );
      const names = sorted
        .map((l) => this.supplierNameById[String(l.supplier_id)] || '')
        .filter(Boolean);
      const nextPayload = {
        id,
        name: item.name ?? '',
        alias: item.alias ?? '',
        unit: item.unit ?? '',
        unit_factor: item.unit_factor ?? null,
        base_unit: item.base_unit ?? null,
        location_id: Number(item.location_id) || 0,
        sort_order: Number(item.sort_order) || 0,
        min_stock: item.min_stock ?? null,
        max_stock: item.max_stock ?? null,
        valuation_price: item.valuation_price ?? null,
        active: Number(item.active) !== 0 ? 1 : 0,
        supplier_links: sorted.map(linkPayload),
      };
      li.dataset.item = JSON.stringify(nextPayload);

      const nameEl = li.querySelector('[data-item-display-name]');
      const formalEl = li.querySelector('[data-item-formal-name]');
      const unitEl = li.querySelector('[data-item-unit]');
      const locEl = li.querySelector('[data-item-location]');
      if (nameEl) nameEl.textContent = item.display_name || item.name || '';
      if (formalEl) {
        formalEl.textContent = item.name ?? '';
        formalEl.hidden = !String(item.alias ?? '').trim();
      }
      if (unitEl) unitEl.textContent = item.unit ?? '';
      if (locEl) locEl.textContent = locName;

      let suppliersEl = li.querySelector('[data-item-suppliers]');
      const main = li.querySelector('.list-item__main');
      if (names.length === 0) {
        suppliersEl?.remove();
      } else {
        if (!suppliersEl && main) {
          suppliersEl = document.createElement('div');
          suppliersEl.className = 'item-suppliers item-suppliers--list';
          suppliersEl.setAttribute('data-item-suppliers', '');
          const badge = main.querySelector('[data-item-inactive]');
          if (badge) main.insertBefore(suppliersEl, badge);
          else main.appendChild(suppliersEl);
        }
        if (suppliersEl) {
          suppliersEl.innerHTML = names
            .map(
              (n, i) =>
                `<span class="supplier-chip${i === 0 ? ' supplier-chip--primary' : ''}">${escapeHtml(n)}</span>`,
            )
            .join('');
        }
      }

      let badge = li.querySelector('[data-item-inactive]');
      if (Number(item.active) === 0) {
        if (!badge && main) {
          badge = document.createElement('span');
          badge.className = 'status-badge status-badge--warn';
          badge.setAttribute('data-item-inactive', '');
          badge.textContent = 'inaktiv';
          main.appendChild(badge);
        }
      } else {
        badge?.remove();
      }

      li.classList.add('list-item--just-saved');
      window.setTimeout(() => li.classList.remove('list-item--just-saved'), 1600);
    },
  }));
}
