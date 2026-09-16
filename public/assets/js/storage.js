/**
 * IndexedDB persistence for one active order round (offline).
 */

const DB_NAME = 'ct-orderlauf';
const DB_VERSION = 5;

/** @returns {Promise<IDBDatabase>} */
function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onerror = () => reject(req.error);
    req.onsuccess = () => resolve(req.result);
    // Ein anderer offener Tab blockiert sonst still das Schema-Update.
    req.onblocked = () =>
      reject(
        new Error('Bitte alle anderen Tabs dieser App schließen und die Seite neu laden.'),
      );
    req.onupgradeneeded = () => {
      const db = req.result;
      if (!db.objectStoreNames.contains('meta')) {
        db.createObjectStore('meta', { keyPath: 'key' });
      }
      if (!db.objectStoreNames.contains('order_round')) {
        db.createObjectStore('order_round', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('locations')) {
        db.createObjectStore('locations', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('items')) {
        db.createObjectStore('items', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('suppliers')) {
        db.createObjectStore('suppliers', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('supplier_delivery_days')) {
        const s = db.createObjectStore('supplier_delivery_days', { keyPath: 'id', autoIncrement: true });
        s.createIndex('by_supplier', 'supplier_id', { unique: false });
      }
      if (!db.objectStoreNames.contains('item_supplier_links')) {
        const s = db.createObjectStore('item_supplier_links', { keyPath: 'id', autoIncrement: true });
        s.createIndex('by_item', 'item_id', { unique: false });
      }
      if (!db.objectStoreNames.contains('order_entries')) {
        const s = db.createObjectStore('order_entries', { keyPath: 'id', autoIncrement: true });
        s.createIndex('by_item', 'item_id', { unique: false });
      }
      if (!db.objectStoreNames.contains('supplier_notes')) {
        db.createObjectStore('supplier_notes', { keyPath: 'supplier_id' });
      }
      if (!db.objectStoreNames.contains('inventory_session')) {
        db.createObjectStore('inventory_session', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('inventory_lines')) {
        const inv = db.createObjectStore('inventory_lines', { keyPath: 'id', autoIncrement: true });
        inv.createIndex('by_item', 'item_id', { unique: true });
      }
      if (!db.objectStoreNames.contains('inventory_catalog_locations')) {
        db.createObjectStore('inventory_catalog_locations', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('inventory_catalog_items')) {
        db.createObjectStore('inventory_catalog_items', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('inventory_free_items')) {
        const s = db.createObjectStore('inventory_free_items', { keyPath: 'id', autoIncrement: true });
        s.createIndex('by_location', 'location_id', { unique: false });
      }
      if (!db.objectStoreNames.contains('pending_items')) {
        const s = db.createObjectStore('pending_items', { keyPath: 'id', autoIncrement: true });
        s.createIndex('by_key', 'dedupe_key', { unique: true });
      }
    };
  });
}

/**
 * Ausgangskorb für Freitext-Artikel auf dem Weg zur gemeinsamen Sammlung
 * auf dem Server. Wird bewusst von keinem Reset geleert – Einträge verschwinden
 * erst, wenn sie erfolgreich übertragen wurden. Offline erfasste Sichtungen
 * werden hier je Bezeichnung gebündelt.
 */
export const PENDING_ITEMS_STORE = 'pending_items';

/** Inventur-Stores (inventory_*) werden von savePreparedSnapshot/clearOrderRound nicht geleert. */
export const INVENTORY_ONLY_STORES = [
  'inventory_session',
  'inventory_lines',
  'inventory_catalog_locations',
  'inventory_catalog_items',
  'inventory_free_items',
];

/** @param {string} store */
async function clearStore(store) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite');
    tx.objectStore(store).clear();
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

/** @param {string} store @param {unknown} row */
async function putRow(store, row) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite');
    tx.objectStore(store).put(row);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

/** @param {string} store @param {object[]} rows */
async function bulkAdd(store, rows) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite');
    const s = tx.objectStore(store);
    for (const row of rows) {
      s.add(row);
    }
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

/** @param {string} store @param {IDBValidKey} key */
async function deleteRow(store, key) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readwrite');
    tx.objectStore(store).delete(key);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

/** @param {string} store */
async function getAll(store) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readonly');
    const req = tx.objectStore(store).getAll();
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

/** @param {string} store @param {IDBValidKey} key */
async function getOne(store, key) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(store, 'readonly');
    const req = tx.objectStore(store).get(key);
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

/**
 * Ob „Bestellrunde laden“ (savePreparedSnapshot) lokale Fortschritte zerstören würde.
 * @returns {Promise<boolean>}
 */
export async function prepareReloadWouldEraseLocalProgress() {
  const round = await getOrderRound();
  if (!round) return false;
  // Abgeschlossene Runde: bewusst beendet, nächstes „Laden“ ist eine neue Runde – kein Verlust offener Arbeit.
  if (round.status === 'finalized') return false;
  if (round.status !== 'prepared') return true;
  const entries = await getOrderEntries();
  for (const e of entries) {
    if (e.is_free_item) {
      if (String(e.free_label ?? '').trim() !== '' || String(e.quantity ?? '').trim() !== '') {
        return true;
      }
      continue;
    }
    const s = String(e.quantity ?? '').trim().replace(',', '.');
    if (!s) continue;
    const n = Number(s);
    if (!Number.isNaN(n) && n !== 0) return true;
  }
  const notes = await getAllSupplierNotes();
  return notes.some((x) => String(x.text ?? '').trim() !== '');
}

/**
 * Replace local DB with server snapshot for a target date.
 * @param {object} payload API /api/order/payload
 */
export async function savePreparedSnapshot(payload) {
  const stores = [
    'order_round',
    'locations',
    'items',
    'suppliers',
    'supplier_delivery_days',
    'item_supplier_links',
    'order_entries',
    'supplier_notes',
    'meta',
  ];
  for (const s of stores) {
    await clearStore(s);
  }

  const now = new Date().toISOString();
  await putRow('order_round', {
    id: 1,
    status: 'prepared',
    created_at: now,
    target_date: payload.target_date,
    target_weekday: payload.target_weekday,
    prepared_at: now,
    review_ready_at: null,
    finalized_at: null,
  });

  for (const loc of payload.locations) {
    await putRow('locations', {
      id: loc.id,
      name: loc.name,
      sort_order: loc.sort_order,
      active: loc.active,
    });
  }

  for (const it of payload.items) {
    await putRow('items', {
      id: it.id,
      name: it.name,
      unit: it.unit,
      location_id: it.location_id,
      sort_order: it.sort_order ?? 0,
      min_stock: it.min_stock ?? null,
      max_stock: it.max_stock ?? null,
      active: it.active,
    });
  }

  for (const sup of payload.suppliers) {
    await putRow('suppliers', {
      id: sup.id,
      name: sup.name,
      order_type: sup.order_type,
      email: sup.email,
      email_template: sup.email_template,
      active: sup.active,
      street: sup.street ?? null,
      city: sup.city ?? null,
      attach_pdf: !!sup.attach_pdf,
      email_subject_template: sup.email_subject_template ?? null,
    });
  }

  await bulkAdd(
    'supplier_delivery_days',
    payload.supplier_delivery_days.map((row) => ({
      supplier_id: row.supplier_id,
      weekday: row.weekday,
    })),
  );

  await bulkAdd(
    'item_supplier_links',
    payload.item_supplier_links.map((row) => ({
      item_id: row.item_id,
      supplier_id: row.supplier_id,
      priority: row.priority,
    })),
  );

  await putRow('meta', {
    key: 'snapshot',
    last_sync: now,
    app_version: '1',
    prepared_flag: true,
    offline_ready_flag: true,
    settings: payload.settings || {},
    suppliers_delivering_ids: payload.suppliers_delivering_ids || [],
    supplier_delivery_targets: payload.supplier_delivery_targets || [],
  });
}

/**
 * Aktualisiert Lieferanten + globale Einstellungen aus dem Server, ohne die Runde zu verlieren.
 * Wichtig für aktuelle Betreff-Vorlagen (pro Lieferant) und Firmenname nach Stammdaten-Änderungen.
 * @param {object} payload Antwort von /api/order/payload (ok: true, …)
 */
export async function mergeSuppliersAndSettingsFromPayload(payload) {
  const now = new Date().toISOString();
  for (const sup of payload.suppliers || []) {
    await putRow('suppliers', {
      id: sup.id,
      name: sup.name,
      order_type: sup.order_type,
      email: sup.email,
      email_template: sup.email_template,
      active: sup.active,
      street: sup.street ?? null,
      city: sup.city ?? null,
      attach_pdf: !!sup.attach_pdf,
      email_subject_template: sup.email_subject_template ?? null,
    });
  }
  const meta = await getOne('meta', 'snapshot');
  if (meta) {
    const hidden = meta.hidden_supplier_ids;
    const incomingSettings = payload.settings || {};
    meta.settings = { ...meta.settings, ...incomingSettings };
    if (Array.isArray(payload.suppliers_delivering_ids)) {
      meta.suppliers_delivering_ids = payload.suppliers_delivering_ids;
    }
    if (Array.isArray(payload.supplier_delivery_targets)) {
      meta.supplier_delivery_targets = payload.supplier_delivery_targets;
    }
    if (Array.isArray(hidden)) {
      meta.hidden_supplier_ids = hidden;
    }
    meta.last_sync = now;
    await putRow('meta', meta);
  }
}

export async function getOrderRound() {
  return getOne('order_round', 1);
}

/** @param {string} status */
export async function setOrderRoundStatus(status, extra = {}) {
  const row = await getOrderRound();
  if (!row) return;
  const now = new Date().toISOString();
  const next = { ...row, ...extra, status };
  if (status === 'ready_for_review') {
    next.review_ready_at = now;
  }
  if (status === 'finalized') {
    next.finalized_at = now;
  }
  await putRow('order_round', next);
}

export async function getMetaSnapshot() {
  return getOne('meta', 'snapshot');
}

/** Lieferanten-IDs, die im Rundgang ausgeblendet sind (nur lokal, pro Gerät). */
export async function getHiddenSupplierIds() {
  const m = await getMetaSnapshot();
  const raw = m?.hidden_supplier_ids;
  if (!Array.isArray(raw)) return [];
  return raw.map(Number).filter((n) => n > 0);
}

/** @param {number[]} ids */
export async function setHiddenSupplierIds(ids) {
  const m = (await getOne('meta', 'snapshot')) || { key: 'snapshot' };
  m.hidden_supplier_ids = [...new Set(ids.map(Number).filter((n) => n > 0))];
  await putRow('meta', m);
}

export async function getLocationsSorted() {
  const rows = await getAll('locations');
  return rows.sort((a, b) => (a.sort_order - b.sort_order) || String(a.name).localeCompare(String(b.name)));
}

export async function getItemsByLocation(locationId) {
  const items = await getAll('items');
  const lid = Number(locationId);
  return items
    .filter((i) => Number(i.location_id) === lid)
    .sort((a, b) => {
      const ao = Number(a.sort_order) || 0;
      const bo = Number(b.sort_order) || 0;
      if (ao !== bo) return ao - bo;
      return String(a.name).localeCompare(String(b.name), 'de');
    });
}

export async function getAllItems() {
  const items = await getAll('items');
  return items.sort((a, b) => String(a.name).localeCompare(String(b.name), 'de'));
}

export async function getAllSuppliers() {
  return getAll('suppliers');
}

export async function getDeliveryDays() {
  return getAll('supplier_delivery_days');
}

export async function getItemSupplierLinks() {
  return getAll('item_supplier_links');
}

/**
 * Artikelzeile in der lokalen DB (Form wie nach savePreparedSnapshot).
 * @param {object} it
 */
export async function upsertItemRow(it) {
  await putRow('items', {
    id: Number(it.id),
    name: it.name,
    unit: it.unit ?? '',
    location_id: Number(it.location_id),
    sort_order: Number(it.sort_order) || 0,
    min_stock: it.min_stock != null && it.min_stock !== '' ? Number(it.min_stock) : null,
    max_stock: it.max_stock != null && it.max_stock !== '' ? Number(it.max_stock) : null,
    active: Number(it.active) ? 1 : 0,
  });
}

/**
 * Alle item_supplier_links für eine Artikel-ID ersetzen (ohne andere Artikel zu ändern).
 * @param {number} itemId
 * @param {{ supplier_id: number, priority: number }[]} links
 */
export async function replaceItemSupplierLinks(itemId, links) {
  const iid = Number(itemId);
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('item_supplier_links', 'readwrite');
    const store = tx.objectStore('item_supplier_links');
    const idx = store.index('by_item');
    const req = idx.getAll(iid);
    req.onerror = () => reject(req.error);
    req.onsuccess = () => {
      try {
        for (const row of req.result) {
          if (row.id != null) {
            store.delete(row.id);
          }
        }
        for (const L of links) {
          store.add({
            item_id: iid,
            supplier_id: Number(L.supplier_id),
            priority: Number(L.priority) || 0,
          });
        }
      } catch (e) {
        reject(e);
      }
    };
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

export async function getOrderEntries() {
  return getAll('order_entries');
}

/** @param {number} itemId */
export async function findCatalogEntryByItemId(itemId) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('order_entries', 'readonly');
    const idx = tx.objectStore('order_entries').index('by_item');
    const req = idx.getAll(itemId);
    req.onsuccess = () => {
      const rows = req.result.filter((e) => !e.is_free_item);
      resolve(rows[0] || null);
    };
    req.onerror = () => reject(req.error);
  });
}

/** @param {object} entry */
export async function saveOrderEntry(entry) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('order_entries', 'readwrite');
    const store = tx.objectStore('order_entries');
    let req;
    if (entry.id != null) {
      req = store.put(entry);
    } else {
      const clean = { ...entry };
      delete clean.id;
      req = store.add(clean);
    }
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

export async function updateOrderEntry(id, patch) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction('order_entries', 'readwrite');
    const store = tx.objectStore('order_entries');
    const getReq = store.get(id);
    getReq.onsuccess = () => {
      const cur = getReq.result;
      if (!cur) {
        reject(new Error('entry missing'));
        return;
      }
      Object.assign(cur, patch);
      const putReq = store.put(cur);
      putReq.onsuccess = () => resolve();
      putReq.onerror = () => reject(putReq.error);
    };
    getReq.onerror = () => reject(getReq.error);
  });
}

