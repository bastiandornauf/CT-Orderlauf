const SCALE = 10000n;

const PURCHASE_STATUS_LABELS = {
  ok: 'ok',
  no_purchase: 'kein Einkaufspreis',
  not_convertible: 'nicht umrechenbar',
};

/** Menge (Zahl aus der Zählung) → ganze Zahl in Zehntausendsteln */
function toUnits(q) {
  return BigInt(Math.round(Number(q) * 10000));
}

/** Zehntausendstel → "8,68" (kaufmännisch gerundet, Tausenderpunkt) */
export function formatUnits(units) {
  const cents = (units + 50n) / 100n;
  const whole = cents / 100n;
  const frac = String(cents % 100n).padStart(2, '0');
  return `${whole.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${frac}`;
}

/**
 * Wert nach letztem Einkaufspreis in Zehntausendsteln, oder null, wenn keine Bewertung möglich.
 * @param {number} quantity gezählte Menge in der Zähleinheit
 * @param {object|null} valuation purchase_valuation aus dem Inventur-Snapshot
 */
export function purchaseValueUnits(quantity, valuation) {
  if (valuation?.status !== 'ok' || valuation.unit_price_units == null) return null;
  const q = Number(quantity);
  if (!Number.isFinite(q) || q < 0) return null;
  return (toUnits(q) * BigInt(valuation.unit_price_units) + SCALE / 2n) / SCALE;
}

/** Status-Text für CSV/Anzeige; ältere Snapshots ohne Bewertung → „nicht ermittelt“ */
export function purchaseStatusLabel(valuation) {
  if (!valuation) return 'nicht ermittelt';
  return PURCHASE_STATUS_LABELS[valuation.status] || valuation.status;
}

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
    // ab hier: Bewertung nach letztem bestätigten Einkauf bis Stichtag (neue Spalten hinten,
    // damit bestehende Excel-Auswertungen ihre Spalten behalten)
    'einkaufspreis_je_einheit',
    'einkaufswert',
    'einkauf_status',
    'einkauf_lieferant',
    'einkauf_datum',
    'einkauf_basis',
    'einkauf_beleg',
    'alias',
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
  let totalPurchase = 0n;
  let hasPurchase = false;

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

    const pv = it.purchase_valuation || null;
    let purchaseValueStr = '';
    if (line?.status === 'counted') {
      const q = Number(line.quantity);
      const units = q === 0 ? null : purchaseValueUnits(q, pv);
      if (units != null) {
        purchaseValueStr = formatUnits(units);
        totalPurchase += units;
        hasPurchase = true;
      }
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
        pv?.status === 'ok' ? pv.unit_price : '',
        purchaseValueStr,
        purchaseStatusLabel(pv),
        pv?.supplier || '',
        pv?.price_date || '',
        pv?.basis || '',
        pv?.document_ref || '',
        String(it.alias ?? '').trim(),
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
        '',
        '',
        'frei erfasst',
        '',
        '',
        '',
        '',
        '',
      ]
        .map(esc)
        .join(';'),
    );
  }

  if (hasValue || hasPurchase) {
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
        hasValue ? String(totalValue).replace('.', ',') : '',
        '',
        hasPurchase ? formatUnits(totalPurchase) : '',
        '',
        '',
        '',
        '',
        '',
        '',
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
