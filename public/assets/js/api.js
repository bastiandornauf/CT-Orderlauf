function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

export async function fetchInventoryPayload(stichtag, label = '') {
  const u = new URL('/api/inventory/payload', window.location.origin);
  if (stichtag) {
    u.searchParams.set('stichtag', stichtag);
  }
  const res = await fetch(u.toString(), {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  const raw = await res.text();
  let data;
  try {
    data = raw ? JSON.parse(raw) : {};
  } catch {
    if (res.redirected || raw.includes('login') || raw.includes('Anmelden')) {
      throw new Error('Sitzung abgelaufen – bitte erneut anmelden.');
    }
    throw new Error(
      `Server-Antwort ungültig (HTTP ${res.status}). ` +
        'Prüfen Sie die Datenbank-Migration oder Server-Logs.',
    );
  }
  if (!res.ok || !data.ok) {
    throw new Error(data.error || `Inventur laden fehlgeschlagen (HTTP ${res.status})`);
  }
  return { ...data, label: String(label || '').trim() };
}

export async function fetchPayload(targetDate) {
  const u = new URL('/api/order/payload', window.location.origin);
  u.searchParams.set('target_date', targetDate);
  const res = await fetch(u.toString(), {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });
  const data = await res.json();
  if (!res.ok || !data.ok) {
    throw new Error(data.error || 'Laden fehlgeschlagen');
  }
  return data;
}

/**
 * @param {{ to: string, subject: string, body: string, cc?: string }} params
 * @returns {Promise<{ ok: boolean, error?: string }>}
 */
/**
 * Artikel-Stammdaten speichern (JSON-API, nur Update bestehender Artikel).
 * @param {object} body id, name, unit, location_id, sort_order, min_stock, max_stock, active, supplier_links
 */
export async function saveItem(body) {
  const res = await fetch('/api/items/save', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ ...body, _csrf: csrfToken() }),
  });
  const data = await res.json();
  if (!res.ok || !data.ok) {
    throw new Error(data.error || 'Speichern fehlgeschlagen');
  }
  return data;
}

/**
 * Neuen Artikel-Stammdatensatz anlegen (JSON-API, nur Editor).
 * @param {{ name: string, unit?: string, location_id: number, valuation_price?: string|number|null, supplier_links?: {supplier_id:number,priority?:number}[] }} body
 */
export async function createItem(body) {
  const res = await fetch('/api/items/create', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ ...body, _csrf: csrfToken() }),
  });
  const data = await res.json();
  if (!res.ok || !data.ok) {
    throw new Error(data.error || 'Anlegen fehlgeschlagen');
  }
  return data;
}

export async function sendMail(params) {
  const res = await fetch('/api/order/send-mail', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ ...params, _csrf: csrfToken() }),
  });
  const data = await res.json();
  if (!res.ok || !data.ok) {
    throw new Error(data.error || 'Versand fehlgeschlagen');
  }
  return data;
}

/**
 * @param {object} body
 * @returns {Promise<Blob>}
 */
export async function downloadSupplierPdf(body) {
  const res = await fetch('/api/pdf/supplier', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/pdf',
    },
    body: JSON.stringify({ ...body, _csrf: csrfToken() }),
  });
  if (!res.ok) {
    const t = await res.text();
    throw new Error(t || 'PDF fehlgeschlagen');
  }
  return res.blob();
}
