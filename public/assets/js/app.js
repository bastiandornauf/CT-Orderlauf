import { initOnlineIndicator, registerServiceWorker } from './offline.js';
import * as storage from './storage.js';
import './order-pages.js';

initOnlineIndicator();
registerServiceWorker();

document.addEventListener('alpine:init', () => {
  window.Alpine.data('dashboardPage', () => ({
    hasRound: false,
    roundStatus: 'idle',
    async init() {
      const row = await storage.getOrderRound();
      this.hasRound = !!row;
      this.roundStatus = row?.status || 'idle';
    },
  }));
});
