import * as api from './api.js';
import * as storage from './storage.js';
import * as orch from './order-round.js';
import * as pendingSync from './pending-sync.js';
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

/** Ohne Jahr – für Fließtext, wo das Jahr aus dem Zusammenhang klar ist. */
function formatDeDayMonth(iso) {
  if (!iso) return '';
  const [, m, day] = iso.split('-');
  return `${day}.${m}.`;
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

const MAILTO_SOFT_LIMIT = 1800;

/** Anzahl ungültiger Tokens in einem CC-String (Komma/Semikolon). */
function countDiscardedCc(raw) {
  const s = String(raw ?? '').trim();
  if (s === '') return 0;
  const parts = s.split(/[,;]/).map((p) => p.trim()).filter(Boolean);
  let discarded = 0;
  for (const p of parts) {
    const m = p.match(/<([^>]+)>/);
    const e = (m ? m[1] : p).trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e)) discarded += 1;
  }
  return discarded;
}

function warnIfMailtoLong(href) {
  if (String(href).length > MAILTO_SOFT_LIMIT) {
    showToast(
      'Der Mail-Text ist sehr lang. Falls das Programm nichts öffnet: Kopieren und selbst einfügen.',
      8000,
      'warn',
    );
  }
}

/**
 * @param {{ targetDate: string, loading: boolean, error: string }} ctx
 */
