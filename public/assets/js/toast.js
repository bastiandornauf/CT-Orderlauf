let _toastTimer = null;

/** Kurze Hinweise; Fehler länger und rot hervorgehoben. */
export function showToast(msg, durationMs = 2500, isError = false) {
  const el = document.getElementById('toast-float');
  if (!el) return;
  el.textContent = msg;
  el.classList.toggle('toast-float--error', !!isError);
  el.classList.add('toast-float--visible');
  clearTimeout(_toastTimer);
  _toastTimer = setTimeout(() => {
    el.classList.remove('toast-float--visible', 'toast-float--error');
  }, isError ? Math.max(durationMs, 5000) : durationMs);
}
