/**
 * CSV mit allen Katalog-Artikeln und Spalte status (offen | gezaehlt_0 | gezaehlt).
 *
 * @param {object} session
 * @param {object[]} catalogItems aktive Artikel im Inventur-Snapshot
 * @param {Map<number, object>} lineByItemId inventory_lines nach item_id
 * @param {Map<number, object>} locationById
 * @param {object[]} [freeItems] freie Artikel (nicht im Katalog)
 */
export function buildInventoryCsv(session, catalogItems, lineByItemId, locationById, freeItems = []) {
  const esc = (v) => {
    const s = v == null ? '' : String(v);
    if (/[;"\n\r]/.test(s)) {
      return `"${s.replace(/"/g, '""')}"`;
    }
    return s;
  };

  const header = [
    'stichtag',
    'bezeichnung',
    'lagerort',
    'artikel_id',
    'artikel',
    'einheit',
    'status',
    'menge',
    'bewertungspreis',
    'wert',
  ];

  const rows = [header.map(esc).join(';')];
  const stichtag = session?.stichtag || '';
  const label = session?.label || '';

  const sorted = [...catalogItems].sort((a, b) => {
    const la = locationById.get(Number(a.location_id))?.name || '';
    const lb = locationById.get(Number(b.location_id))?.name || '';
    if (la !== lb) return la.localeCompare(lb, 'de');
    const ao = Number(a.sort_order) || 0;
    const bo = Number(b.sort_order) || 0;
    if (ao !== bo) return ao - bo;
    return String(a.name || '').localeCompare(String(b.name || ''), 'de');
  });

  let totalValue = 0;
  let hasValue = false;

  for (const it of sorted) {
    const locName = locationById.get(Number(it.location_id))?.name || '';
    const line = lineByItemId.get(Number(it.id));
    let status = 'offen';
    let qtyStr = '';
    let wert = '';
    const price =
      it.valuation_price != null && it.valuation_price !== ''
        ? Number(it.valuation_price)
        : null;
    const priceStr =
      price != null && !Number.isNaN(price) ? String(price).replace('.', ',') : '';

    if (line?.status === 'counted') {
      const q = Number(line.quantity);
      if (q === 0) {
        status = 'gezaehlt_0';
        qtyStr = '0';
      } else {
        status = 'gezaehlt';
        qtyStr = String(q).replace('.', ',');
        if (price != null && !Number.isNaN(price)) {
          const v = Math.round(q * price * 100) / 100;
          wert = String(v).replace('.', ',');
          totalValue += v;
          hasValue = true;
        }
      }
    }

    rows.push(
      [
        stichtag,
        label,
        locName,
        it.id,
        it.name,
        it.unit || '',
        status,
        qtyStr,
        priceStr,
        wert,
      ]
        .map(esc)
        .join(';'),
    );
  }

  const frees = Array.isArray(freeItems) ? freeItems : [];
  const sortedFree = [...frees].sort((a, b) => {
    const la = locationById.get(Number(a.location_id))?.name || '';
    const lb = locationById.get(Number(b.location_id))?.name || '';
    if (la !== lb) return la.localeCompare(lb, 'de');
    return String(a.name || '').localeCompare(String(b.name || ''), 'de');
  });
  for (const fi of sortedFree) {
    const locName = locationById.get(Number(fi.location_id))?.name || '';
    const q = Number(fi.quantity);
    const qtyStr = Number.isFinite(q) ? String(q).replace('.', ',') : '';
    rows.push(
      [
        stichtag,
        label,
        locName,
        '',
        fi.name || '',
        fi.unit || '',
        'frei_gezaehlt',
        qtyStr,
        '',
        '',
      ]
        .map(esc)
        .join(';'),
    );
  }

  if (hasValue) {
    rows.push(
      [
        '',
        '',
        '',
        '',
        '',
        'Summe Wert (nur gezaehlt)',
        '',
        '',
        '',
        String(totalValue).replace('.', ','),
      ]
        .map(esc)
        .join(';'),
    );
  }

  return '\uFEFF' + rows.join('\r\n');
}

/**
 * @param {string} csv
 * @param {string} filename
 * @returns {{ url: string, filename: string, opened: boolean }}
 */
export function downloadCsv(csv, filename) {
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const safeName = filename.replace(/[^\wäöüÄÖÜß.\-]+/g, '_') || 'Inventur.csv';

  const a = document.createElement('a');
  a.href = url;
  a.download = safeName;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();

  const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent || '');
  let opened = true;
  if (isMobile) {
    const popup = window.open(url, '_blank');
    if (!popup) {
      opened = false;
    }
  }

  return { url, filename: safeName, opened };
}
