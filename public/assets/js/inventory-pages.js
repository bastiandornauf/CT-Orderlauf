import * as api from './api.js';
import * as invStorage from './inventory-storage.js';
import * as orderStorage from './storage.js';
import * as invRound from './inventory-round.js';
import { buildInventoryCsv, downloadCsv } from './inventory-csv.js';
import { showToast } from './toast.js';

function todayIso() {
  const d = new Date();
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

/**
 * Alle noch offenen Freitext-Positionen aus Inventur (lokaler Store) und
 * Bestellung (order_entries) einsammeln – Quelle für die Sammelliste „Neue Artikel".
 * @returns {Promise<object[]>}
 */
async function collectPendingFreeItems() {
  const list = [];
  const suppliers = await orderStorage.getAllSuppliers();
  const supById = new Map(suppliers.map((s) => [Number(s.id), String(s.name || '')]));

  const invFree = await invStorage.getInventoryFreeItems();
  for (const f of invFree) {
    if (Number(f.transferred) === 1) continue;
    list.push({
      key: `inv-${f.id}`,
      source: 'inventory',
      sourceId: f.id,
      sourceLabel: 'Inventur',
      name: f.name || '',
      unit: f.unit || '',
      quantity: f.quantity,
      location_id: f.location_id != null ? Number(f.location_id) : null,
      supplier_id: null,
      busy: false,
    });
  }

  const entries = await orderStorage.getOrderEntries();
  for (const e of entries) {
    if (!e.is_free_item || e.transferred_to_item_id) continue;
    const label = String(e.free_label || '').trim();
    if (label === '') continue;
    const rawSid = e.free_supplier_id ?? e.selected_supplier_id;
    const supplierId =
      rawSid != null && rawSid !== '' && Number(rawSid) > 0 ? Number(rawSid) : null;
    list.push({
      key: `ord-${e.id}`,
      source: 'order',
      sourceId: e.id,
      sourceLabel: 'Bestellung',
      name: label,
      unit: '',
      quantity: e.quantity,
      location_id: e.location_id != null ? Number(e.location_id) : null,
      supplier_id: supplierId,
      supplier_label: supplierId ? supById.get(supplierId) || '' : '',
      busy: false,
    });
  }

  return list;
}

/** @param {object} entry Quelle als erledigt markieren (übernommen/ausgeblendet) */
async function markPendingFreeItemDone(entry) {
  if (entry.source === 'inventory') {
    await invStorage.updateInventoryFreeItem(entry.sourceId, { transferred: 1 });
  } else {
    await orderStorage.updateOrderEntry(entry.sourceId, { transferred_to_item_id: 1 });
  }
}

/** @param {object} session */
async function tryRepairInventoryCatalog(session) {
  if (!navigator.onLine) {
    return false;
  }
  try {
    const data = await api.fetchInventoryPayload(session.stichtag, session.label);
    if (!Array.isArray(data.locations) || !Array.isArray(data.items)) {
      return false;
    }
    await invStorage.refillInventoryCatalog({
      locations: data.locations,
      items: data.items,
    });
    const { itemCount, locationCount } = await invStorage.getInventoryCatalogCounts();
    return itemCount > 0 && locationCount > 0;
  } catch {
    return false;
  }
}

/**
 * @param {{ session: object|null, removedInvalid: boolean, catalogInvalid: boolean }} ui
 * @returns {Promise<object|null>}
 */
async function resolveInventorySessionForPage(ui) {
  let session = ui.session;
  if (!session || !ui.catalogInvalid) {
    return session;
  }
  if (await tryRepairInventoryCatalog(session)) {
    return invStorage.getInventorySession();
  }
  if (session.status === 'finalized') {
    return session;
  }
  await invStorage.discardBrokenInventorySession();
  return null;
}

/** Inventur-Startseite (/inventory) */
export function inventoryHomePageData() {
  return {
    initialized: false,
    hasInventory: false,
    inventoryStatus: 'idle',
    invStichtag: todayIso(),
    invLabel: '',
    invLoading: false,
    invError: '',
    orderRoundActive: false,
    clearing: false,
    get canContinue() {
      return (
        this.hasInventory &&
        this.inventoryStatus !== 'finalized' &&
        ['prepared', 'active'].includes(this.inventoryStatus)
      );
    },
    get canGoFinalize() {
      return this.hasInventory && this.inventoryStatus !== 'finalized';
    },
    get isFinalized() {
      return this.hasInventory && this.inventoryStatus === 'finalized';
    },
    get inventoryStatusLabel() {
      const m = {
        prepared: 'Vorbereitet – Rundgang noch nicht begonnen',
        active: 'Inventur läuft',
        finalized: 'Abgeschlossen – neue Inventur möglich',
      };
      return m[this.inventoryStatus] || '';
    },
    async init() {
      try {
        const orderRow = await orderStorage.getOrderRound();
        this.orderRoundActive =
          !!orderRow &&
          orderRow.status !== 'finalized' &&
          orderRow.status !== 'idle';
        const ui = await invStorage.loadInventorySessionForUi();
        const inv = await resolveInventorySessionForPage(ui);
        this.hasInventory = !!inv;
        this.inventoryStatus = inv?.status || 'idle';
        if (inv?.stichtag) {
          this.invStichtag = inv.stichtag;
        }
        if (inv?.label) {
          this.invLabel = inv.label;
        }
        if (ui.catalogInvalid && inv) {
          showToast('Artikelkatalog wurde nachgeladen.', 5000);
        }
        if (ui.catalogInvalid && !inv) {
          showToast(
            'Eine defekte Inventur (ohne Artikel) wurde entfernt. Bitte erneut „Inventur starten“.',
            7000,
            true,
          );
        }
      } catch (e) {
        this.invError =
          e?.message || 'Inventur-Status konnte nicht gelesen werden. Seite neu laden (F5).';
        showToast(this.invError, 6000, true);
      } finally {
        this.initialized = true;
      }
    },
    async clearLocalInventory() {
      const ok = window.confirm(
        'Lokale Inventur-Daten auf diesem Gerät löschen?\n\n' +
          'Die CSV in Ihren Downloads bleibt erhalten. Sie können danach eine neue Inventur starten.',
      );
      if (!ok) return;
      this.clearing = true;
      this.invError = '';
      try {
        await invStorage.clearInventorySession();
        this.hasInventory = false;
        this.inventoryStatus = 'idle';
        showToast('Inventur lokal beendet.');
      } catch (e) {
        const msg = e?.message || 'Löschen fehlgeschlagen';
        this.invError = msg;
        showToast(msg, 6000, true);
      } finally {
        this.clearing = false;
      }
    },
    async loadInventory() {
      this.invError = '';
      this.invLoading = true;
      try {
          if (!navigator.onLine) {
            throw new Error('Inventur-Vorbereitung nur online möglich.');
          }
          const existing = await invStorage.getInventorySession();
          if (existing) {
            let detail =
              'Die bestehende lokale Inventur wird vollständig ersetzt (alle Zählungen weg).';
            if (existing.status === 'finalized') {
              detail =
                'Die abgeschlossene Inventur wird gelöscht und durch eine neue ersetzt.';
            } else if (await invStorage.prepareInventoryReloadWouldEraseProgress()) {
              detail =
                'Es gibt bereits gezählte Positionen. Alle lokalen Inventur-Daten werden gelöscht.';
            }
            const ok = window.confirm(
              `${detail}\n\nZum Fortsetzen einer laufenden Inventur: oben „Inventur fortsetzen“.\n\nTrotzdem neu starten?`,
            );
            if (!ok) return;
          }
          const data = await api.fetchInventoryPayload(this.invStichtag, this.invLabel);
          if (!Array.isArray(data.locations) || !Array.isArray(data.items)) {
            throw new Error('Unvollständige Server-Daten (Lagerorte/Artikel fehlen).');
          }
          await invStorage.saveInventorySnapshot({
            stichtag: data.stichtag,
            label: data.label || this.invLabel,
            locations: data.locations,
            items: data.items,
          });
          const { itemCount, locationCount } = await invStorage.getInventoryCatalogCounts();
          if (itemCount === 0 || locationCount === 0) {
            throw new Error(
              'Inventur-Katalog leer gespeichert. Bitte erneut versuchen oder Stammdaten prüfen.',
            );
          }
          showToast('Inventur geladen – Rundgang startet …');
          window.location.href = '/inventory/round';
        } catch (e) {
          const name = e?.name || '';
          const msg = e?.message || 'Unbekannter Fehler';
          if (name === 'NotFoundError' || /object store|IDBDatabase/i.test(msg)) {
            this.invError =
              'Browser-Datenbank nicht bereit. Seite einmal vollständig neu laden (F5), dann erneut „Inventur starten“.';
          } else {
            this.invError = msg;
          }
          showToast(this.invError, 6000, true);
        } finally {
          this.invLoading = false;
        }
    },
    formatInvDate(iso) {
      return formatDeDate(iso);
    },
  };
}

/** @param {any} Alpine */
export function registerInventoryAlpine(Alpine) {
  Alpine.data('inventoryHomePage', () => inventoryHomePageData());
  Alpine.data('inventoryRoundPage', () => ({
    pageReady: false,
    pageError: '',
    locations: [],
    activeLocId: null,
    items: [],
    allItems: [],
    search: '',
    /** @type {Record<number, 'open'|'counted'>} */
    lineStatus: {},
    /** @type {Record<number, string>} Anzeige Menge bei counted > 0 */
    displayQty: {},
    orderRoundActive: false,
    progress: { counted: 0, total: 0, open: 0 },
    /** @type {object[]} freie Artikel im aktuellen Lagerort */
    freeItems: [],
    freeLabel: '',
    freeUnit: '',
    freeQty: '',
    freeError: '',
    async init() {
      this.pageError = '';
      try {
        const ui = await invStorage.loadInventorySessionForUi();
        const session = await resolveInventorySessionForPage(ui);
        if (!session) {
          showToast(
            ui.catalogInvalid
              ? 'Inventur ohne Artikelkatalog. Bitte auf der Startseite neu starten.'
              : 'Keine Inventur aktiv.',
            6000,
            true,
          );
          window.location.href = '/inventory';
          return;
        }
        if (session.status === 'finalized') {
          window.location.href = '/inventory/finalize';
          return;
        }
        const orderRow = await orderStorage.getOrderRound();
        this.orderRoundActive =
          !!orderRow &&
          orderRow.status !== 'finalized' &&
          orderRow.status !== 'idle';
        if (session.status === 'prepared') {
          await invStorage.setInventorySessionStatus('active');
        }
        this.allItems = await invStorage.getAllInventoryCatalogItems();
        this.locations = await invStorage.getInventoryCatalogLocationsSorted();
        if (this.allItems.length === 0 || this.locations.length === 0) {
          this.pageError =
            'Keine Artikel oder Lagerorte im lokalen Katalog. Zurück zur Startseite und „Inventur starten“ (online).';
          return;
        }
        const firstWithItems = this.locations.find((loc) =>
          this.allItems.some((i) => Number(i.location_id) === Number(loc.id)),
        );
        this.activeLocId = firstWithItems?.id ?? this.locations[0]?.id ?? null;
        await this.reloadLines();
        this.$watch('activeLocId', () => this.loadItemsForTab());
        await this.loadItemsForTab();
        if (ui.catalogInvalid) {
          showToast('Artikelkatalog nachgeladen.', 4000);
        }
      } catch (e) {
        this.pageError =
          e?.message || 'Rundgang konnte nicht geladen werden. Seite neu laden (F5).';
        showToast(this.pageError, 6000, true);
      } finally {
        this.pageReady = true;
      }
    },
    get filteredItems() {
      const q = this.search.trim().toLowerCase();
      if (q !== '') {
        return this.allItems.filter((it) =>
          `${it.name || ''} ${it.unit || ''}`.toLowerCase().includes(q),
        );
      }
      if (this.items.length > 0) {
        return this.items;
      }
      return this.allItems;
    },
    async reloadLines() {
      const lines = await invStorage.getInventoryLines();
      this.lineStatus = {};
      this.displayQty = {};
      let counted = 0;
      for (const l of lines) {
        const id = Number(l.item_id);
        if (l.status === 'counted') {
          this.lineStatus[id] = 'counted';
          counted += 1;
          const q = Number(l.quantity);
          this.displayQty[id] = q === 0 ? '0' : String(q);
        }
      }
      const total = this.allItems.length;
      this.progress = { counted, total, open: Math.max(0, total - counted) };
    },
    async loadItemsForTab() {
      if (!this.activeLocId) {
        this.items = [];
        this.freeItems = [];
        return;
      }
      this.items = await invStorage.getInventoryCatalogItemsByLocation(this.activeLocId);
      await this.loadFreeItems();
    },
    async loadFreeItems() {
      if (!this.activeLocId) {
        this.freeItems = [];
        return;
      }
      this.freeItems = await invStorage.getInventoryFreeItemsByLocation(this.activeLocId);
    },
    async addInventoryFree() {
      this.freeError = '';
      if (this.freeLabel.trim() === '') {
        this.freeError = 'Bitte eine Bezeichnung eingeben.';
        return;
      }
      const ok = await invStorage.addInventoryFreeItem({
        name: this.freeLabel,
        unit: this.freeUnit,
        quantity: this.freeQty,
        location_id: this.activeLocId,
      });
      if (!ok) {
        this.freeError = 'Speichern nicht möglich (Inventur gesperrt?).';
        return;
      }
      this.freeLabel = '';
      this.freeUnit = '';
      this.freeQty = '';
      await this.loadFreeItems();
      showToast('Freier Artikel erfasst.', 3000);
    },
    async deleteFreeItem(id) {
      await invStorage.deleteInventoryFreeItem(id);
      await this.loadFreeItems();
    },
    freeQtyLabel(fi) {
      const q = fi.quantity == null ? '' : String(fi.quantity).replace('.', ',');
      const u = fi.unit ? ` ${fi.unit}` : '';
      return `${q}${u}`.trim();
    },
    isCounted(itemId) {
      return this.lineStatus[Number(itemId)] === 'counted';
    },
    isCountedZero(itemId) {
      if (!this.isCounted(itemId)) return false;
      const q = invRound.parseInventoryQuantity(this.displayQty[Number(itemId)] ?? '0');
      return q === 0;
    },
    tabLabel(loc) {
      const n = this.allItems.filter((i) => Number(i.location_id) === Number(loc.id)).length;
      const counted = this.allItems.filter(
        (i) =>
          Number(i.location_id) === Number(loc.id) &&
          this.lineStatus[Number(i.id)] === 'counted',
      ).length;
      return `${loc.name} (${counted}/${n})`;
    },
    valuationHint(it) {
      const p = it.valuation_price;
      if (p == null || p === '') return '';
      return `Bewertung ${String(p).replace('.', ',')} €/${it.unit || 'Einh.'}`;
    },
    nextLocation() {
      if (this.locations.length < 2) return;
      const idx = this.locations.findIndex((l) => l.id === this.activeLocId);
      const next = idx >= 0 && idx < this.locations.length - 1 ? this.locations[idx + 1] : this.locations[0];
      this.activeLocId = next.id;
      this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    },
    nextLocationLabel() {
      if (this.locations.length < 2) return '';
      const idx = this.locations.findIndex((l) => l.id === this.activeLocId);
      const next = idx >= 0 && idx < this.locations.length - 1 ? this.locations[idx + 1] : this.locations[0];
      return next?.name || '';
    },
    async markZero(itemId) {
      const id = Number(itemId);
      const wasZero = this.isCountedZero(id);
      try {
        if (wasZero) {
          await invStorage.saveInventoryLine(id, 'open');
          delete this.displayQty[id];
          delete this.lineStatus[id];
        } else {
          await invStorage.saveInventoryLine(id, 'counted', 0);
          this.lineStatus[id] = 'counted';
          this.displayQty[id] = '0';
        }
        await this.reloadLines();
      } catch (e) {
        showToast(e?.message || 'Speichern fehlgeschlagen', 5000, true);
      }
    },
    async onQtyBlur(itemId, event) {
      const v = event.target.value;
      await invRound.saveCountedQuantity(itemId, v);
      await this.reloadLines();
    },
    async onQtyStep(itemId, delta) {
      let current = invRound.parseInventoryQuantity(this.displayQty[itemId] || '');
      if (current === null) current = 0;
      const next = Math.max(0, current + delta);
      if (next === 0) {
        await invRound.saveCountedQuantity(itemId, '');
        await this.reloadLines();
        return;
      }
      const val = String(next % 1 === 0 ? next : Number(next.toFixed(2)));
      this.displayQty[itemId] = val;
      await invRound.saveCountedQuantity(itemId, val);
      await this.reloadLines();
    },
    async goFinalize() {
      await invStorage.setInventorySessionStatus('active');
      window.location.href = '/inventory/finalize';
    },
  }));

  Alpine.data('inventoryFinalizePage', () => ({
    pageReady: false,
    pageError: '',
    session: null,
    stats: { counted: 0, open: 0, totalValue: 0, hasValue: false },
    /** @type {{ id: number, name: string, unit: string, location_name: string }[]} */
    openItems: [],
    orderRoundActive: false,
    exporting: false,
    clearing: false,
    csvFallbackUrl: '',
    csvFallbackName: '',
    hasNewItems: false,
    newItemCount: 0,
    get isLocked() {
      return this.session?.status === 'finalized';
    },
    async init() {
      this.pageError = '';
      try {
        const ui = await invStorage.loadInventorySessionForUi();
        const session = await resolveInventorySessionForPage(ui);
        if (!session) {
          showToast('Keine gültige Inventur – bitte neu starten.', 6000, true);
          window.location.href = '/inventory';
          return;
        }
        this.session = session;
        const orderRow = await orderStorage.getOrderRound();
        this.orderRoundActive =
          !!orderRow &&
          orderRow.status !== 'finalized' &&
          orderRow.status !== 'idle';
        await this.refreshStats();
        await this.loadOpenItems();
        await this.countNewItems();
        if (ui.catalogInvalid) {
          showToast('Artikelkatalog nachgeladen.', 4000);
        }
        if (this.stats.counted === 0 && this.stats.open === 0) {
          this.pageError =
            'Kein Artikel im Katalog. „Inventur starten“ auf der Startseite (online).';
        }
      } catch (e) {
        this.pageError =
          e?.message || 'Abschluss konnte nicht geladen werden. Seite neu laden (F5).';
        showToast(this.pageError, 6000, true);
      } finally {
        this.pageReady = true;
      }
    },
    async loadOpenItems() {
      const items = await invStorage.getAllInventoryCatalogItems();
      const lines = await invStorage.getInventoryLines();
      const countedIds = new Set(
        lines.filter((l) => l.status === 'counted').map((l) => Number(l.item_id)),
      );
      const locs = await invStorage.getInventoryCatalogLocationsSorted();
      const locById = new Map(locs.map((l) => [Number(l.id), l.name]));
      this.openItems = items
        .filter((i) => !countedIds.has(Number(i.id)))
        .map((i) => ({
          id: Number(i.id),
          name: i.name,
          unit: i.unit || '',
          location_name: locById.get(Number(i.location_id)) || '',
        }))
        .sort(
          (a, b) =>
            a.location_name.localeCompare(b.location_name, 'de') ||
            a.name.localeCompare(b.name, 'de'),
        );
    },
    async countNewItems() {
      const items = await collectPendingFreeItems();
      this.newItemCount = items.length;
      this.hasNewItems = items.length > 0;
    },
    async refreshStats() {
      const items = await invStorage.getAllInventoryCatalogItems();
      const lines = (await invStorage.getInventoryLines()).filter((l) => l.status === 'counted');
      const itemById = new Map(items.map((i) => [Number(i.id), i]));
      let totalValue = 0;
      let hasValue = false;
      for (const line of lines) {
        const it = itemById.get(Number(line.item_id));
        if (!it) continue;
        const price =
          it.valuation_price != null && it.valuation_price !== ''
            ? Number(it.valuation_price)
            : null;
        if (price != null && !Number.isNaN(price)) {
          totalValue += Number(line.quantity) * price;
          hasValue = true;
        }
      }
      const counted = lines.length;
      const total = items.length;
      this.stats = {
        counted,
        open: Math.max(0, total - counted),
        totalValue: Math.round(totalValue * 100) / 100,
        hasValue,
      };
    },
    async markOpenAsZero(itemId) {
      if (this.isLocked) return;
      try {
        await invRound.markCountedZero(itemId);
        await this.refreshStats();
        await this.loadOpenItems();
        showToast('Als leer (0) erfasst.');
      } catch (e) {
        showToast(e?.message || 'Speichern fehlgeschlagen', 5000, true);
      }
    },
    async markAllOpenAsZero() {
      if (this.isLocked || this.openItems.length === 0) return;
      const n = this.openItems.length;
      const ok = window.confirm(
        `Alle ${n} noch offenen Artikel als leer (0) erfassen?\n\nEinzeln rückgängig: im Rundgang erneut „Leer / 0“ tippen (wechselt zu „Offen“).`,
      );
      if (!ok) return;
      try {
        for (const it of [...this.openItems]) {
          await invRound.markCountedZero(it.id);
        }
        await this.refreshStats();
        await this.loadOpenItems();
        showToast(`${n} Artikel als leer (0) erfasst.`);
      } catch (e) {
        showToast(e?.message || 'Speichern fehlgeschlagen', 5000, true);
      }
    },
    revokeCsvFallback() {
      if (this.csvFallbackUrl) {
        URL.revokeObjectURL(this.csvFallbackUrl);
        this.csvFallbackUrl = '';
        this.csvFallbackName = '';
      }
    },
    async downloadExport() {
      if (!this.session) {
        showToast('Keine Inventur-Session – bitte neu starten.', 6000, true);
        return;
      }
      if (!this.isLocked && this.stats.open > 0) {
        const ok = window.confirm(
          `Noch ${this.stats.open} Artikel sind nicht gezählt (Status „offen“ in der CSV).\n\n` +
            'Wenn Sie noch etwas nachtragen möchten: Abbrechen und „Zurück zum Rundgang“.\n\n' +
            'Sonst: CSV jetzt exportieren – alle Artikel sind enthalten, Filter in Excel nach Spalte status.',
        );
        if (!ok) return;
      }
      this.exporting = true;
      this.revokeCsvFallback();
      try {
        let session = await invStorage.getInventorySession();
        let items = await invStorage.getAllInventoryCatalogItems();
        if (items.length === 0 && navigator.onLine) {
          const repaired = await tryRepairInventoryCatalog(session);
          if (repaired) {
            session = await invStorage.getInventorySession();
            items = await invStorage.getAllInventoryCatalogItems();
            await this.refreshStats();
            await this.loadOpenItems();
          }
        }
        if (items.length === 0) {
          throw new Error(
            'Kein Artikelkatalog vorhanden. Zur Startseite und „Inventur starten“ (online).',
          );
        }
        const locs = await invStorage.getInventoryCatalogLocationsSorted();
        const lines = await invStorage.getInventoryLines();
        const lineByItemId = new Map(
          lines.filter((l) => l.status === 'counted').map((l) => [Number(l.item_id), l]),
        );
        const locationById = new Map(locs.map((l) => [Number(l.id), l]));
        const freeItems = await invStorage.getInventoryFreeItems();
        const csv = buildInventoryCsv(session, items, lineByItemId, locationById, freeItems);
        const date = session?.stichtag || todayIso();
        const dl = downloadCsv(csv, `Inventur_${date}.csv`);
        this.csvFallbackUrl = dl.url;
        this.csvFallbackName = dl.filename;
        if (!dl.opened) {
          showToast(
            'Automatischer Download blockiert. Bitte den Link „CSV-Datei speichern“ unten tippen.',
            8000,
            true,
          );
        }
        if (!this.isLocked) {
          await invStorage.setInventorySessionStatus('finalized');
          this.session = await invStorage.getInventorySession();
          showToast(
            dl.opened
              ? 'CSV gespeichert – Inventur abgeschlossen.'
              : 'Inventur abgeschlossen – CSV über den Link unten speichern.',
            dl.opened ? 4000 : 8000,
            !dl.opened,
          );
        } else {
          showToast(
            dl.opened ? 'CSV erneut heruntergeladen.' : 'CSV über den Link unten speichern.',
            6000,
            !dl.opened,
          );
        }
      } catch (e) {
        showToast(e?.message || 'Export fehlgeschlagen', 6000, true);
      } finally {
        this.exporting = false;
      }
    },
    async finishAndClear() {
      const ok = window.confirm(
        'Lokale Inventur-Daten auf diesem Gerät löschen?\n\n' +
          'Die CSV-Datei bleibt in Ihren Downloads. Die Bestellrunde wird nicht verändert.',
      );
      if (!ok) return;
      this.clearing = true;
      try {
        this.revokeCsvFallback();
        await invStorage.clearInventorySession();
        showToast('Inventur lokal beendet.');
        window.location.href = '/inventory';
      } catch (e) {
        showToast(e?.message || 'Löschen fehlgeschlagen', 6000, true);
        this.clearing = false;
      }
    },
    formatDe(iso) {
      return formatDeDate(iso);
    },
  }));

  Alpine.data('pendingItemsPage', () => ({
    ready: false,
    canTransfer: false,
    bulkBusy: false,
    /** @type {{id:number,name:string}[]} */
    transferLocations: [],
    /** @type {{id:number,name:string}[]} */
    transferSuppliers: [],
    /** @type {object[]} */
    newItems: [],
    async init() {
      this.canTransfer = this.$el?.dataset?.canEditMaster === '1';
      try {
        this.transferLocations = JSON.parse(this.$el?.dataset?.locations || '[]').map((l) => ({
          id: Number(l.id),
          name: String(l.name),
        }));
      } catch {
        this.transferLocations = [];
      }
      try {
        this.transferSuppliers = JSON.parse(this.$el?.dataset?.suppliers || '[]').map((s) => ({
          id: Number(s.id),
          name: String(s.name),
        }));
      } catch {
        this.transferSuppliers = [];
      }
      await this.loadNewItems();
      this.ready = true;
    },
    async loadNewItems() {
      const defaultLoc = this.transferLocations[0]?.id ?? '';
      const locById = new Map(this.transferLocations.map((l) => [l.id, l.name]));
      const supById = new Map(this.transferSuppliers.map((s) => [s.id, s.name]));
      const list = await collectPendingFreeItems();
      for (const ni of list) {
        const fromLoc = ni.location_id != null ? Number(ni.location_id) : null;
        if (fromLoc && locById.has(fromLoc)) {
          ni.location_id = fromLoc;
          ni.location_from_source = locById.get(fromLoc) || '';
        } else {
          ni.location_id = defaultLoc;
          ni.location_from_source = '';
        }
        if (ni.supplier_id != null && Number(ni.supplier_id) > 0) {
          ni.supplier_id = Number(ni.supplier_id);
          ni.supplier_from_source =
            ni.supplier_label || supById.get(ni.supplier_id) || '';
        } else {
          ni.supplier_id = '';
          ni.supplier_from_source = '';
        }
      }
      this.newItems = list;
    },
    async transferNewItem(entry) {
      if (!this.canTransfer) {
        showToast('Keine Berechtigung für Stammdaten.', 5000, true);
        return false;
      }
      if (!navigator.onLine) {
        showToast('Zum Anlegen bitte online sein.', 5000, true);
        return false;
      }
      const name = String(entry.name || '').trim();
      const locId = Number(entry.location_id);
      if (name === '') {
        showToast('Bitte eine Bezeichnung angeben.', 4000, true);
        return false;
      }
      if (!locId) {
        showToast('Bitte einen Lagerort wählen.', 4000, true);
        return false;
      }
      entry.busy = true;
      try {
        const body = { name, unit: entry.unit || '', location_id: locId };
        const sid = Number(entry.supplier_id);
        if (sid > 0) {
          body.supplier_links = [{ supplier_id: sid, priority: 10 }];
        }
        await api.createItem(body);
        await markPendingFreeItemDone(entry);
        showToast(`„${name}" als Artikel angelegt.`, 3500);
        await this.loadNewItems();
        return true;
      } catch (e) {
        entry.busy = false;
        showToast(e?.message || 'Anlegen fehlgeschlagen', 6000, true);
        return false;
      }
    },
    async transferAll() {
      if (!this.canTransfer || this.bulkBusy) return;
      this.bulkBusy = true;
      let ok = 0;
      let fail = 0;
      try {
        for (const entry of [...this.newItems]) {
          const done = await this.transferNewItem(entry);
          if (done) ok += 1;
          else fail += 1;
        }
        if (ok > 0 && fail === 0) {
          showToast(`${ok} Artikel angelegt.`, 4000);
        } else if (ok > 0) {
          showToast(`${ok} angelegt, ${fail} übersprungen (siehe Liste).`, 6000, true);
        }
      } finally {
        this.bulkBusy = false;
      }
    },
    async dismissNewItem(entry) {
      try {
        await markPendingFreeItemDone(entry);
        await this.loadNewItems();
      } catch (e) {
        showToast(e?.message || 'Konnte nicht ausblenden', 5000, true);
      }
    },
  }));
}