export async function deleteOrderEntry(id) {
  return deleteRow('order_entries', id);
}

export async function setSupplierNote(supplierId, text) {
  await putRow('supplier_notes', { supplier_id: supplierId, text: text || '' });
}

export async function getSupplierNote(supplierId) {
  const row = await getOne('supplier_notes', supplierId);
  return row?.text || '';
}

export async function getAllSupplierNotes() {
  return getAll('supplier_notes');
}

/** Remove round and all working data */
export async function clearOrderRound() {
  const stores = [
    'order_round',
    'locations',
    'items',
    'suppliers',
    'supplier_delivery_days',
    'item_supplier_links',
    'order_entries',
    'supplier_notes',
    'meta',
  ];
  for (const s of stores) {
    await clearStore(s);
  }
}

/* ─── Ausgangskorb für die gemeinsame Sammlung neuer Artikel ───────────────── */

/** @param {string} name */
function pendingDedupeKey(name) {
  return String(name || '')
    .trim()
    .replace(/\s+/g, ' ')
    .toLowerCase();
}

/** @param {string} key */
async function findPendingItemByKey(key) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(PENDING_ITEMS_STORE, 'readonly');
    const req = tx.objectStore(PENDING_ITEMS_STORE).index('by_key').get(key);
    req.onsuccess = () => resolve(req.result || null);
    req.onerror = () => reject(req.error);
  });
}

