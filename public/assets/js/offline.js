function setOnlineUi(online) {
  const badge = document.querySelector('[data-offline-badge]');
  if (!badge) return;
  badge.textContent = online ? 'Online' : 'Offline';
  badge.classList.toggle('status-badge--offline', !online);
  badge.classList.toggle('status-badge--ok', online);
}

export function initOnlineIndicator() {
  setOnlineUi(navigator.onLine);
  window.addEventListener('online', () => setOnlineUi(true));
  window.addEventListener('offline', () => setOnlineUi(false));
}

export async function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;
  try {
    await navigator.serviceWorker.register('/sw.js', { scope: '/' });
  } catch (e) {
    console.warn('Service worker registration failed', e);
  }
}
