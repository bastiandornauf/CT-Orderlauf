/**
 * IndexedDB für Inventur (strikt getrennt von Bestellrunde).
 */
import {
  clearStore,
  getAll,
  getOne,
  putRow,
  deleteRow,
  openDb,
  INVENTORY_ONLY_STORES,
} from './storage.js';

/** @param {unknown} v */
function normalizeActive(v) {
  if (v === true || v === 1 || v === '1') return 1;
  return 0;
}

/** @returns {Promise<void>} */
async function assertInventoryStoresReady() {
  const db = await openDb();
  for (const s of INVENTORY_ONLY_STORES) {
    if (!db.objectStoreNames.contains(s)) {
      throw new Error(
        'Inventur-Speicher im Browser fehlt. Seite hart neu laden (Cache leeren) und erneut „Inventur starten“.',
      );
    }
  }
}

/**
 * @returns {Promise<boolean>}
 */
export async function prepareInventoryReloadWouldEraseProgress() {
  const { session } = await loadInventorySessionForUi();
  if (!session) return false;
  if (session.status === 'finalized') return false;
  if (session.status !== 'prepared') return true;
  const lines = await getInventoryLines();
  return lines.some((l) => l.status === 'counted');
}

/**
 * @returns {Promise<{ itemCount: number, locationCount: number }>}
 */
export async function getInventoryCatalogCounts() {
  const items = await getAll('inventory_catalog_items');
  const locs = await getAll('inventory_catalog_locations');
  const activeItems = items.filter((i) => Number(i.active) !== 0);
  const activeLocs = locs.filter((l) => Number(l.active) !== 0);
  return { itemCount: activeItems.length, locationCount: activeLocs.length };
}

/** Session ohne Katalog = defekt (z. B. abgebrochener Start). */
export async function isInventoryCatalogValid() {
  const session = await getOne('inventory_session', 1);
  if (!session) return false;
  const { itemCount, locationCount } = await getInventoryCatalogCounts();
  return itemCount > 0 && locationCount > 0;
}

/**
 * @returns {Promise<{ session: object|null, removedInvalid: boolean }>}
 */
export async function loadInventorySessionForUi() {
  await assertInventoryStoresReady();
  const session = await getOne('inventory_session', 1);
  if (!session) {
    return { session: null, removedInvalid: false, catalogInvalid: false };
  }
  const { itemCount, locationCount } = await getInventoryCatalogCounts();
  if (itemCount > 0 && locationCount > 0) {
    return { session, removedInvalid: false, catalogInvalid: false };
  }
  return { session, removedInvalid: false, catalogInvalid: true };
}

/** Session + leerer Katalog (defekt) – alles lokale Inventur-Daten entfernen. */
export async function discardBrokenInventorySession() {
  await clearInventorySession();
}

/** @returns {Promise<object|null>} */
export async function getValidInventorySession() {
  const { session } = await loadInventorySessionForUi();
  return session;
}

/**
 * @param {object} payload Antwort von /api/inventory/payload
 */
/**
 * Nur Katalog neu schreiben (Session + Zählzeilen bleiben).
 * @param {object} payload
 */
export async function refillInventoryCatalog(payload) {
  await assertInventoryStoresReady();
  const locations = Array.isArray(payload.locations) ? payload.locations : [];
  const items = Array.isArray(payload.items) ? payload.items : [];
  if (locations.length === 0 || items.length === 0) {
    throw new Error(
      'Keine aktiven Artikel oder Lagerorte vom Server. Bitte Stammdaten prüfen (Artikel aktiv, Lagerort aktiv).',
    );
  }
  await clearStore('inventory_catalog_locations');
  await clearStore('inventory_catalog_items');
  await writeInventoryCatalogRows(locations, items);
}

/** @param {object[]} locations @param {object[]} items */
async function writeInventoryCatalogRows(locations, items) {
  for (const loc of locations) {
    await putRow('inventory_catalog_locations', {
      id: Number(loc.id),
      name: loc.name,
      sort_order: loc.sort_order ?? 0,
      active: normalizeActive(loc.active),
    });
  }
  for (const it of items) {
    await putRow('inventory_catalog_items', {
      id: Number(it.id),
      name: it.name,
      unit: it.unit ?? '',
      location_id: Number(it.location_id),
      sort_order: it.sort_order ?? 0,
      valuation_price: it.valuation_price ?? null,
      active: normalizeActive(it.active),
    });
  }
}

export async function saveInventorySnapshot(payload) {
  await assertInventoryStoresReady();
  const locations = Array.isArray(payload.locations) ? payload.locations : [];
  const items = Array.isArray(payload.items) ? payload.items : [];
  if (locations.length === 0 || items.length === 0) {
    throw new Error(
      'Keine aktiven Artikel oder Lagerorte vom Server. Bitte Stammdaten prüfen (Artikel aktiv, Lagerort aktiv).',
    );
  }

  for (const s of INVENTORY_ONLY_STORES) {
    await clearStore(s);
  }

  await writeInventoryCatalogRows(locations, items);

  const now = new Date().toISOString();
  const stichtag = payload.stichtag || now.slice(0, 10);
  const label = String(payload.label || '').trim();

  await putRow('inventory_session', {
    id: 1,
    status: 'prepared',
    label: label || `Inventur ${stichtag}`,
    stichtag,
    created_at: now,
    prepared_at: now,
    finalized_at: null,
  });
}

