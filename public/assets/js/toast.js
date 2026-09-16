let _toastTimer = null;

/**
 * Kurze Hinweise. Farben nur über `.toast--success|warn|error` (Light/Dark-Tokens).
 *
 * @param {string} msg
 * @param {number} [durationMs]
 * @param {boolean|'error'|'warn'|'success'} [kind] true = error (alt)
 */
export function showToast(msg, durationMs = 2500, kind = 'success') {
  const el = document.getElementById('toast-float');
  if (!el) return;
  const variant =
    kind === true || kind === 'error' ? 'error' : kind === 'warn' ? 'warn' : 'success';
  const isError = variant === 'error';
  el.textContent = msg;
  el.className = `toast toast-float toast--${variant}`;
  if (isError) {
    el.classList.add('toast-float--error');
  }
  el.setAttribute('role', isError ? 'alert' : 'status');
  el.setAttribute('aria-live', isError ? 'assertive' : 'polite');
  el.classList.add('toast-float--visible');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => {
    el.classList.remove('toast-float--visible', 'toast-float--error');
  }, isError ? Math.max(durationMs, 5000) : durationMs);
}
