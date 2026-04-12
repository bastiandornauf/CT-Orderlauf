function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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
