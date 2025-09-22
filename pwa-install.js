// /pwa-install.js
(function () {
  const btn = document.getElementById('btnInstall');
  if (!btn) return;

  let deferred;

  // Chrome/Edge/Android: capture the prompt event and show the button
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferred = e;
    btn.style.display = 'inline-flex';
  });

  // Button click => show the prompt
  btn.addEventListener('click', async () => {
    if (!deferred) return;
    deferred.prompt();
    try { await deferred.userChoice; } catch(e) {}
    deferred = null;
    btn.style.display = 'none';
  });

  // If already installed, hide button
  window.addEventListener('appinstalled', () => {
    btn.style.display = 'none';
    deferred = null;
  });

  // iOS Safari doesn’t fire beforeinstallprompt — keep hidden
  const isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
  if (isIOS && isSafari) {
    btn.title = 'On iOS: Share ▸ “Add to Home Screen”';
  }
})();
