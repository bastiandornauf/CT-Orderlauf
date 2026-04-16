import Alpine from 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/module.esm.js';
import { registerOrderAlpine, dashboardPageData } from './order-pages.js';
import { initOnlineIndicator, registerServiceWorker } from './offline.js';

registerOrderAlpine(Alpine);
Alpine.data('dashboardPage', () => dashboardPageData());

Alpine.data('appHeader', () => ({
  navOpen: false,
  sleek: false,
  clockLabel: '',
  clockIso: '',
  init() {
    this.sleek = localStorage.getItem('sleekMode') === '1';
    document.body.classList.toggle('sleek', this.sleek);
    this.tickClock();
    setInterval(() => this.tickClock(), 30000);

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
  tickClock() {
    const d = new Date();
    this.clockIso = d.toISOString();
    this.clockLabel = new Intl.DateTimeFormat('de-DE', {
      hour: '2-digit',
      minute: '2-digit',
    }).format(d);
  },
}));

initOnlineIndicator();
registerServiceWorker();
Alpine.start();
