import { initOnlineIndicator, registerServiceWorker } from './offline.js';
import { registerOrderAlpine, dashboardPageData } from './order-pages.js';
import { registerInventoryAlpine } from './inventory-pages.js';

initOnlineIndicator();
registerServiceWorker();

document.addEventListener('alpine:init', () => {
  registerInventoryAlpine(window.Alpine);
  registerOrderAlpine(window.Alpine);
  window.Alpine.data('dashboardPage', () => dashboardPageData());
});
