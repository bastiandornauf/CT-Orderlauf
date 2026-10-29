import { showToast } from './toast.js';

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function formatDeDate(iso) {
  if (!iso) return '';
  const [y, m, d] = String(iso).split('-');
  return `${d}.${m}.${y.slice(2)}`;
}

async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ ...body, _csrf: csrfToken() }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || !data.ok) throw new Error(data.error || 'Speichern fehlgeschlagen');
  return data;
}

export const PRICE_TYPE_LABELS = { known: 'Listenpreis', purchase: 'Einkauf' };

/**
 * Kurzform eines Preises: "24,80 €/Karton · 2,48 €/kg · 03.10.26"
 * Betrag und Vergleichspreis kommen fertig formatiert vom Server (keine Rechnung im Browser).
 * @param {object|null} p Preis aus der API (PriceRepository::present)
 * @param {{ withType?: boolean }} [opt]
 */
export function formatPrice(p, opt = {}) {
  if (!p) return '';
  const per = p.per_qty && p.per_qty !== '1' ? `${p.per_qty} ${p.per_label}` : p.per_label;
  const parts = [`${p.amount} €/${per}`];
  if (p.comparison) parts.push(`${p.comparison.amount} €/${p.comparison.base}`);
  parts.push(formatDeDate(p.price_date));
  if (opt.withType) parts.push(PRICE_TYPE_LABELS[p.price_type] || p.price_type);
  return parts.join(' · ');
}

function emptyDraft() {
  return {
    amount: '',
    per_qty: '1',
    per_unit: 'order',
    price_date: todayIso(),
    price_type: 'known',
    source: 'manual',
    document_ref: '',
  };
}

/**
 * Preise eines Lieferanten für einen Artikel (im Editor unter „Bestelldaten“).
 * Item- und Lieferanten-ID kommen über x-effect="watch(itemId, supplierId)",
 * weil die Zeile im Dialog für wechselnde Artikel wiederverwendet wird.
 */
export function registerPricesAlpine(Alpine) {
  Alpine.data('supplierPrices', () => {
    let key = '';
    return {
      itemId: 0,
      supplierId: 0,
      prices: [],
      loading: false,
      showForm: false,
      showHistory: false,
      draft: emptyDraft(),
      error: '',
      busy: false,
      watch(itemId, supplierId) {
        const next = `${Number(itemId) || 0}:${Number(supplierId) || 0}`;
        if (next === key) return;
        key = next;
        this.itemId = Number(itemId) || 0;
        this.supplierId = Number(supplierId) || 0;
        this.prices = [];
        this.showForm = false;
        this.showHistory = false;
        this.error = '';
        if (this.itemId && this.supplierId) this.load();
      },
      async load() {
        this.loading = true;
        try {
          const res = await fetch(`/api/prices?item_id=${this.itemId}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          const data = await res.json();
          if (!res.ok || !data.ok) throw new Error(data.error || 'Preise laden fehlgeschlagen');
          this.setPrices(data.prices);
        } catch (e) {
          this.error = e?.message || 'Preise laden fehlgeschlagen';
        } finally {
          this.loading = false;
        }
      },
      setPrices(all) {
        this.prices = (all || []).filter((p) => Number(p.supplier_id) === this.supplierId);
      },
      get latest() {
        return this.prices[0] || null;
      },
      get latestPurchase() {
        return this.prices.find((p) => p.price_type === 'purchase') || null;
      },
      label(p, withType = true) {
        return formatPrice(p, { withType });
      },
      openForm() {
        this.draft = emptyDraft();
        this.error = '';
        this.showForm = true;
      },
      async save() {
        if (!navigator.onLine) {
          this.error = 'Nur online möglich.';
          return;
        }
        this.busy = true;
        this.error = '';
        try {
          const data = await postJson('/api/prices/add', {
            ...this.draft,
            item_id: this.itemId,
            supplier_id: this.supplierId,
          });
          this.setPrices(data.prices);
          this.showForm = false;
          showToast(data.created ? 'Preis gespeichert.' : 'Diesen Preis gibt es schon.', 3000, data.created ? 'success' : 'warn');
        } catch (e) {
          this.error = e?.message || 'Speichern fehlgeschlagen';
        } finally {
          this.busy = false;
        }
      },
      async remove(p) {
        if (!window.confirm(`Preis vom ${formatDeDate(p.price_date)} (${p.amount} €) löschen?`)) return;
        try {
          const data = await postJson('/api/prices/delete', { id: p.id });
          this.setPrices(data.prices);
        } catch (e) {
          this.error = e?.message || 'Löschen fehlgeschlagen';
        }
      },
    };
  });
}
