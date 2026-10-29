/**
 * Anzeigename für interne Listen: Alias, sonst Standardbezeichnung.
 * Ältere Snapshots ohne display_name/alias fallen auf name zurück.
 */
export function displayName(item) {
  if (!item) return '';
  const alias = String(item.alias ?? '').trim();
  if (alias) return alias;
  return item.display_name || item.name || '';
}

/**
 * Suchtext eines Artikels: Anzeigename, Standardbezeichnung und Lieferanten-Bezeichnungen/Art.-Nr.
 * @param {object} item
 * @param {Map<number, string>} [supplierSearchByItem]
 */
export function itemSearchText(item, supplierSearchByItem) {
  return [displayName(item), item?.name, supplierSearchByItem?.get(Number(item?.id))]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();
}

/**
 * Bezeichnung und Einheit, wie sie an den Lieferanten gehen.
 * Nie der Alias – nur Lieferanten-Bezeichnung oder Standardbezeichnung.
 * @param {object} item
 * @param {object} [link] Zuordnung inkl. Bestelldaten (supplier_label, supplier_sku, order_unit)
 * @returns {{ label: string, unit: string }}
 */
export function supplierOrderLine(item, link) {
  const formal = String(link?.supplier_label ?? '').trim() || item?.name || '';
  const sku = String(link?.supplier_sku ?? '').trim();
  return {
    label: sku ? `${formal} – Art.-Nr. ${sku}` : formal,
    unit: String(link?.order_unit ?? '').trim() || item?.unit || '',
  };
}