export async function getInventorySession() {
  return getOne('inventory_session', 1);
}

/** @param {string} status @param {object} [extra] */
export async function setInventorySessionStatus(status, extra = {}) {
  const row = await getInventorySession();
  if (!row) return;
  const now = new Date().toISOString();
  const next = { ...row, ...extra, status };
  if (status === 'finalized') {
    next.finalized_at = now;
  }
  await putRow('inventory_session', next);
}

export async function getInventoryCatalogLocationsSorted() {
  const rows = await getAll('inventory_catalog_locations');
  return rows
    .filter((l) => normalizeActive(l.active) === 1)
    .sort(
      (a, b) =>
        (a.sort_order - b.sort_order) || String(a.name).localeCompare(String(b.name), 'de'),
    );
}

export async function getInventoryCatalogItemsByLocation(locationId) {
  const items = await getAll('inventory_catalog_items');
  const lid = Number(locationId);
  return items
    .filter((i) => Number(i.location_id) === lid && Number(i.active) !== 0)
    .sort((a, b) => {
      const ao = Number(a.sort_order) || 0;
      const bo = Number(b.sort_order) || 0;
      if (ao !== bo) return ao - bo;
      return String(a.name).localeCompare(String(b.name), 'de');
    });
}

export async function getAllInventoryCatalogItems() {
  const items = await getAll('inventory_catalog_items');
  return items.filter((i) => Number(i.active) !== 0);
}

export async function getInventoryLines() {
  return getAll('inventory_lines');
}

/** @param {number} itemId */
export async function findInventoryLineByItemId(itemId) {
  const lines = await getInventoryLines();
  return lines.find((l) => Number(l.item_id) === Number(itemId)) || null;
}

/**
 * @param {number} itemId
 * @param {'open'|'counted'} status
 * @param {number|null} quantity nur bei counted (0 erlaubt)
 */
export async function saveInventoryLine(itemId, status, quantity = null) {
  const session = await getInventorySession();
  if (session?.status === 'finalized') {
    return;
  }
  const iid = Number(itemId);
  const existing = await findInventoryLineByItemId(iid);
  const now = new Date().toISOString();

  if (status === 'open') {
    if (existing?.id != null) {
      await deleteRow('inventory_lines', existing.id);
    }
    return;
  }

  const row = {
    item_id: iid,
    status: 'counted',
    quantity: quantity == null ? 0 : Number(quantity),
    updated_at: now,
  };
  if (existing?.id != null) {
    row.id = existing.id;
  }
  await putRow('inventory_lines', row);
}

/** Nur Inventur-Stores leeren. */
export async function clearInventorySession() {
  for (const s of INVENTORY_ONLY_STORES) {
    await clearStore(s);
  }
}

/* ─── Freie Inventur-Artikel (nicht im Katalog) ─────────────── */

/** @returns {Promise<object[]>} */
export async function getInventoryFreeItems() {
  return getAll('inventory_free_items');
}

/** @param {number} locationId */
export async function getInventoryFreeItemsByLocation(locationId) {
  const lid = Number(locationId);
  const rows = await getAll('inventory_free_items');
  return rows
    .filter((r) => Number(r.location_id) === lid)
    .sort((a, b) => String(a.name || '').localeCompare(String(b.name || ''), 'de'));
}

/**
 * @param {{ name: string, unit?: string, quantity?: number|string, location_id?: number|null }} data
 * @returns {Promise<boolean>} false wenn ungültig oder Inventur gesperrt
 */
export async function addInventoryFreeItem(data) {
  const session = await getInventorySession();
  if (session?.status === 'finalized') return false;
  const name = String(data.name || '').trim();
  if (name === '') return false;
  const now = new Date().toISOString();
  const qtyRaw = String(data.quantity ?? '').trim().replace(',', '.');
  const qty = qtyRaw === '' ? 0 : Number(qtyRaw);
  await putRow('inventory_free_items', {
    name,
    unit: String(data.unit || '').trim(),
    quantity: Number.isFinite(qty) && qty >= 0 ? qty : 0,
    location_id: data.location_id != null ? Number(data.location_id) : null,
    transferred: 0,
    created_at: now,
    updated_at: now,
  });
  return true;
}

/** @param {number} id @param {object} patch */
export async function updateInventoryFreeItem(id, patch) {
  const row = await getOne('inventory_free_items', Number(id));
  if (!row) return;
  await putRow('inventory_free_items', {
    ...row,
    ...patch,
    id: row.id,
    updated_at: new Date().toISOString(),
  });
}

/** @param {number} id */
export async function deleteInventoryFreeItem(id) {
  await deleteRow('inventory_free_items', Number(id));
}
