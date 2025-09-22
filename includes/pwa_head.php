<?php
// /includes/pwa_head.php — put inside <head> of each page
// Assumes you’ve placed manifest + icons at /manifest.webmanifest and /assets/icons/*
$PAGE_TITLE = $PAGE_TITLE ?? 'Safety Tours';
$THEME_COLOR = '#0ea5e9';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($PAGE_TITLE) ?></title>

<!-- PWA basics -->
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="<?= $THEME_COLOR ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Safety Tours">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/pwa/icons/icon-apple-180.png">
<meta name="theme-color" content="#0ea5e9">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(()=>{});
  }
</script>

<!-- Icons (Android + iOS + favicon) -->
<link rel="apple-touch-icon" href="/assets/pwa/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/pwa/icons/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/pwa/icons/favicon-16.png">
<link rel="mask-icon" href="/assets/pwa/icons/safari-pinned-tab.svg" color="#0ea5e9">

<!-- Base styles for nav buttons (kept minimal so pages can bring their own CSS too) -->
<style>
  .dt-btn{
    padding:10px 16px;border-radius:14px;border:1px solid #1f2937;
    background:linear-gradient(180deg, rgba(14,165,233,.25), rgba(2,132,199,.12));
    color:#dbeafe;text-decoration:none;font-weight:800;display:inline-flex;align-items:center;gap:8px
  }
  .dt-btn.active{
    background:linear-gradient(180deg,#0ea5e9,#0284c7);color:#00131a;border-color:#075985;
    box-shadow:0 14px 28px rgba(2,132,199,.20) inset
  }
  .dt-pill{padding:8px 12px;border-radius:999px;border:1px solid #1f2937;background:#0f172a;color:#94a3b8;text-decoration:none}
</style>

<!-- Register service worker + install helper (defer so HTML parses first) -->
<script>
  // Service worker
  window.addEventListener('load', () => {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
    }
  });
</script>
<script src="/pwa-install.js" defer></script>
