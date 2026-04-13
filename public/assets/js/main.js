import Alpine from 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/module.esm.js';
import { registerOrderAlpine } from './order-pages.js';
import { initOnlineIndicator, registerServiceWorker } from './offline.js';
import * as storage from './storage.js';

registerOrderAlpine(Alpine);

Alpine.data('appHeader', () => ({
  navOpen: false,
  sleek: false,
  init() {
    this.sleek = localStorage.getItem('sleekMode') === '1';
    document.body.classList.toggle('sleek', this.sleek);

    this.$watch('navOpen', (open) => {
      document.body.classList.toggle('app-body--nav-open', !!open);
    });
    // Nach Browser-Zurück (bfcache) kann das Menü sonst hängen (grauer Screen)
    window.addEventListener('pageshow', (e) => {
      if (e.persisted) this.navOpen = false;
    });
  },
  toggleNav() {
    this.navOpen = !this.navOpen;
  },
  closeNav() {
    this.navOpen = false;
  },
  toggleSleek() {
    this.sleek = !this.sleek;
    localStorage.setItem('sleekMode', this.sleek ? '1' : '0');
    document.body.classList.toggle('sleek', this.sleek);
  },
}));

Alpine.data('dashboardPage', () => ({
  hasRound: false,
  roundStatus: 'idle',
  get roundStatusLabel() {
    const m = {
      prepared: 'Vorbereitet – Rundgang noch nicht begonnen',
      active: 'Rundgang läuft',
      ready_for_review: 'Bereit zur Kontrolle und Ausgabe',
      finalized: 'Abgeschlossen – neue Runde möglich',
      paused: 'Runde pausiert',
    };
    return m[this.roundStatus] || '';
  },
  async init() {
    const row = await storage.getOrderRound();
    this.hasRound = !!row;
    this.roundStatus = row?.status || 'idle';
  },
}));

initOnlineIndicator();
registerServiceWorker();
Alpine.start();
