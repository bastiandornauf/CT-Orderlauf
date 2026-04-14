import { initOnlineIndicator, registerServiceWorker } from './offline.js';
import { registerOrderAlpine, dashboardPageData } from './order-pages.js';

initOnlineIndicator();
registerServiceWorker();

document.addEventListener('alpine:init', () => {
  registerOrderAlpine(window.Alpine);
  window.Alpine.data('dashboardPage', dashboardPageData);
});
