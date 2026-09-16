import Alpine from 'https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/module.esm.js';
import { registerOrderAlpine, dashboardPageData } from './order-pages.js';
import { registerInventoryAlpine } from './inventory-pages.js';
import { initOnlineIndicator, registerServiceWorker } from './offline.js';

registerOrderAlpine(Alpine);
registerInventoryAlpine(Alpine);
Alpine.data('dashboardPage', () => dashboardPageData());

Alpine.data('appHeader', () => ({
  navOpen: false,
  theme: 'system',
  resolvedDark: false,
  clockLabel: '',
  clockIso: '',
  init() {
    this.theme = localStorage.getItem('uiTheme') || 'system';
    this.applyTheme();
    this.tickClock();
    setInterval(() => this.tickClock(), 30000);

    this.$watch('navOpen', (open) => {
      document.body.classList.toggle('app-body--nav-open', !!open);
    });
    // Nach Browser-Zurück (bfcache) kann das Menü sonst hängen (grauer Screen)
    window.addEventListener('pageshow', (e) => {
      if (e.persisted) this.navOpen = false;
    });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (this.theme === 'system') this.applyTheme();
    });
  },
  applyTheme() {
    if (this.theme === 'dark' || this.theme === 'light') {
      document.documentElement.setAttribute('data-theme', this.theme);
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    this.resolvedDark =
      this.theme === 'dark' ||
      (this.theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
  },
  toggleTheme() {
    this.theme = this.resolvedDark ? 'light' : 'dark';
    localStorage.setItem('uiTheme', this.theme);
    this.applyTheme();
  },
  toggleNav() {
    this.navOpen = !this.navOpen;
  },
  closeNav() {
    this.navOpen = false;
  },
  closeNavLater() {
    setTimeout(() => { this.navOpen = false; }, 50);
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
