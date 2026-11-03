import { showToast } from './toast.js';

function csrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

async function postJson(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ ...body, _csrf: csrfToken() }),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `Fehler (HTTP ${res.status})`);
  return data;
}

/** Posteingang (nur Admin): Abruf, Erledigt/Art ändern */
export function registerInboxAlpine(Alpine) {
  Alpine.data('inboxPage', () => ({
    busy: false,
    async fetchNow() {
      if (!navigator.onLine) {
        showToast('Offline – Abruf nicht möglich.', 3000, 'warn');
        return;
      }
      this.busy = true;
      try {
        const r = await postJson('/api/posteingang/fetch', { force: true });
        if (!r.ok) throw new Error(r.error || 'Abruf fehlgeschlagen');
        sessionStorage.setItem('inboxToast', r.fetched ? `${r.fetched} neue Nachricht(en).` : 'Keine neuen Nachrichten.');
        location.reload();
      } catch (e) {
        showToast(e?.message || 'Abruf fehlgeschlagen', 6000, 'error');
        this.busy = false;
      }
    },
    async update(id, change) {
      try {
        await postJson('/api/posteingang/update', { id, ...change });
        location.reload();
      } catch (e) {
        showToast(e?.message || 'Speichern fehlgeschlagen', 5000, 'error');
      }
    },
    init() {
      const msg = sessionStorage.getItem('inboxToast');
      if (msg) {
        sessionStorage.removeItem('inboxToast');
        showToast(msg);
      }
    },
  }));

  /** Startseite: Hinweis auf neue Nachrichten, holt im Hintergrund ab (serverseitig gedrosselt) */
  Alpine.data('dashboardInbox', () => ({
    counts: null,
    init() {
      try {
        this.counts = JSON.parse(this.$el.dataset.counts || 'null');
      } catch {
        this.counts = null;
      }
      if (!navigator.onLine) return;
      postJson('/api/posteingang/fetch', {})
        .then((r) => {
          if (r.counts) this.counts = r.counts;
        })
        .catch(() => {});
    },
    get attention() {
      return this.counts ? this.counts.lieferant + this.counts.sonstiges : 0;
    },
    get visible() {
      return !!this.counts && (this.attention > 0 || this.counts.belege > 0);
    },
    get headline() {
      if (!this.counts) return '';
      const parts = [];
      if (this.counts.lieferant) {
        const names = (this.counts.suppliers || []).join(', ');
        parts.push(this.counts.lieferant === 1 ? `Antwort von ${names}` : `${this.counts.lieferant} Lieferanten-Antworten (${names})`);
      }
      if (this.counts.sonstiges) parts.push(`${this.counts.sonstiges} sonstige`);
      if (this.counts.belege) parts.push(`${this.counts.belege} offene Beleg${this.counts.belege === 1 ? '' : 'e'}`);
      return parts.join(' · ');
    },
    get href() {
      return this.attention > 0 ? '/posteingang' : '/posteingang?f=belege';
    },
  }));
}
