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
  if (!res.ok || !data.ok) throw new Error(data.error || `Fehler (HTTP ${res.status})`);
  return data;
}

export const STATUS_LABELS = { change: 'neu/geändert', same: 'schon so', warn: 'prüfen', error: 'Fehler' };

/** Pflegepaket: Datei wählen → Vorschau → ausgewählte Zeilen übernehmen */
export function registerMaintenanceAlpine(Alpine) {
  Alpine.data('packageImport', () => ({
    pkg: null,
    fileName: '',
    title: '',
    rows: [],
    selected: {},
    error: '',
    busy: false,
    result: null,
    get selectedCount() {
      return Object.values(this.selected).filter(Boolean).length;
    },
    get counts() {
      const c = { change: 0, same: 0, warn: 0, error: 0 };
      for (const r of this.rows) c[r.status] = (c[r.status] || 0) + 1;
      return c;
    },
    statusLabel(s) {
      return STATUS_LABELS[s] || s;
    },
    async onFile(event) {
      const file = event.target.files?.[0];
      this.error = '';
      this.result = null;
      this.rows = [];
      if (!file) return;
      this.fileName = file.name;
      try {
        this.pkg = JSON.parse(await file.text());
      } catch {
        this.error = 'Die Datei ist kein gültiges JSON.';
        return;
      }
      await this.loadPreview();
    },
    async loadPreview() {
      this.busy = true;
      try {
        const data = await postJson('/api/pflege/paket/preview', { package: this.pkg });
        this.title = data.title;
        this.rows = data.rows;
        // vorbelegt: nur eindeutige Änderungen; Warnungen bewusst selbst anhaken
        this.selected = Object.fromEntries(data.rows.map((r) => [r.index, r.status === 'change']));
      } catch (e) {
        this.error = e?.message || 'Vorschau fehlgeschlagen';
      } finally {
        this.busy = false;
      }
    },
    selectAll(on) {
      for (const r of this.rows) {
        if (r.status === 'change' || r.status === 'warn') this.selected[r.index] = on;
      }
    },
    async apply() {
      if (!navigator.onLine) {
        this.error = 'Nur online möglich.';
        return;
      }
      const selected = this.rows.filter((r) => this.selected[r.index]).map((r) => r.index);
      if (selected.length === 0) return;
      this.busy = true;
      this.error = '';
      try {
        this.result = await postJson('/api/pflege/paket/apply', { package: this.pkg, selected });
        showToast(`${this.result.applied} Änderungen übernommen.`);
        await this.loadPreview();
      } catch (e) {
        this.error = e?.message || 'Übernahme fehlgeschlagen';
      } finally {
        this.busy = false;
      }
    },
  }));
}
