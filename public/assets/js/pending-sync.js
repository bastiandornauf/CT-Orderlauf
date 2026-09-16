/**
 * Abgleich der Freitext-Artikel mit der gemeinsamen Sammlung auf dem Server.
 *
 * Der Rundgang läuft offline, deshalb werden Freitext-Artikel zunächst lokal
 * vorgemerkt und später gebündelt übertragen. Ausgelöst wird das an den
 * Stellen, an denen ohnehin eine Verbindung besteht: Kontrolle, Inventur-
 * Abschluss und die Sammelliste selbst. So landen auch die Artikel von
 * Kolleginnen mit „Nur Bestellen“-Recht im gemeinsamen Topf.
 */
import * as api from './api.js';
import * as storage from './storage.js';
import * as invStorage from './inventory-storage.js';

/** Erfassungen ohne Zeitstempel: bewusst alt, damit Verworfenes nicht zurückkehrt. */
const LEGACY_SEEN_AT = '1970-01-01T00:00:00.000Z';

/**
 * Freitext-Positionen der aktuellen Runde/Inventur einmalig vormerken.
 * Nötig für Daten, die vor Einführung der Sammlung entstanden sind.
 * Übernommene Zeilen werden mit `pending_synced` markiert, damit der Zähler
 * nicht bei jedem Aufruf erneut hochläuft.
 */
export async function backfillFromCurrentRounds() {
  for (const f of await invStorage.getInventoryFreeItems()) {
    if (Number(f.pending_synced) === 1) continue;
    if (Number(f.transferred) === 1) {
      await invStorage.updateInventoryFreeItem(f.id, { pending_synced: 1 });
      continue;
    }
    await storage.recordPendingItem(
      {
        name: f.name || '',
        unit: f.unit || '',
        quantity: f.quantity,
        location_id: f.location_id ?? null,
        source: 'inventory',
      },
      f.created_at || f.updated_at || LEGACY_SEEN_AT,
    );
    await invStorage.updateInventoryFreeItem(f.id, { pending_synced: 1 });
  }

  const round = await storage.getOrderRound();
  const roundSeenAt = round?.prepared_at || round?.created_at || LEGACY_SEEN_AT;
  for (const e of await storage.getOrderEntries()) {
    if (!e.is_free_item || Number(e.pending_synced) === 1) continue;
    if (e.transferred_to_item_id) {
      await storage.updateOrderEntry(e.id, { pending_synced: 1 });
      continue;
    }
    await storage.recordPendingItem(
      {
        name: e.free_label || '',
        unit: e.free_unit || '',
        quantity: e.quantity,
        location_id: e.location_id ?? null,
        supplier_id: e.free_supplier_id ?? e.selected_supplier_id ?? null,
        source: 'order',
      },
      e.created_at || roundSeenAt,
    );
    await storage.updateOrderEntry(e.id, { pending_synced: 1 });
  }
}

/**
 * Lokal vorgemerkte Freitext-Artikel an den Server übergeben.
 * Schlägt bewusst leise fehl: der Ausgangskorb bleibt dann stehen und wird
 * beim nächsten Versuch erneut angeboten.
 *
 * @returns {Promise<{ pushed: number, offline?: boolean, error?: string }>}
 */
export async function pushPendingOutbox() {
  try {
    await backfillFromCurrentRounds();
  } catch {
    /* Nachtrag darf den Abgleich nicht verhindern */
  }

  if (!navigator.onLine) {
    return { pushed: 0, offline: true };
  }

  let rows = [];
  try {
    rows = await storage.getPendingOutbox();
  } catch {
    return { pushed: 0, error: 'Ausgangskorb nicht lesbar' };
  }
  if (rows.length === 0) {
    return { pushed: 0 };
  }

  const items = rows.map((r) => ({
    name: r.name,
    unit: r.unit || '',
    quantity: r.last_quantity || '',
    location_id: r.location_id ?? null,
    supplier_id: r.supplier_id ?? null,
    source: Array.isArray(r.sources) && r.sources.includes('inventory') ? 'inventory' : 'order',
    increment: Number(r.seen_count) || 1,
    first_seen_at: r.first_seen_at || null,
    last_seen_at: r.last_seen_at || null,
  }));

  try {
    await api.syncPendingItems(items);
  } catch (e) {
    return { pushed: 0, error: e?.message || 'Abgleich fehlgeschlagen' };
  }

  await storage.clearPushedPendingOutbox(
    rows.map((r) => ({ id: r.id, seen_count: Number(r.seen_count) || 1 })),
  );
  return { pushed: rows.length };
}
