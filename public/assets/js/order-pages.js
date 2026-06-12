import * as api from './api.js';
import * as storage from './storage.js';
import * as orch from './order-round.js';
import { groupLinksByItem, pickSupplierForItem } from './supplier-logic.js';
import { buildMailPreview, mailtoLink } from './email-generator.js';
import { showToast } from './toast.js';

/** Nächster Kalendertag in lokaler Zeitzone (nicht UTC), für input type="date" */
function tomorrowIso() {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function formatDeDate(iso) {
  if (!iso) return '';
  const [y, m, day] = iso.split('-');
  return `${day}.${m}.${y}`;
}

function orderTypeLabel(raw) {
  if (raw === 'mail') return 'E-Mail';
  if (raw === 'webshop') return 'Webshop';
  return raw || '';
}

/** Min/Max-Bestand für Anzeige im Bestellprozess (leer wenn nicht gepflegt). */
function formatStockHint(it) {
  if (!it || typeof it !== 'object') return '';
  const toNum = (v) => {
    if (v == null || v === '') return null;
    const n = Number(v);
    return Number.isFinite(n) ? n : null;
  };
  const min = toNum(it.min_stock);
  const max = toNum(it.max_stock);
  if (min == null && max == null) return '';
  if (min != null && max != null) return `min ${min} · max ${max}`;
  if (min != null) return `min ${min}`;
  return `max ${max}`;
}

/**
 * @param {{ targetDate: string, loading: boolean, error: string, suppliersWithDates: object[] }} ctx
 */
async function executeLoadPreparedRound(ctx) {
  ctx.error = '';
  ctx.loading = true;
  try {
    if (!navigator.onLine) {
      throw new Error('Vorbereitung nur online möglich.');
    }
    if (await storage.prepareReloadWouldEraseLocalProgress()) {
      const ok = window.confirm(
        'Es gibt bereits eine Bestellrunde mit gespeicherten Eingaben (Rundgang, Kontrolle oder Lieferanten-Notizen).\n\n' +
          'Wenn Sie jetzt „Bestellrunde laden“ ausführen, werden diese lokalen Daten gelöscht und durch eine neue, leere Runde ersetzt.\n\n' +
          'Zum Fortsetzen ohne Datenverlust: oben „Rundgang fortsetzen“ oder „Kontrolle / Abschluss“ wählen – nicht erneut laden.\n\n' +
          'Trotzdem neu laden und alles verwerfen?',
      );
      if (!ok) {
        return;
      }
    }
    const data = await api.fetchPayload(ctx.targetDate);
    const payload = {
      target_date: data.target_date,
      target_weekday: data.target_weekday,
      suppliers_delivering_ids: data.suppliers_delivering_ids,
      supplier_delivery_targets: data.supplier_delivery_targets || [],
      locations: data.locations,
      suppliers: data.suppliers,
      supplier_delivery_days: data.supplier_delivery_days,
      items: data.items,
      item_supplier_links: data.item_supplier_links,
      settings: data.settings,
    };
    await storage.savePreparedSnapshot(payload);

    const targetMap = new Map((data.supplier_delivery_targets || []).map((t) => [Number(t.supplier_id), t.delivery_date]));
    ctx.suppliersWithDates = data.suppliers
      .filter((s) => s.active)
      .map((s) => ({
        ...s,
        deliveryDate: targetMap.get(Number(s.id)) || null,
      }))
      .sort((a, b) => (a.deliveryDate || '').localeCompare(b.deliveryDate || '') || a.name.localeCompare(b.name));

    window.location.href = '/order/round';
  } catch (e) {
    ctx.error = e.message || 'Fehler';
  } finally {
    ctx.loading = false;
  }
}

/** Start-Seite: Status + Fortsetzen + neue Runde vorbereiten */
export function dashboardPageData() {
  return {
    /** Nach init() true – vermeidet „Keine Runde“-Flash vor IndexedDB-Lesezugriff */
    initialized: false,
    hasRound: false,
    roundStatus: 'idle',
    targetDate: tomorrowIso(),
    loading: false,
    error: '',
    suppliersWithDates: [],
    get roundStatusLabel() {
      const m = {
        prepared: 'Vorbereitet – Rundgang noch nicht begonnen',
        active: 'Rundgang läuft',
        ready_for_review: 'Bereit zur Kontrolle und Ausgabe',
        finalized: 'Abgeschlossen – neue Runde möglich',
        paused: 'Runde pausiert',
      };
      return m[this.roundStatus] || '';
    },
    async init() {
      const dateIn = document.getElementById('target_date');
      if (dateIn instanceof HTMLInputElement && dateIn.value) {
        this.targetDate = dateIn.value;
      }
      const row = await storage.getOrderRound();
      this.hasRound = !!row;
      this.roundStatus = row?.status || 'idle';
      this.suppliersWithDates = [];
      this.initialized = true;
      this.$nextTick(() => {
        const open = new URLSearchParams(window.location.search).get('open');
        if (open === 'bestellen') {
          document.getElementById('bestellen')?.scrollIntoView({ behavior: 'smooth' });
          try {
            const u = new URL(window.location.href);
            u.searchParams.delete('open');
            const clean = u.pathname + (u.searchParams.toString() ? `?${u.searchParams}` : '') + u.hash;
            window.history.replaceState({}, '', clean || '/');
          } catch {
            /* ignore */
          }
        }
      });
    },
    async loadRound() {
      await executeLoadPreparedRound(this);
    },
    formatDate(iso) {
      return formatDeDate(iso);
    },
  };
}

/** @param {any} Alpine */
export function registerOrderAlpine(Alpine) {
  Alpine.data('roundPage', () => ({
    locations: [],
    activeLocId: null,
    items: [],
    allItems: [],
    search: '',
    quantities: {},
    freeLabel: '',
    freeQty: '',
    freeSupplierId: '',
    suppliers: [],
    /** @type {Map<number, number[]>} itemId -> Lieferanten-IDs (Priorität) */
    supplierIdsByItem: new Map(),
    supplierNameById: new Map(),
    /** Lieferanten heute ausblenden (nur Anzeige) */
    hiddenSupplierIds: [],
    canEditMaster: false,
    editDraft: {
      id: null,
      name: '',
      unit: '',
      location_id: '',
      sort_order: '0',
      min_stock: '',
      max_stock: '',
      active: true,
    },
    /** @type {{ supplier_id: string, priority: number }[]} */
    editSupplierRows: [],
    editError: '',
    editSaving: false,
    get filteredItems() {
      const q = this.search.trim().toLowerCase();
      const hid = new Set(this.hiddenSupplierIds.map(Number));
      const base =
        q === ''
          ? this.items
          : this.allItems.filter(
              (it) =>
                Number(it.active) !== 0 && it.name.toLowerCase().includes(q),
            );
      return base.filter(
        (it) => Number(it.active) !== 0 && this.isItemVisibleForRound(it.id, hid),
      );
    },
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/?open=bestellen';
        return;
      }
      this.canEditMaster = this.$el.dataset.canEditMaster === '1';
      if (round.status === 'prepared') {
        await storage.setOrderRoundStatus('active');
      }
      this.locations = await storage.getLocationsSorted();
      this.activeLocId = this.locations[0]?.id ?? null;
      this.suppliers = await storage.getAllSuppliers();
      this.hiddenSupplierIds = await storage.getHiddenSupplierIds();
      this.allItems = await storage.getAllItems();

      this.supplierNameById = new Map(this.suppliers.map((s) => [s.id, s.name]));
      await this.rebuildSupplierLinksMap();

      await this.reloadEntries();
      this.$watch('activeLocId', () => this.loadItemsForTab());
      await this.loadItemsForTab();
    },
    async rebuildSupplierLinksMap() {
      const links = await storage.getItemSupplierLinks();
      const byItem = groupLinksByItem(links);
      this.supplierIdsByItem = new Map();
      for (const [itemId, itemLinks] of byItem.entries()) {
        const sorted = [...itemLinks].sort((a, b) => b.priority - a.priority);
        this.supplierIdsByItem.set(
          Number(itemId),
          sorted.map((l) => Number(l.supplier_id)).filter((id) => id > 0),
        );
      }
    },
    async refreshItemCatalogState() {
      this.allItems = await storage.getAllItems();
      await this.rebuildSupplierLinksMap();
      await this.loadItemsForTab();
    },
    async openQuickEdit(it) {
      this.editError = '';
      this.editDraft = {
        id: it.id,
        name: it.name ?? '',
        unit: it.unit ?? '',
        location_id: String(it.location_id ?? ''),
        sort_order: String(it.sort_order ?? 0),
        min_stock:
          it.min_stock != null && it.min_stock !== '' ? String(it.min_stock) : '',
        max_stock:
          it.max_stock != null && it.max_stock !== '' ? String(it.max_stock) : '',
        active: Number(it.active) !== 0,
      };
      const allLinks = await storage.getItemSupplierLinks();
      const mine = allLinks.filter((l) => Number(l.item_id) === Number(it.id));
      const sorted = [...mine].sort((a, b) => b.priority - a.priority);
      if (sorted.length === 0) {
        this.editSupplierRows = [{ supplier_id: '', priority: 0 }];
      } else {
        this.editSupplierRows = sorted.map((l) => ({
          supplier_id: String(l.supplier_id),
          priority: Number(l.priority) || 0,
        }));
      }
      await this.$nextTick();
      this.$refs.quickEditDialog?.showModal?.();
    },
    closeQuickEdit() {
      this.$refs.quickEditDialog?.close?.();
      this.editError = '';
    },
    addEditSupplierRow() {
      this.editSupplierRows.push({ supplier_id: '', priority: 0 });
    },
    removeEditSupplierRow(idx) {
      if (this.editSupplierRows.length <= 1) return;
      this.editSupplierRows.splice(idx, 1);
    },
    async submitQuickEdit() {
      if (!navigator.onLine) {
        this.editError = 'Nur online möglich.';
        return;
      }
      this.editSaving = true;
      this.editError = '';
      try {
        const supplier_links = this.editSupplierRows
          .filter((r) => r.supplier_id && String(r.supplier_id).trim() !== '')
          .map((r) => ({
            supplier_id: Number(r.supplier_id),
            priority: Number(r.priority) || 0,
          }));
        const lid = Number(this.editDraft.location_id);
        if (!lid) {
          this.editError = 'Lagerort wählen.';
          return;
        }
        const hadQty = this.hasQty(this.editDraft.id);
        const minStr = String(this.editDraft.min_stock ?? '').trim();
        const maxStr = String(this.editDraft.max_stock ?? '').trim();
        const data = await api.saveItem({
          id: this.editDraft.id,
          name: this.editDraft.name.trim(),
          unit: this.editDraft.unit.trim(),
          location_id: lid,
          sort_order: Number(this.editDraft.sort_order) || 0,
          min_stock: minStr === '' ? null : Number(minStr),
          max_stock: maxStr === '' ? null : Number(maxStr),
          active: this.editDraft.active,
          supplier_links,
        });
        await storage.upsertItemRow(data.item);
        await storage.replaceItemSupplierLinks(data.item.id, data.item_supplier_links || []);
        await this.refreshItemCatalogState();
        await this.reloadEntries();
        this.closeQuickEdit();
        showToast('Artikel gespeichert.');
        if (!Number(data.item.active) && hadQty) {
          showToast(
            'Hinweis: Artikel ist deaktiviert; eingetragene Menge bleibt lokal, bis Sie sie leeren oder in der Kontrolle weiterbearbeiten.',
            5000,
          );
        }
      } catch (e) {
        this.editError = e?.message || 'Speichern fehlgeschlagen';
      } finally {
        this.editSaving = false;
      }
    },
    get suppliersForFree() {
      const hid = new Set(this.hiddenSupplierIds.map(Number));
      return this.suppliers.filter((s) => s.active && !hid.has(Number(s.id)));
    },
    /**
     * Artikel ausblenden, wenn er nur ausgeblendete Lieferanten hat.
     * Ohne Lieferantenzuordnung: weiter anzeigen (Pflege-Hinweis möglich).
     * @param {Set<number>} [hidSet]
     */
    isItemVisibleForRound(itemId, hidSet = null) {
      const hid = hidSet ?? new Set(this.hiddenSupplierIds.map(Number));
      const ids = this.supplierIdsByItem.get(Number(itemId)) || [];
      if (ids.length === 0) return true;
      return ids.some((sid) => !hid.has(Number(sid)));
    },
    countVisibleInLocation(locId) {
      const hid = new Set(this.hiddenSupplierIds.map(Number));
      return this.allItems.filter((i) => {
        if (Number(i.active) === 0) return false;
        if (Number(i.location_id) !== Number(locId)) return false;
        return this.isItemVisibleForRound(i.id, hid);
      }).length;
    },
    async toggleSupplierHidden(supplierId) {
      const id = Number(supplierId);
      const i = this.hiddenSupplierIds.indexOf(id);
      if (i >= 0) {
        this.hiddenSupplierIds.splice(i, 1);
      } else {
        this.hiddenSupplierIds.push(id);
      }
      await storage.setHiddenSupplierIds(this.hiddenSupplierIds);
      await this.loadItemsForTab();
    },
    isSupplierHidden(supplierId) {
      return this.hiddenSupplierIds.includes(Number(supplierId));
    },
    nextLocation() {
      if (this.locations.length < 2) return;
      const idx = this.locations.findIndex((l) => l.id === this.activeLocId);
      const next = idx >= 0 && idx < this.locations.length - 1 ? this.locations[idx + 1] : this.locations[0];
      this.activeLocId = next.id;
      this.$nextTick(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    },
    nextLocationLabel() {
      if (this.locations.length < 2) return '';
      const idx = this.locations.findIndex((l) => l.id === this.activeLocId);
      const next = idx >= 0 && idx < this.locations.length - 1 ? this.locations[idx + 1] : this.locations[0];
      return next?.name || '';
    },
    itemSupplierNames(itemId) {
      const ids = this.supplierIdsByItem.get(Number(itemId)) || [];
      const hid = new Set(this.hiddenSupplierIds.map(Number));
      return ids
        .filter((sid) => !hid.has(Number(sid)))
        .map((sid) => this.supplierNameById.get(sid))
        .filter(Boolean);
    },
    stockHint(it) {
      return formatStockHint(it);
    },
    tabLabel(loc) {
      const n = this.countVisibleInLocation(loc.id);
      return `${loc.name} (${n})`;
    },
    hasQty(itemId) {
      const q = this.quantities[itemId];
      return q != null && String(q).trim() !== '';
    },
    async reloadEntries() {
      const entries = await storage.getOrderEntries();
      this.quantities = {};
      for (const e of entries) {
        if (!e.is_free_item && e.item_id != null) {
          this.quantities[e.item_id] = String(e.quantity);
        }
      }
    },
    async loadItemsForTab() {
      if (!this.activeLocId) {
        this.items = [];
        return;
      }
      const raw = await storage.getItemsByLocation(this.activeLocId);
      const hid = new Set(this.hiddenSupplierIds.map(Number));
      this.items = raw.filter(
        (it) => Number(it.active) !== 0 && this.isItemVisibleForRound(it.id, hid),
      );
    },
    async onQtyBlur(itemId, event) {
      const v = event.target.value;
      await orch.saveCatalogQuantity(itemId, v, '');
      await this.reloadEntries();
    },
    async onQtyStep(itemId, delta) {
      const current = parseFloat(this.quantities[itemId] || '0') || 0;
      const next = Math.max(0, current + delta);
      const val = next === 0 ? '' : String(next % 1 === 0 ? next : next.toFixed(1));
      await orch.saveCatalogQuantity(itemId, val, '');
      await this.reloadEntries();
    },
    async addFree() {
      await orch.addFreeLine(this.activeLocId, this.freeLabel, this.freeQty, this.freeSupplierId || null);
      this.freeLabel = '';
      this.freeQty = '';
      this.freeSupplierId = '';
    },
    async goReview() {
      await storage.setOrderRoundStatus('ready_for_review');
      window.location.href = '/order/review';
    },
  }));

  Alpine.data('reviewPage', () => ({
    loading: true,
    syncBusy: false,
    problemLines: [],
    pendingFreeLines: [],
    groups: [],
    meta: null,
    allSuppliers: [],
    deliveryMap: new Map(),
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/?open=bestellen';
        return;
      }
      this.loading = true;
      try {
        await this._mergeServerIfOnline(round.target_date);
        await this._rebuildReviewUi();
      } finally {
        this.loading = false;
      }
      let visTimer;
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible' || !navigator.onLine) return;
        clearTimeout(visTimer);
        visTimer = setTimeout(async () => {
          const r = await storage.getOrderRound();
          if (!r) return;
          this.syncBusy = true;
          try {
            await this._mergeServerIfOnline(r.target_date);
            await this._rebuildReviewUi();
          } catch {
            /* ignore */
          } finally {
            this.syncBusy = false;
          }
        }, 400);
      });
    },
    async _mergeServerIfOnline(targetDate) {
      if (!navigator.onLine) return;
      try {
        const fresh = await api.fetchPayload(targetDate);
        await storage.mergeSuppliersAndSettingsFromPayload(fresh);
      } catch (e) {
        console.warn('Stammdaten-Sync übersprungen:', e);
      }
    },
    async _rebuildReviewUi() {
      this.meta = await storage.getMetaSnapshot();

      // Build supplier_id -> delivery_date map
      const targets = this.meta?.supplier_delivery_targets || [];
      this.deliveryMap = new Map(targets.map((t) => [Number(t.supplier_id), t.delivery_date]));

      const entries = (await storage.getOrderEntries()).filter((e) =>
        e.is_free_item ? String(e.quantity ?? '').trim() !== '' : orch.parseQuantity(e.quantity) != null
      );
      const items = await storage.getAllItems();
      const itemMap = new Map(items.map((i) => [i.id, i]));
      const suppliers = await storage.getAllSuppliers();
      this.allSuppliers = suppliers;
      const supMap = new Map(suppliers.map((s) => [s.id, s]));
      const links = await storage.getItemSupplierLinks();
      const byItem = groupLinksByItem(links);
      // All suppliers with a delivery target are "delivering"
      const delivering = this.meta?.suppliers_delivering_ids || [];

      const problem = [];
      const pendingFree = [];
      const bySup = new Map();

      for (const e of entries) {
        if (e.is_free_item) {
          const sid = e.free_supplier_id || e.selected_supplier_id;
          if (!sid) {
            let candidateIds = delivering.map(Number).filter((id) => id > 0);
            if (candidateIds.length === 0) {
              candidateIds = suppliers.filter((s) => s.active).map((s) => Number(s.id));
            }
            candidateIds = [...new Set(candidateIds)].sort((a, b) => {
              const na = supMap.get(a)?.name ?? '';
              const nb = supMap.get(b)?.name ?? '';
              return String(na).localeCompare(String(nb), 'de');
            });
            pendingFree.push({
              entryId: e.id,
              label: e.free_label,
              quantity: e.quantity,
              unit: '',
              itemId: null,
              candidates: candidateIds,
              supplierId: null,
            });
            continue;
          }
          if (!bySup.has(sid)) bySup.set(sid, []);
          bySup.get(sid).push({
            entryId: e.id,
            label: e.free_label,
            quantity: e.quantity,
            unit: '',
            itemId: null,
            candidates: [sid],
            supplierId: sid,
            sortOrder: 0,
          });
          continue;
        }

        const item = itemMap.get(e.item_id);
        if (!item) continue;
        const itemLinks = byItem.get(e.item_id) || [];
        const pick = pickSupplierForItem(e.item_id, itemLinks, delivering, e.selected_supplier_id);
        if (pick.supplierId == null) {
          problem.push({
            entry: e,
            itemLabel: item.name,
            reason: 'Kein Lieferant zugeordnet – bitte manuell ergänzen',
          });
          continue;
        }
        const sid = pick.supplierId;
        if (!bySup.has(sid)) bySup.set(sid, []);
        bySup.get(sid).push({
          entryId: e.id,
          label: item.name,
          quantity: e.quantity,
          unit: item.unit,
          stockHint: formatStockHint(item),
          itemId: e.item_id,
          candidates: pick.candidates,
          supplierId: sid,
          sortOrder: Number(item.sort_order) || 0,
        });
      }

      for (const arr of bySup.values()) {
        arr.sort((a, b) => {
          const ao = a.sortOrder ?? 0;
          const bo = b.sortOrder ?? 0;
          if (ao !== bo) return ao - bo;
          return String(a.label).localeCompare(String(b.label), 'de');
        });
      }

      this.problemLines = problem;
      this.pendingFreeLines = pendingFree;
      const sortedSupIds = [...bySup.keys()].sort((a, b) => {
        const na = supMap.get(a)?.name ?? '';
        const nb = supMap.get(b)?.name ?? '';
        return String(na).localeCompare(String(nb), 'de');
      });
      this.groups = sortedSupIds.map((supplierId) => {
        const lines = bySup.get(supplierId) || [];
        return {
          supplierId,
          supplier: supMap.get(supplierId),
          deliveryDate: this.deliveryMap.get(Number(supplierId)) || null,
          lines,
          note: '',
        };
      });

      for (const g of this.groups) {
        g.note = await storage.getSupplierNote(g.supplierId);
      }
    },
    async rebuildLocal() {
      this.loading = true;
      try {
        await this._rebuildReviewUi();
      } finally {
        this.loading = false;
      }
    },
    async refreshStammdaten() {
      if (!navigator.onLine) {
        showToast('Nur online möglich.');
        return;
      }
      const round = await storage.getOrderRound();
      if (!round) return;
      this.syncBusy = true;
      try {
        await this._mergeServerIfOnline(round.target_date);
        await this._rebuildReviewUi();
        showToast('Lieferanten & Einstellungen aktualisiert.');
      } finally {
        this.syncBusy = false;
      }
    },
    formatDate(iso) {
      return formatDeDate(iso);
    },
    /**
     * Lieferanten-Optionen für eine Position (Reihenfolge = Priorität bei mehreren).
     * @returns {{ id: number, name: string, label: string, dateLabel: string }[]}
     */
    supplierOptions(line) {
      return line.candidates
        .map((id) => {
          const s = this.allSuppliers.find((s) => s.id === id);
          if (!s) return null;
          const date = this.deliveryMap.get(Number(id));
          const dateLabel = date ? `Lieferung ${formatDeDate(date)}` : '';
          const label = dateLabel ? `${s.name} (${formatDeDate(date)})` : s.name;
          return { id: s.id, name: s.name, label, dateLabel };
        })
        .filter(Boolean);
    },
    async onSupplierChange(line, newSid) {
      line.supplierId = Number(newSid);
      const patch = { selected_supplier_id: line.supplierId };
      if (line.itemId == null) {
        patch.free_supplier_id = line.supplierId;
      }
      await storage.updateOrderEntry(line.entryId, patch);
      await this.rebuildLocal();
    },
    async assignPendingFreeSupplier(line, newSid) {
      const v = String(newSid ?? '').trim();
      if (!v) return;
      await this.onSupplierChange(line, v);
    },
    async updateQty(line, event) {
      const raw = event.target.value;
      if (line.itemId == null) {
        const v = String(raw ?? '').trim();
        await storage.updateOrderEntry(line.entryId, { quantity: v });
        line.quantity = v;
        return;
      }
      const parsed = orch.parseQuantity(raw);
      if (parsed === null && raw.trim() !== '') {
        event.target.value = line.quantity;
        return;
      }
      const v = parsed ?? '';
      await storage.updateOrderEntry(line.entryId, { quantity: v });
      line.quantity = v;
    },
    async removeLine(line) {
      await storage.deleteOrderEntry(line.entryId);
      await this.rebuildLocal();
    },
    async saveNote(supplierId, text) {
      await storage.setSupplierNote(supplierId, text);
    },
    async addFreeToSupplier(supplierId) {
      const label = window.prompt('Freier Artikel (Bezeichnung)');
      if (!label?.trim()) return;
      const qty = window.prompt('Menge');
      await orch.addFreeLine(null, label, qty, supplierId);
      await this.rebuildLocal();
    },
    goOutput() {
      if (this.pendingFreeLines.length > 0) {
        showToast('Bitte zuerst alle freien Positionen einem Lieferanten zuordnen.');
        return;
      }
      if (this.problemLines.length > 0) {
        showToast('Bitte zuerst die Problemartikel beheben.');
        return;
      }
      window.location.href = '/order/output';
    },
  }));

  Alpine.data('outputPage', () => ({
    showOutlookExport: true,
    showPdfDownload: true,
    mailUserName: '',
    blocks: [],
    cc: '',
    devMode: false,
    devEmail: '',
    directSend: false,
    targetDate: '',
    finalized: false,
    sendStatus: {},
    sendingAll: false,
    syncBusy: false,
    /** @type {Record<number, true>} Lieferant: mailto wurde mindestens einmal ausgelöst (persistiert pro Zieltag in sessionStorage) */
    mailtoOpenedMap: {},
    /** @type {Record<number, true>} Erledigte Blöcke werden automatisch eingeklappt; per Klick auf „Anzeigen" lässt sich der Block wieder ausklappen. */
    manualExpandedIds: {},
    mailtoWizardIndex: 0,
    mailtoStorageKey() {
      return `ctol_mailto_ok_${this.targetDate || 'x'}`;
    },
    loadMailtoOpened() {
      try {
        const raw = sessionStorage.getItem(this.mailtoStorageKey());
        const o = raw ? JSON.parse(raw) : {};
        const next = {};
        for (const k of Object.keys(o)) {
          next[Number(k)] = true;
        }
        this.mailtoOpenedMap = next;
      } catch {
        this.mailtoOpenedMap = {};
      }
    },
    touchMailtoOpened(supplierId) {
      const sid = Number(supplierId);
      try {
        const raw = sessionStorage.getItem(this.mailtoStorageKey());
        const o = raw ? JSON.parse(raw) : {};
        o[String(sid)] = 1;
        sessionStorage.setItem(this.mailtoStorageKey(), JSON.stringify(o));
      } catch {
        /* ignore */
      }
      this.mailtoOpenedMap = { ...this.mailtoOpenedMap, [sid]: true };
    },
    mailtoOpened(block) {
      const id = block?.supplier?.id;
      if (id == null) return false;
      const n = Number(id);
      return !!(this.mailtoOpenedMap[n] ?? this.mailtoOpenedMap[id]);
    },
    isSendableBlock(block) {
      if (block.supplier.order_type === 'mail' && block.supplier.email) return true;
      if (block.supplier.order_type === 'webshop' && String(this.cc || '').trim()) return true;
      return false;
    },
    mailtoNeedsAttention(block) {
      return !this.directSend && this.isSendableBlock(block) && !this.mailtoOpened(block);
    },
    mailtoClientButtonClass(block) {
      if (this.directSend) {
        return this.mailtoOpened(block)
          ? 'button button--ghost output-mailto-client--used'
          : 'button button--ghost';
      }
      if (this.mailtoNeedsAttention(block)) return 'button button--mailto-urgent';
      return 'button button--primary';
    },
    mailtoClientButtonLabel(block) {
      if (this.directSend) {
        return this.mailtoOpened(block) ? 'Nochmal im Mail-Programm' : 'Im Mail-Client';
      }
      if (block.supplier.order_type === 'webshop') {
        if (this.mailtoOpened(block)) return 'Webshop erneut im Mail-Programm';
        if (this.mailtoNeedsAttention(block)) return 'Webshop jetzt im Mail-Programm';
        return 'Webshop-Liste mailen';
      }
      if (this.mailtoOpened(block)) return 'Nochmal im Mail-Programm öffnen';
      if (this.mailtoNeedsAttention(block)) return 'Jetzt im Mail-Programm öffnen';
      return 'Mail öffnen';
    },
    formatDate(iso) {
      return formatDeDate(iso);
    },
    blockSendState(block) {
      return this.sendStatus[block.supplier.id] || 'idle';
    },
    /** SMTP-Direktversand: ein Button, Text/Klasse ohne verschachteltes x-text (Alpine/HTML). */
    smtpSendButtonLabel(block) {
      const s = this.blockSendState(block);
      if (s === 'sent') return 'Erneut senden';
      if (s === 'sending') return 'Sende...';
      if (s === 'error') return 'Nochmal senden (Server)';
      if (block.supplier.order_type === 'webshop') return 'Webshop-Liste senden';
      return 'Senden';
    },
    smtpSendButtonClass(block) {
      return this.blockSendState(block) === 'error'
        ? 'button button--mailto-urgent'
        : 'button button--primary';
    },
    /** „Erledigt" = SMTP gesendet ODER Mail-Programm geöffnet (Fallback). */
    blockIsDone(block) {
      if (this.blockSendState(block) === 'sent') return true;
      if (this.mailtoOpened(block)) return true;
      return false;
    },
    blockCollapsed(block) {
      const sid = Number(block?.supplier?.id);
      if (!sid) return false;
      if (!this.blockIsDone(block)) return false;
      return !this.manualExpandedIds[sid];
    },
    toggleBlockCollapse(block) {
      const sid = Number(block?.supplier?.id);
      if (!sid) return;
      this.manualExpandedIds = {
        ...this.manualExpandedIds,
        [sid]: !this.manualExpandedIds[sid],
      };
    },
    blockCollapseLabel(block) {
      return this.blockCollapsed(block) ? 'Details anzeigen' : 'Details verbergen';
    },
    get mailBlocks() {
      return this.blocks.filter((b) => b.supplier.order_type === 'mail' && b.supplier.email);
    },
    get webshopBlocks() {
      return this.blocks.filter((b) => b.supplier.order_type === 'webshop');
    },
    get sendableBlocks() {
      return this.blocks.filter((b) => {
        if (b.supplier.order_type === 'mail' && b.supplier.email) return true;
        if (b.supplier.order_type === 'webshop' && this.cc) return true;
        return false;
      });
    },
    get mailtoWizardStepLabel() {
      const n = this.sendableBlocks.length;
      if (n === 0) return '';
      return `${this.mailtoWizardIndex + 1} / ${n}`;
    },
    get mailtoWizardCurrentBlock() {
      return this.sendableBlocks[this.mailtoWizardIndex] || null;
    },
    get mailtoWizardSupplierName() {
      const b = this.mailtoWizardCurrentBlock;
      return b?.supplier?.name ?? '—';
    },
    /** Serientipp: Browser ersetzt mailto bei mehreren schnellen Aufrufen – ein Klick pro Mail. */
    startMailtoWizard() {
      if (this.sendableBlocks.length === 0) return;
      this.mailtoWizardIndex = 0;
      this.$nextTick(() => this.$refs.mailtoWizardDialog?.showModal?.());
    },
    closeMailtoWizard() {
      this.$refs.mailtoWizardDialog?.close?.();
    },
    mailtoWizardOpenCurrent() {
      const b = this.mailtoWizardCurrentBlock;
      if (!b) return;
      this.mailtoBlock(b);
    },
    mailtoWizardNext() {
      if (this.mailtoWizardIndex < this.sendableBlocks.length - 1) {
        this.mailtoWizardIndex += 1;
      } else {
        this.closeMailtoWizard();
        showToast('Letzter Schritt. Prüfen Sie die orange markierten Lieferanten, falls eine Mail noch fehlt.', 6000);
      }
    },
    async copyAllBlocks() {
      const text = this.blocks
        .map((b) => {
          const header = `═══ ${b.supplier.name} · Lieferung ${formatDeDate(b.deliveryDate)} ═══`;
          return `${header}\nBetreff: ${b.subject}\n\n${b.body}`;
        })
        .join('\n\n' + '─'.repeat(50) + '\n\n');
      try {
        await navigator.clipboard.writeText(text);
        showToast('Kopiert!');
      } catch {
        showToast('Kopieren fehlgeschlagen');
      }
    },
    /** Export XML manifest + all PDFs for the Outlook macro */
    async exportForOutlook() {
      const date = this.targetDate;

      // Build XML
      const xmlEsc = (s) =>
        String(s ?? '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');

      const sanitizeName = (name) => name.replace(/[^a-z0-9_-]+/gi, '_');

      const orderNodes = this.blocks
        .map((b) => {
          const pdfName = `bestellung-${sanitizeName(b.supplier.name)}-${date}.pdf`;
          const cc = this.cc || '';
          const isWebshop = b.supplier.order_type === 'webshop';
          const to = isWebshop ? cc : (b.supplier.email || '');
          const subject = isWebshop ? `[Webshop] ${b.subject} – ${b.supplier.name}` : b.subject;
          return [
            '  <order>',
            `    <supplier>${xmlEsc(b.supplier.name)}</supplier>`,
            `    <to>${xmlEsc(to)}</to>`,
            `    <cc>${xmlEsc(isWebshop ? '' : cc)}</cc>`,
            `    <subject>${xmlEsc(subject)}</subject>`,
            `    <body>${xmlEsc(b.body)}</body>`,
            `    <pdf>${xmlEsc(pdfName)}</pdf>`,
            `    <order_type>${xmlEsc(b.supplier.order_type)}</order_type>`,
            '  </order>',
          ].join('\n');
        })
        .join('\n');

      const xml =
        '<?xml version="1.0" encoding="UTF-8"?>\n' +
        `<ct_orderlauf export_date="${new Date().toISOString()}">\n` +
        orderNodes +
        '\n</ct_orderlauf>';

      // Download XML manifest
      const xmlBlob = new Blob([xml], { type: 'application/xml' });
      const xmlUrl = URL.createObjectURL(xmlBlob);
      const xmlLink = document.createElement('a');
      xmlLink.href = xmlUrl;
      xmlLink.download = 'ct-orderlauf-export.xml';
      xmlLink.click();
      URL.revokeObjectURL(xmlUrl);

      // Download all PDFs with consistent names
      await new Promise((r) => setTimeout(r, 400));
      for (const b of this.blocks) {
        try {
          const pdfName = `bestellung-${sanitizeName(b.supplier.name)}-${date}.pdf`;
          const blob = await api.downloadSupplierPdf({
            supplier_name: b.supplier.name,
            target_date: b.deliveryDate || date,
            lines: b.lines,
            free_lines: b.freeLines || [],
            note: b.note,
          });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = pdfName;
          a.click();
          URL.revokeObjectURL(url);
          await new Promise((r) => setTimeout(r, 500));
        } catch (e) {
          console.error('PDF failed for', b.supplier.name, e);
        }
      }
    },
    async init() {
      this.showOutlookExport = this.$el?.dataset?.showOutlookExport !== '0';
      this.showPdfDownload = this.$el?.dataset?.showPdfDownload !== '0';
      this.mailUserName = String(this.$el?.dataset?.mailUserName ?? '').trim();
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/?open=bestellen';
        return;
      }
      this.targetDate = round.target_date;
      this.finalized = round.status === 'finalized';
      await this._mergeOutputServerIfOnline();
      await this._rebuildOutputBlocks();
      window.addEventListener('pageshow', () => {
        this.loadMailtoOpened();
      });
      window.addEventListener('focus', () => {
        this.loadMailtoOpened();
      });
      let visTimer;
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
          this.loadMailtoOpened();
        }
        if (document.visibilityState !== 'visible' || !navigator.onLine) return;
        clearTimeout(visTimer);
        visTimer = setTimeout(async () => {
          if (!this.targetDate) return;
          this.syncBusy = true;
          try {
            await this._mergeOutputServerIfOnline();
            await this._rebuildOutputBlocks();
          } catch {
            /* ignore */
          } finally {
            this.syncBusy = false;
          }
        }, 400);
      });
    },
    async _mergeOutputServerIfOnline() {
      if (!navigator.onLine || !this.targetDate) return;
      try {
        const fresh = await api.fetchPayload(this.targetDate);
        await storage.mergeSuppliersAndSettingsFromPayload(fresh);
      } catch (e) {
        console.warn('Stammdaten-Sync übersprungen:', e);
      }
    },
    async _rebuildOutputBlocks() {
      const meta = await storage.getMetaSnapshot();
      this.cc = meta?.settings?.order_cc_email || '';
      this.devMode = !!meta?.settings?.dev_mode;
      this.devEmail = meta?.settings?.dev_email || '';
      this.directSend = !!meta?.settings?.send_email_direct;

      const entries = (await storage.getOrderEntries()).filter((e) =>
        e.is_free_item ? String(e.quantity ?? '').trim() !== '' : orch.parseQuantity(e.quantity) != null
      );
      const items = await storage.getAllItems();
      const itemMap = new Map(items.map((i) => [i.id, i]));
      const suppliers = await storage.getAllSuppliers();
      const links = await storage.getItemSupplierLinks();
      const byItem = groupLinksByItem(links);
      const delivering = meta?.suppliers_delivering_ids || [];

      // Build supplier_id -> delivery_date map
      const targets = meta?.supplier_delivery_targets || [];
      const deliveryMap = new Map(targets.map((t) => [Number(t.supplier_id), t.delivery_date]));

      const bySup = new Map();     // supplierId -> regular lines[]
      const freesBySup = new Map(); // supplierId -> free line strings[]
      for (const e of entries) {
        if (e.is_free_item) {
          const sid = e.free_supplier_id || e.selected_supplier_id;
          if (!sid) continue;
          if (!bySup.has(sid)) bySup.set(sid, []);
          if (!freesBySup.has(sid)) freesBySup.set(sid, []);
          freesBySup.get(sid).push(`${String(e.quantity ?? '').trim()}x ${String(e.free_label ?? '').trim()}`);
          continue;
        }
        const item = itemMap.get(e.item_id);
        if (!item) continue;
        const itemLinks = byItem.get(e.item_id) || [];
        const pick = pickSupplierForItem(e.item_id, itemLinks, delivering, e.selected_supplier_id);
        const sid = pick.supplierId;
        if (sid == null) continue;
        if (!bySup.has(sid)) bySup.set(sid, []);
        bySup.get(sid).push({
          label: item.name,
          quantity: e.quantity,
          unit: item.unit,
          sortOrder: Number(item.sort_order) || 0,
        });
      }

      for (const arr of bySup.values()) {
        arr.sort((a, b) => {
          const ao = a.sortOrder ?? 0;
          const bo = b.sortOrder ?? 0;
          if (ao !== bo) return ao - bo;
          return String(a.label).localeCompare(String(b.label), 'de');
        });
      }

      const allSupIds = [...new Set([...bySup.keys(), ...freesBySup.keys()])].sort((a, b) => {
        const na = suppliers.find((s) => s.id === a)?.name ?? '';
        const nb = suppliers.find((s) => s.id === b)?.name ?? '';
        return String(na).localeCompare(String(nb), 'de');
      });

      this.blocks = [];
      this.loadMailtoOpened();
      for (const supplierId of allSupIds) {
        const supplier = suppliers.find((s) => s.id === supplierId);
        if (!supplier) continue;
        const lines = bySup.get(supplierId) || [];
        const freeLines = freesBySup.get(supplierId) || [];
        // Use the supplier-specific delivery date, fall back to the round's target date
        const deliveryDate = deliveryMap.get(Number(supplierId)) || this.targetDate;
        const note = await storage.getSupplierNote(supplierId);
        const ms = meta?.settings || {};
        const ownSubject =
          supplier.email_subject_template &&
          String(supplier.email_subject_template).trim() !== ''
            ? String(supplier.email_subject_template)
            : '';
        const prev = buildMailPreview({
          template: supplier.email_template,
          supplierName: supplier.name,
          targetDateFormatted: formatDeDate(deliveryDate),
          lines,
          freeLines,
          supplierNote: note,
          subjectTemplate:
            ownSubject || (ms.order_email_subject_template || ''),
          companyName: ms.company_name || '',
          appName: ms.app_name || '',
          userName: this.mailUserName,
        });
        this.blocks.push({
          supplier,
          lines,
          freeLines,
          note,
          deliveryDate,
          subject: prev.subject,
          body: prev.body,
        });
      }
    },
    async refreshStammdaten() {
      if (!navigator.onLine) {
        showToast('Nur online möglich.');
        return;
      }
      if (!this.targetDate) return;
      this.syncBusy = true;
      try {
        await this._mergeOutputServerIfOnline();
        await this._rebuildOutputBlocks();
        showToast('Lieferanten & Einstellungen aktualisiert.');
      } finally {
        this.syncBusy = false;
      }
    },
    async copyBlock(block) {
      try {
        await navigator.clipboard.writeText(`${block.subject}\n\n${block.body}`);
        showToast('Kopiert!');
      } catch {
        showToast('Kopieren fehlgeschlagen');
      }
    },
    async mailtoBlock(block) {
      if (block.supplier.order_type === 'webshop') {
        if (!this.cc) {
          showToast('Keine CC-Adresse für Webshop-Mail konfiguriert');
          return;
        }
        const subj = `[Webshop] ${block.subject} – ${block.supplier.name}`;
        this.touchMailtoOpened(block.supplier.id);
        await this.$nextTick();
        window.location.href = mailtoLink(this.cc, subj, block.body, '', {
          devMode: this.devMode,
          devEmail: this.devEmail,
        });
        return;
      }
      if (!block.supplier.email) return;
      this.touchMailtoOpened(block.supplier.id);
      await this.$nextTick();
      window.location.href = mailtoLink(block.supplier.email, block.subject, block.body, this.cc, {
        devMode: this.devMode,
        devEmail: this.devEmail,
      });
    },
    resolveMailParams(block) {
      const isWebshop = block.supplier.order_type === 'webshop';
      const to = isWebshop ? this.cc : block.supplier.email;
      const subject = isWebshop
        ? `[Webshop] ${block.subject} – ${block.supplier.name}`
        : block.subject;
      const cc = isWebshop ? '' : this.cc;
      const attachPdf = !!block.supplier.attach_pdf;
      return {
        to, subject, body: block.body, cc,
        attach_pdf: attachPdf,
        supplier_name: attachPdf ? block.supplier.name : undefined,
        target_date: attachPdf ? (block.deliveryDate || this.targetDate) : undefined,
        lines: attachPdf ? block.lines : undefined,
        free_lines: attachPdf ? (block.freeLines || []) : undefined,
        note: attachPdf ? (block.note || '') : undefined,
      };
    },
    async sendBlock(block) {
      const sid = block.supplier.id;
      if (this.sendStatus[sid] === 'sending') return;
      const params = this.resolveMailParams(block);
      if (!params.to) {
        showToast('Kein Empfänger');
        return;
      }
      this.sendStatus[sid] = 'sending';
      try {
        await api.sendMail(params);
        this.sendStatus[sid] = 'sent';
        const ccStr = String(params.cc || '').trim();
        showToast(
          ccStr
            ? `Server hat angenommen: an ${params.to}, CC ${ccStr}`
            : `Server hat angenommen: an ${params.to}`,
        );
      } catch (e) {
        this.sendStatus[sid] = 'error';
        showToast(e.message || 'Versand fehlgeschlagen', 60000, true);
      }
    },
    async sendAllBlocks() {
      this.sendingAll = true;
      let ok = 0;
      let fail = 0;
      for (const block of this.sendableBlocks) {
        if (this.sendStatus[block.supplier.id] === 'sent') {
          ok++;
          continue;
        }
        await this.sendBlock(block);
        if (this.sendStatus[block.supplier.id] === 'sent') ok++;
        else fail++;
        await new Promise((r) => setTimeout(r, 300));
      }
      this.sendingAll = false;
      if (fail === 0) {
        showToast(`Alle ${ok} Mails gesendet!`);
      } else if (ok > 0) {
        showToast(`${ok} gesendet, ${fail} fehlgeschlagen.`, 15000, true);
      }
      /* Wenn alle fehlschlagen: keine kurze End-Meldung — die letzte ausführliche Fehlermeldung bleibt sichtbar (60s). */
    },
    async pdfBlock(block) {
      try {
        const blob = await api.downloadSupplierPdf({
          supplier_name: block.supplier.name,
          target_date: block.deliveryDate || this.targetDate,
          lines: block.lines,
          free_lines: block.freeLines || [],
          note: block.note,
        });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bestellung-${block.supplier.name.replace(/[^a-z0-9_-]+/gi, '_')}.pdf`;
        a.click();
        URL.revokeObjectURL(url);
      } catch (e) {
        alert(e.message || 'PDF fehlgeschlagen');
      }
    },
    async finalizeDone() {
      if (!window.confirm('Bestellrunde wirklich abschließen? Es gibt keine automatische Versandbestätigung – dies ist nur eine lokale Bestätigung.')) {
        return;
      }
      await storage.setOrderRoundStatus('finalized');
      this.finalized = true;
    },
    async newRound() {
      if (!window.confirm('Neue Runde starten? Die aktuelle Bestellrunde und alle lokalen Eingaben werden gelöscht.')) {
        return;
      }
      await storage.clearOrderRound();
      window.location.href = '/';
    },
  }));
}
