import * as invStorage from './inventory-storage.js';

/** @param {string|number} raw @returns {number|null} */
export function parseInventoryQuantity(raw) {
  const s = String(raw ?? '').trim().replace(',', '.');
  if (s === '') return null;
  const n = Number(s);
  if (Number.isNaN(n) || n < 0) return null;
  return n;
}

/** @param {number} itemId */
export async function markCountedZero(itemId) {
  await invStorage.saveInventoryLine(itemId, 'counted', 0);
}

/**
 * Leer/0 umschalten: erneuter Klick setzt Artikel wieder auf „offen“.
 * @param {number} itemId
 * @param {boolean} currentlyZero
 */
export async function toggleInventoryZero(itemId, currentlyZero) {
  if (currentlyZero) {
    await invStorage.saveInventoryLine(itemId, 'open');
    return;
  }
  await markCountedZero(itemId);
}

/**
 * Menge > 0 erfassen; leer/ungültig → wieder offen.
 * @param {number} itemId
 * @param {string|number} rawQty
 */
export async function saveCountedQuantity(itemId, rawQty) {
  const n = parseInventoryQuantity(rawQty);
  if (n === null || n === 0) {
    await invStorage.saveInventoryLine(itemId, 'open');
    return;
  }
  await invStorage.saveInventoryLine(itemId, 'counted', n);
}
