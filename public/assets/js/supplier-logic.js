/**
 * Lieferanten-Entscheidung: Liefertag + Priorität (höhere Zahl = bevorzugt).
 */

/** @param {number[]} deliveringIds */
export function pickSupplierForItem(itemId, links, deliveringIds, selectedOverride) {
  const dset = new Set(deliveringIds.map((x) => Number(x)));
  const so = selectedOverride != null ? Number(selectedOverride) : null;
  if (so != null && dset.has(so)) {
    const allowed = links.some((l) => Number(l.supplier_id) === so);
    if (allowed) {
      const candidates = links
        .map((l) => Number(l.supplier_id))
        .filter((id) => dset.has(id));
      return { supplierId: so, candidates };
    }
  }
  const eligible = links
    .filter((l) => dset.has(Number(l.supplier_id)))
    .sort((a, b) => b.priority - a.priority);
  const candidates = eligible.map((l) => Number(l.supplier_id));
  return { supplierId: eligible[0] != null ? Number(eligible[0].supplier_id) : null, candidates };
}

export function groupLinksByItem(links) {
  const map = new Map();
  for (const l of links) {
    if (!map.has(l.item_id)) map.set(l.item_id, []);
    map.get(l.item_id).push(l);
  }
  return map;
}
