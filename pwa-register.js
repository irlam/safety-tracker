// Registers the service worker when supported
(function(){
  if (!('serviceWorker' in navigator)) return;
  window.addEventListener('load', function(){
    navigator.serviceWorker.register('/sw.js')
      .catch(err => console.warn('SW register failed:', err));
  });
})();
