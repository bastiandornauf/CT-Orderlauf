import * as api from './api.js';
import * as storage from './storage.js';
import * as orch from './order-round.js';
import { groupLinksByItem, pickSupplierForItem } from './supplier-logic.js';
import { buildMailPreview, mailtoLink } from './email-generator.js';

function tomorrowIso() {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().slice(0, 10);
}

function formatDeDate(iso) {
  if (!iso) return '';
  const [y, m, day] = iso.split('-');
  return `${day}.${m}.${y}`;
}

/** @param {any} Alpine */
export function registerOrderAlpine(Alpine) {
  Alpine.data('preparePage', () => ({
    targetDate: tomorrowIso(),
    loading: false,
    error: '',
    delivering: [],
    notDelivering: [],
    init() {
      this.delivering = [];
      this.notDelivering = [];
    },
    async loadRound() {
      this.error = '';
      this.loading = true;
      try {
        if (!navigator.onLine) {
          throw new Error('Vorbereitung nur online möglich.');
        }
        const data = await api.fetchPayload(this.targetDate);
        const payload = {
          target_date: data.target_date,
          target_weekday: data.target_weekday,
          suppliers_delivering_ids: data.suppliers_delivering_ids,
          locations: data.locations,
          suppliers: data.suppliers,
          supplier_delivery_days: data.supplier_delivery_days,
          items: data.items,
          item_supplier_links: data.item_supplier_links,
          settings: data.settings,
        };
        await storage.savePreparedSnapshot(payload);
        const del = new Set(data.suppliers_delivering_ids);
        this.delivering = data.suppliers.filter((s) => del.has(s.id));
        this.notDelivering = data.suppliers.filter((s) => !del.has(s.id));
        window.location.href = '/order/round';
      } catch (e) {
        this.error = e.message || 'Fehler';
      } finally {
        this.loading = false;
      }
    },
  }));

  Alpine.data('roundPage', () => ({
    locations: [],
    activeLocId: null,
    items: [],
    quantities: {},
    freeLabel: '',
    freeQty: '',
    freeSupplierId: '',
    suppliers: [],
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/order/prepare';
        return;
      }
      if (round.status === 'prepared') {
        await storage.setOrderRoundStatus('active');
      }
      this.locations = await storage.getLocationsSorted();
      this.activeLocId = this.locations[0]?.id ?? null;
      this.suppliers = await storage.getAllSuppliers();
      await this.reloadEntries();
      this.$watch('activeLocId', () => this.loadItemsForTab());
      await this.loadItemsForTab();
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
      this.items = await storage.getItemsByLocation(this.activeLocId);
    },
    async onQtyBlur(itemId, event) {
      const v = event.target.value;
      await orch.saveCatalogQuantity(itemId, v, '');
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
    problemLines: [],
    groups: [],
    meta: null,
    allSuppliers: [],
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/order/prepare';
        return;
      }
      this.loading = true;
      this.meta = await storage.getMetaSnapshot();
      const entries = (await storage.getOrderEntries()).filter((e) => orch.parseQuantity(e.quantity) != null);
      const items = await storage.getAllItems();
      const itemMap = new Map(items.map((i) => [i.id, i]));
      const suppliers = await storage.getAllSuppliers();
      this.allSuppliers = suppliers;
      const supMap = new Map(suppliers.map((s) => [s.id, s]));
      const links = await storage.getItemSupplierLinks();
      const byItem = groupLinksByItem(links);
      const delivering = this.meta?.suppliers_delivering_ids || [];
      const delSet = new Set(delivering.map((x) => Number(x)));

      const problem = [];
      const bySup = new Map();

      for (const e of entries) {
        if (e.is_free_item) {
          const sid = e.free_supplier_id || e.selected_supplier_id;
          if (!sid || !delSet.has(Number(sid))) {
            problem.push({ entry: e, itemLabel: e.free_label, reason: 'Lieferant fehlt oder liefert nicht' });
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
            reason: 'Kein Lieferant am Zieltag',
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
          itemId: e.item_id,
          candidates: pick.candidates,
          supplierId: sid,
        });
      }

      this.problemLines = problem;
      this.groups = Array.from(bySup.entries()).map(([supplierId, lines]) => ({
        supplierId,
        supplier: supMap.get(supplierId),
        lines,
        note: '',
      }));

      for (const g of this.groups) {
        g.note = await storage.getSupplierNote(g.supplierId);
      }

      this.loading = false;
    },
    supplierOptions(line) {
      return line.candidates
        .map((id) => this.allSuppliers.find((s) => s.id === id))
        .filter(Boolean);
    },
    async onSupplierChange(line, newSid) {
      line.supplierId = Number(newSid);
      await storage.updateOrderEntry(line.entryId, { selected_supplier_id: line.supplierId });
      await this.init();
    },
    async updateQty(line, event) {
      const v = event.target.value;
      await storage.updateOrderEntry(line.entryId, { quantity: v });
 },
    async removeLine(line) {
      await storage.deleteOrderEntry(line.entryId);
      await this.init();
    },
    async saveNote(supplierId, text) {
      await storage.setSupplierNote(supplierId, text);
    },
    async addFreeToSupplier(supplierId) {
      const label = window.prompt('Freier Artikel (Bezeichnung)');
      if (!label?.trim()) return;
      const qty = window.prompt('Menge');
      await orch.addFreeLine(null, label, qty, supplierId);
      await this.init();
    },
    goOutput() {
      window.location.href = '/order/output';
    },
  }));

  Alpine.data('outputPage', () => ({
    blocks: [],
    cc: '',
    targetDate: '',
    finalized: false,
    async init() {
      const round = await storage.getOrderRound();
      if (!round) {
        window.location.href = '/order/prepare';
        return;
      }
      this.targetDate = round.target_date;
      this.finalized = round.status === 'finalized';
      const meta = await storage.getMetaSnapshot();
      this.cc = meta?.settings?.order_cc_email || '';

      const entries = (await storage.getOrderEntries()).filter((e) => orch.parseQuantity(e.quantity) != null);
      const items = await storage.getAllItems();
      const itemMap = new Map(items.map((i) => [i.id, i]));
      const suppliers = await storage.getAllSuppliers();
      const links = await storage.getItemSupplierLinks();
      const byItem = groupLinksByItem(links);
      const delivering = meta?.suppliers_delivering_ids || [];

      const bySup = new Map();
      for (const e of entries) {
        if (e.is_free_item) {
          const sid = e.free_supplier_id || e.selected_supplier_id;
          if (!sid) continue;
          if (!bySup.has(sid)) bySup.set(sid, []);
          bySup.get(sid).push({ label: e.free_label, quantity: e.quantity, unit: '' });
          continue;
        }
        const item = itemMap.get(e.item_id);
        if (!item) continue;
        const itemLinks = byItem.get(e.item_id) || [];
        const pick = pickSupplierForItem(e.item_id, itemLinks, delivering, e.selected_supplier_id);
        const sid = pick.supplierId;
        if (sid == null) continue;
        if (!bySup.has(sid)) bySup.set(sid, []);
        bySup.get(sid).push({ label: item.name, quantity: e.quantity, unit: item.unit });
      }

      this.blocks = [];
      for (const [supplierId, lines] of bySup.entries()) {
        const supplier = suppliers.find((s) => s.id === supplierId);
        if (!supplier) continue;
        const note = await storage.getSupplierNote(supplierId);
        const prev = buildMailPreview({
          template: supplier.email_template,
          supplierName: supplier.name,
          targetDateFormatted: formatDeDate(this.targetDate),
          lines,
          freeLines: [],
          supplierNote: note,
        });
        this.blocks.push({
          supplier,
          lines,
          note,
          subject: prev.subject,
          body: prev.body,
        });
      }
    },
    async copyBlock(block) {
      await navigator.clipboard.writeText(`${block.subject}\n\n${block.body}`);
    },
    mailtoBlock(block) {
      if (!block.supplier.email) return;
      window.location.href = mailtoLink(block.supplier.email, block.subject, block.body, this.cc);
    },
    async pdfBlock(block) {
      try {
        const blob = await api.downloadSupplierPdf({
          supplier_name: block.supplier.name,
          target_date: this.targetDate,
          lines: block.lines,
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
      await storage.setOrderRoundStatus('finalized');
      this.finalized = true;
    },
    async newRound() {
      await storage.clearOrderRound();
      window.location.href = '/';
    },
  }));
}
