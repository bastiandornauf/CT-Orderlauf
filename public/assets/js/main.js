import Alpine from 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/module.esm.js';
import { registerOrderAlpine } from './order-pages.js';
import { initOnlineIndicator, registerServiceWorker } from './offline.js';
import * as storage from './storage.js';

registerOrderAlpine(Alpine);

Alpine.data('appHeader', () => ({
  navOpen: false,
  init() {
    this.$watch('navOpen', (open) => {
      document.body.classList.toggle('app-body--nav-open', !!open);
    });
  },
  toggleNav() {
    this.navOpen = !this.navOpen;
  },
  closeNav() {
    this.navOpen = false;
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
