import Alpine from 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/module.esm.js';
import { registerOrderAlpine } from './order-pages.js';
import { initOnlineIndicator, registerServiceWorker } from './offline.js';
import * as storage from './storage.js';

registerOrderAlpine(Alpine);

Alpine.data('dashboardPage', () => ({
  hasRound: false,
  roundStatus: 'idle',
  async init() {
    const row = await storage.getOrderRound();
    this.hasRound = !!row;
    this.roundStatus = row?.status || 'idle';
  },
}));

initOnlineIndicator();
registerServiceWorker();
Alpine.start();