async function executeLoadPreparedRound(ctx) {
  ctx.error = '';
  ctx.loading = true;
  try {
    if (!navigator.onLine) {
      throw new Error('Bestellung beginnen geht nur online.');
    }
    if (await storage.prepareReloadWouldEraseLocalProgress()) {
      const ok = window.confirm(
        'Es läuft schon eine Bestellung.\n\n' +
          'Neu beginnen löscht alle Eingaben.\n' +
          'Zum Weitermachen den Button oben nutzen.\n\n' +
          'Trotzdem neu beginnen?',
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

    window.location.href = '/order/round';
  } catch (e) {
    ctx.error = e.message || 'Fehler';
  } finally {
    ctx.loading = false;
  }
}

let deliveryPreviewTimer = null;

/** @param {ReturnType<typeof dashboardPageData>} ctx */
async function refreshDeliveryPreview(ctx) {
  if (!ctx.targetDate) {
    return;
  }
  if (!navigator.onLine) {
    ctx.deliveryPreview = [];
    ctx.previewOffline = true;
    ctx.previewLoading = false;
    ctx.previewError = '';
    return;
  }
  ctx.previewOffline = false;
  ctx.previewLoading = true;
  ctx.previewError = '';
  try {
    const data = await api.fetchDeliveryPreview(ctx.targetDate);
    ctx.deliveryPreview = (data.suppliers || []).map((s) => ({
      id: s.id,
      name: s.name,
      order_type: s.order_type,
      deliveryDate: s.delivery_date,
      onTarget: !!s.on_target,
    }));
  } catch (e) {
    ctx.previewError = e.message || 'Liefer-Vorschau nicht verfügbar';
    ctx.deliveryPreview = [];
  } finally {
    ctx.previewLoading = false;
  }
}

/** @param {ReturnType<typeof dashboardPageData>} ctx */
function scheduleDeliveryPreview(ctx) {
  clearTimeout(deliveryPreviewTimer);
  deliveryPreviewTimer = setTimeout(() => refreshDeliveryPreview(ctx), 300);
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
    deliveryPreview: [],
    previewLoading: false,
    previewError: '',
    previewOffline: false,
    get roundStatusLabel() {
      const m = {
        prepared: 'Vorbereitet – Rundgang noch nicht begonnen',
        active: 'Rundgang läuft',
        ready_for_review: 'Bereit zur Kontrolle',
        finalized: 'Abgeschlossen',
        paused: 'Rundgang pausiert',
      };
      return m[this.roundStatus] || '';
    },
    /** Eine Bestellung, an der noch gearbeitet wird – nicht abgeschlossen. */
    get roundInProgress() {
      return this.hasRound && this.roundStatus !== 'finalized' && this.roundStatus !== 'idle';
    },
    /**
     * Nächste Schritte der laufenden Bestellung, wichtigster zuerst.
     * Aus dem Status abgeleitet, damit die Startseite genau eine
     * Hauptaktion anbietet statt drei gleichrangiger Buttons.
     */
    get roundActions() {
      const round = { href: '/order/round', label: 'Rundgang fortsetzen' };
      const review = { href: '/order/review', label: 'Kontrolle' };
      const output = { href: '/order/output', label: 'Versand' };
      switch (this.roundStatus) {
        case 'prepared':
          return [{ ...round, label: 'Rundgang beginnen' }];
        case 'active':
        case 'paused':
          return [round, review];
        case 'ready_for_review':
          return [review, output];
        default:
          return [];
      }
    },
    /** Kurzfassung der Liefer-Vorschau für die eingeklappte Zeile. */
    get deliverySummary() {
      const total = this.deliveryPreview.length;
      if (!total) return '';
      const onTarget = this.deliveryPreview.filter((s) => s.onTarget).length;
      const datePart = this.targetDate ? ` am ${formatDeDayMonth(this.targetDate)}` : '';
      if (onTarget === total) {
        return `Alle ${total} Lieferanten liefern${datePart}`;
      }
      return `${onTarget} von ${total} Lieferanten liefern${datePart}`;
    },
    async init() {
      const dateIn = document.getElementById('target_date');
      if (dateIn instanceof HTMLInputElement && dateIn.value) {
        this.targetDate = dateIn.value;
      }
      const row = await storage.getOrderRound();
      this.hasRound = !!row;
      this.roundStatus = row?.status || 'idle';
      this.initialized = true;
      this.$watch('targetDate', () => scheduleDeliveryPreview(this));
      scheduleDeliveryPreview(this);
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
    freeUnit: '',
    freeSupplierId: '',
    suppliers: [],
    /** @type {Map<number, number[]>} itemId -> Lieferanten-IDs (Priorität) */
    supplierIdsByItem: new Map(),
    supplierNameById: new Map(),
    /** Lieferanten heute ausblenden (nur Anzeige) */
    hiddenSupplierIds: [],
    targetDate: '',
    orderedCount: 0,
    roundReady: false,
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
    get emptyRoundKind() {
      if (this.filteredItems.length > 0) return '';
      if (String(this.search || '').trim() !== '') return 'search';
      if (!this.locations.length) return 'no-loc';
      if (this.activeLocId && this.hiddenSupplierIds.length > 0) {
        const locId = Number(this.activeLocId);
        const inLoc = this.allItems.filter(
          (i) => Number(i.location_id) === locId && Number(i.active) !== 0,
        );
        if (inLoc.length > 0) return 'hidden';
      }
      return 'empty-loc';
    },
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/?open=bestellen';
        return;
      }
      this.targetDate = round.target_date || '';
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
      this.roundReady = true;
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
            'Hinweis: Artikel ist deaktiviert; eingetragene Menge bleibt lokal, bis das Feld geleert oder in der Kontrolle bearbeitet wird.',
            5000,
            'warn',
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
    /**
     * Nach oben springen, sobald der Lagerort wechselt.
     * iOS Safari bricht ein laufendes „smooth“-Scroll ab, wenn sich die
     * Seitenhöhe ändert – und genau das passiert beim Neuaufbau der Liste.
     * Deshalb hart springen, direkt in der Tap-Geste und erneut nach Re-Render
     * und nächstem Frame (dann ist die neue Höhe geklammert).
     */
    scrollToListTop() {
      const jump = () => {
        window.scrollTo(0, 0);
        const el = document.scrollingElement || document.documentElement;
        el.scrollTop = 0;
      };
      jump();
      this.$nextTick(() => {
        jump();
        requestAnimationFrame(jump);
      });
    },
    selectLocation(locationId) {
      this.activeLocId = locationId;
      this.scrollToListTop();
    },
    nextLocation() {
      if (this.locations.length < 2) return;
      const idx = this.locations.findIndex((l) => l.id === this.activeLocId);
      const next = idx >= 0 && idx < this.locations.length - 1 ? this.locations[idx + 1] : this.locations[0];
      this.selectLocation(next.id);
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
      let n = 0;
      for (const e of entries) {
        if (!e.is_free_item && e.item_id != null) {
          this.quantities[e.item_id] = String(e.quantity);
          if (orch.parseQuantity(e.quantity) != null) n += 1;
        } else if (e.is_free_item && String(e.quantity ?? '').trim() !== '') {
          n += 1;
        }
      }
      this.orderedCount = n;
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
      if (!String(this.freeLabel || '').trim() || !String(this.freeQty || '').trim()) {
        showToast('Bezeichnung und Menge angeben.', 2500, 'warn');
        return;
      }
      await orch.addFreeLine(
        this.activeLocId,
        this.freeLabel,
        this.freeQty,
        this.freeSupplierId || null,
        this.freeUnit,
      );
      this.freeLabel = '';
      this.freeQty = '';
      this.freeUnit = '';
      this.freeSupplierId = '';
      await this.reloadEntries();
      showToast('Freier Artikel hinzugefügt.');
    },
    formatDate(iso) {
      return formatDeDate(iso);
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
    targetDate: '',
    positionCount: 0,
    freeDraftOpen: null,
    freeDraft: { label: '', qty: '', unit: '' },
    get reviewIsEmpty() {
      return (
        this.problemLines.length === 0 &&
        this.pendingFreeLines.length === 0 &&
        this.groups.length === 0
      );
    },
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/?open=bestellen';
        return;
      }
      this.targetDate = round.target_date || '';
      this.loading = true;
      try {
        await this._mergeServerIfOnline(round.target_date);
        await this._rebuildReviewUi();
        // Freitext-Artikel in die gemeinsame Sammlung schieben. Die Kontrolle ist
        // der erste Schritt nach dem Rundgang, an dem verlässlich Netz besteht –
        // und der einzige, den auch „Nur Bestellen“-Nutzer erreichen.
        pendingSync.pushPendingOutbox();
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
      this.positionCount = entries.length;
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
              unit: e.free_unit || '',
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
            unit: e.free_unit || '',
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
            entryId: e.id,
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
        showToast('Nur online möglich.', 2500, 'warn');
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
    activeSupplierOptions() {
      return this.allSuppliers
        .filter((s) => Number(s.active) !== 0)
        .map((s) => {
          const date = this.deliveryMap.get(Number(s.id));
          const dateLabel = date ? `Lieferung ${formatDeDate(date)}` : '';
          const label = dateLabel ? `${s.name} (${formatDeDate(date)})` : s.name;
          return { id: s.id, name: s.name, label, dateLabel };
        });
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
    toggleFreeDraft(supplierId) {
      if (this.freeDraftOpen === supplierId) {
        this.freeDraftOpen = null;
        return;
      }
      this.freeDraftOpen = supplierId;
      this.freeDraft = { label: '', qty: '', unit: '' };
    },
    async submitFreeDraft(supplierId) {
      if (!String(this.freeDraft.label || '').trim() || !String(this.freeDraft.qty || '').trim()) {
        showToast('Bezeichnung und Menge angeben.', 2500, 'warn');
        return;
      }
      await orch.addFreeLine(
        null,
        this.freeDraft.label,
        this.freeDraft.qty,
        supplierId,
        this.freeDraft.unit,
      );
      this.freeDraftOpen = null;
      this.freeDraft = { label: '', qty: '', unit: '' };
      showToast('Freier Artikel hinzugefügt.');
      await this.rebuildLocal();
    },
    async assignProblemSupplier(p, sid) {
      const v = String(sid ?? '').trim();
      if (!v || p?.entry?.id == null) return;
      await storage.updateOrderEntry(p.entry.id, { selected_supplier_id: Number(v) });
      await this.rebuildLocal();
    },
    async removeProblem(p) {
      const id = p?.entryId ?? p?.entry?.id;
      if (id == null) return;
      await storage.deleteOrderEntry(id);
      await this.rebuildLocal();
    },
    goOutput() {
      if (this.pendingFreeLines.length > 0) {
        showToast('Bitte zuerst alle freien Artikel einem Lieferanten zuordnen.', 2500, 'warn');
        return;
      }
      if (this.problemLines.length > 0) {
        showToast('Bitte zuerst die Problemartikel beheben.', 2500, 'warn');
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
    ccDiscardWarned: false,
    hasUnresolved: false,
    positionCount: 0,
    outputReady: false,
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
        return this.mailtoOpened(block) ? 'Nochmal' : 'Mail-Programm';
      }
      if (block.supplier.order_type === 'webshop') {
        return this.mailtoOpened(block) ? 'Nochmal' : 'Liste mailen';
      }
      return this.mailtoOpened(block) ? 'Nochmal' : 'Mail öffnen';
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
      if (s === 'sent') return 'Erneut';
      if (s === 'sending') return 'Sende …';
      if (s === 'error') return 'Nochmal senden';
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
      return this.blockCollapsed(block) ? 'Anzeigen' : 'Zuklappen';
    },
    blockStatusLabel(block) {
      const s = this.blockSendState(block);
      if (s === 'sending') return 'Sende …';
      if (s === 'sent') return 'Gesendet';
      if (s === 'error') return 'Fehlgeschlagen';
      if (this.mailtoOpened(block)) return 'Geöffnet';
      return '';
    },
    blockStatusClass(block) {
      const s = this.blockSendState(block);
      if (s === 'error') return 'status-badge--warn';
      if (s === 'sent' || this.mailtoOpened(block)) return 'status-badge--ok';
      return 'status-badge--neutral';
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
        showToast('Fertig. Orange markierte Lieferanten prüfen, falls eine Mail fehlt.', 6000, 'warn');
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
        showToast('Kopieren fehlgeschlagen', 2500, 'error');
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
      if (this.hasUnresolved && !this.finalized) {
        window.location.replace('/order/review');
        return;
      }
      this.outputReady = true;
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
      if (!this.ccDiscardWarned) {
        const discarded = countDiscardedCc(this.cc);
        if (discarded > 0) {
          this.ccDiscardWarned = true;
          showToast(
            discarded === 1
              ? 'Eine CC-Adresse ist ungültig und wird nicht mitgeschickt.'
              : `${discarded} CC-Adressen sind ungültig und werden nicht mitgeschickt.`,
            2500,
            'warn',
          );
        }
      }

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
      let unresolved = 0;
      for (const e of entries) {
        if (e.is_free_item) {
          const sid = e.free_supplier_id || e.selected_supplier_id;
          if (!sid) {
            unresolved += 1;
            continue;
          }
          if (!bySup.has(sid)) bySup.set(sid, []);
          if (!freesBySup.has(sid)) freesBySup.set(sid, []);
          const freeUnit = String(e.free_unit ?? '').trim();
          const freeLabel = String(e.free_label ?? '').trim();
          freesBySup
            .get(sid)
            .push(
              `${String(e.quantity ?? '').trim()}x ${freeUnit ? `${freeUnit} ` : ''}${freeLabel}`,
            );
          continue;
        }
        const item = itemMap.get(e.item_id);
        if (!item) continue;
        const itemLinks = byItem.get(e.item_id) || [];
        const pick = pickSupplierForItem(e.item_id, itemLinks, delivering, e.selected_supplier_id);
        const sid = pick.supplierId;
        if (sid == null) {
          unresolved += 1;
          continue;
        }
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
      this.hasUnresolved = unresolved > 0;
      this.positionCount = this.blocks.reduce(
        (n, b) => n + (b.lines?.length || 0) + (b.freeLines?.length || 0),
        0,
      );
    },
    async refreshStammdaten() {
      if (!navigator.onLine) {
        showToast('Nur online möglich.', 2500, 'warn');
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
        showToast('Kopieren fehlgeschlagen', 2500, 'error');
      }
    },
    async mailtoBlock(block) {
      if (block.supplier.order_type === 'webshop') {
        if (!this.cc) {
          showToast('Keine CC-Adresse für Webshop-Mail konfiguriert', 2500, 'warn');
          return;
        }
        const subj = `[Webshop] ${block.subject} – ${block.supplier.name}`;
        this.touchMailtoOpened(block.supplier.id);
        await this.$nextTick();
        const href = mailtoLink(this.cc, subj, block.body, '', {
          devMode: this.devMode,
          devEmail: this.devEmail,
        });
        warnIfMailtoLong(href);
        window.location.href = href;
        return;
      }
      if (!block.supplier.email) return;
      this.touchMailtoOpened(block.supplier.id);
      await this.$nextTick();
      const href = mailtoLink(block.supplier.email, block.subject, block.body, this.cc, {
        devMode: this.devMode,
        devEmail: this.devEmail,
      });
      warnIfMailtoLong(href);
      window.location.href = href;
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
        showToast('Kein Empfänger', 2500, 'warn');
        return;
      }
      this.sendStatus[sid] = 'sending';
      try {
        const data = await api.sendMail(params);
        this.sendStatus[sid] = 'sent';
        const ccStr = String(params.cc || '').trim();
        const discarded = Number(data.cc_discarded || 0);
        let msg = ccStr
          ? `Server hat angenommen: an ${params.to}, CC ${ccStr}`
          : `Server hat angenommen: an ${params.to}`;
        if (discarded > 0) {
          msg += discarded === 1
            ? ' · eine CC-Adresse nicht übernommen'
            : ` · ${discarded} CC-Adressen nicht übernommen`;
        }
        showToast(msg, 2500, discarded > 0 ? 'warn' : 'success');
      } catch (e) {
        this.sendStatus[sid] = 'error';
        showToast(e.message || 'Versand fehlgeschlagen', 60000, 'error');
      }
    },
    async sendAllBlocks() {
      if (this.sendingAll) return;
      this.sendingAll = true;
      let ok = 0;
      let fail = 0;
      try {
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
      } finally {
        this.sendingAll = false;
      }
      if (fail === 0) {
        showToast(`Alle ${ok} Mails gesendet!`);
      } else if (ok > 0) {
        showToast(`${ok} gesendet, ${fail} fehlgeschlagen.`, 15000, 'error');
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
      if (!window.confirm('Bestellung abschließen? Das ist nur lokal – kein Versandnachweis.')) {
        return;
      }
      await storage.setOrderRoundStatus('finalized');
      this.finalized = true;
    },
    async newRound() {
      if (!window.confirm('Neue Bestellung starten? Die aktuelle und alle lokalen Eingaben werden gelöscht.')) {
        return;
      }
      await storage.clearOrderRound();
      window.location.href = '/';
    },
  }));
}