/**
 * Freitext-Artikel für die gemeinsame Sammlung vormerken. Läuft offline und
 * bündelt mehrfache Sichtungen derselben Bezeichnung in `seen_count`, damit der
 * Server beim nächsten Abgleich um genau diesen Betrag hochzählt.
 *
 * @param {{
 *   name: string, unit?: string, quantity?: unknown,
 *   location_id?: number|null, supplier_id?: number|null,
 *   source?: 'order'|'inventory'
 * }} data
 * @param {string} [seenAt] ISO-Zeitpunkt der Erfassung (für Nachträge aus Altdaten)
 */
export async function recordPendingItem(data, seenAt) {
  const name = String(data.name || '').trim().replace(/\s+/g, ' ');
  if (name === '') return;
  const key = pendingDedupeKey(name);
  const at = seenAt || new Date().toISOString();
  const source = data.source === 'inventory' ? 'inventory' : 'order';
  const unit = String(data.unit || '').trim();
  const qty = String(data.quantity ?? '').trim();
  const locId = data.location_id != null && data.location_id !== '' ? Number(data.location_id) : null;
  const supId = data.supplier_id != null && data.supplier_id !== '' ? Number(data.supplier_id) : null;

  const existing = await findPendingItemByKey(key);
  if (!existing) {
    await putRow(PENDING_ITEMS_STORE, {
      dedupe_key: key,
      name,
      unit,
      last_quantity: qty,
      location_id: locId,
      supplier_id: supId && supId > 0 ? supId : null,
      sources: [source],
      seen_count: 1,
      first_seen_at: at,
      last_seen_at: at,
    });
    return;
  }

  const sources = new Set(Array.isArray(existing.sources) ? existing.sources : []);
  sources.add(source);
  const next = {
    ...existing,
    name: existing.name || name,
    unit: existing.unit || unit,
    last_quantity: qty || existing.last_quantity || '',
    location_id: existing.location_id ?? locId,
    supplier_id: existing.supplier_id ?? (supId && supId > 0 ? supId : null),
    sources: [...sources],
    seen_count: (Number(existing.seen_count) || 0) + 1,
    first_seen_at: existing.first_seen_at && existing.first_seen_at < at ? existing.first_seen_at : at,
    last_seen_at: existing.last_seen_at && existing.last_seen_at > at ? existing.last_seen_at : at,
  };
  await putRow(PENDING_ITEMS_STORE, next);
}

/** @returns {Promise<object[]>} noch nicht übertragene Vormerkungen */
export async function getPendingOutbox() {
  return getAll(PENDING_ITEMS_STORE);
}

/**
 * Übertragene Vormerkungen entfernen. Nur die gemeldeten Sichtungen werden
 * abgezogen – kommt währenddessen eine neue dazu, bleibt sie für den nächsten
 * Abgleich stehen.
 *
 * @param {{ id: number, seen_count: number }[]} pushed
 */
export async function clearPushedPendingOutbox(pushed) {
  for (const p of pushed) {
    const row = await getOne(PENDING_ITEMS_STORE, Number(p.id));
    if (!row) continue;
    const remaining = (Number(row.seen_count) || 0) - (Number(p.seen_count) || 0);
    if (remaining > 0) {
      await putRow(PENDING_ITEMS_STORE, { ...row, seen_count: remaining });
    } else {
      await deleteRow(PENDING_ITEMS_STORE, row.id);
    }
  }
}

export { openDb, putRow, getAll, getOne, deleteRow, clearStore };
