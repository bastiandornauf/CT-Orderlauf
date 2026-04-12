/**
 * IndexedDB persistence for one active order round (offline).
 */

const DB_NAME = 'ct-orderlauf';
const DB_VERSION = 1;

/** @returns {Promise<IDBDatabase>} */
function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onerror = () => reject(req.error);
    req.onsuccess = () => resolve(req.result);
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
    };
  });
}

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
  });
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

export async function getLocationsSorted() {
  const rows = await getAll('locations');
  return rows.sort((a, b) => (a.sort_order - b.sort_order) || String(a.name).localeCompare(String(b.name)));
}

export async function getItemsByLocation(locationId) {
  const items = await getAll('items');
  const lid = Number(locationId);
  return items
    .filter((i) => Number(i.location_id) === lid)
    .sort((a, b) => String(a.name).localeCompare(String(b.name), 'de'));
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
