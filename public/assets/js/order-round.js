import * as storage from './storage.js';

export function parseQuantity(raw) {
  const s = String(raw ?? '').trim().replace(',', '.');
  if (s === '') return null;
  const n = Number(s);
  if (Number.isNaN(n) || n === 0) return null;
  return s;
}

export async function saveCatalogQuantity(itemId, rawQty, note = '') {
  const qty = parseQuantity(rawQty);
  const existing = await storage.findCatalogEntryByItemId(itemId);
  if (qty === null) {
    if (existing?.id != null) {
      await storage.deleteOrderEntry(existing.id);
    }
    return;
  }
  const row = {
    item_id: itemId,
    quantity: qty,
    note: note || '',
    selected_supplier_id: existing?.selected_supplier_id ?? null,
    is_free_item: false,
    free_label: '',
    free_supplier_id: null,
    location_id: null,
  };
  if (existing?.id != null) {
    row.id = existing.id;
  }
  await storage.saveOrderEntry(row);
}

export async function addFreeLine(locationId, label, rawQty, freeSupplierId) {
  // Free items allow any non-empty quantity string (e.g. "2 Stück", "1 Karton")
  const qty = String(rawQty ?? '').trim();
  if (!label?.trim() || qty === '') return;
  const sid =
    freeSupplierId != null && freeSupplierId !== '' ? Number(freeSupplierId) : null;
  await storage.saveOrderEntry({
    item_id: null,
    quantity: qty,
    note: '',
    selected_supplier_id: sid,
    is_free_item: true,
    free_label: label.trim(),
    free_supplier_id: sid,
    location_id: locationId,
  });
}
