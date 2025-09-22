<?php
// /includes/nav.php — shared top nav (gradient buttons + admin menu + PWA install button)
if (!function_exists('is_admin')) {
  $auth = __DIR__ . '/auth.php';
  if (is_file($auth)) require_once $auth;
}

if (!function_exists('render_nav')) {
function render_nav(string $active = ''): void {
  $isAdmin = function_exists('is_admin') ? is_admin() : false;
  $me      = function_exists('auth_user') ? (auth_user()['email'] ?? null) : null;
  $isActive = fn(string $k) => $active === $k;
  ?>
  <style>
    /* Scoped nav styles */
    .dt-nav{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:14px;
            background:radial-gradient(1200px 800px at 75% -100px, rgba(14,165,233,.10), transparent 60%),#0b1220;
            color:#e5e7eb;border-bottom:1px solid #1f2937;font:16px/1.4 system-ui,-apple-system,Segoe UI,Roboto}
    .dt-brand{display:flex;align-items:center;gap:10px}
    .dt-brand img{height:28px}
    .dt-brand strong{font-weight:800;font-size:1.05rem}
    .dt-links{display:flex;gap:10px;align-items:center}
    .dt-btn{padding:10px 16px;border-radius:14px;border:1px solid #1f2937;
      background:linear-gradient(180deg, rgba(14,165,233,.25), rgba(2,132,199,.12));
      color:#dbeafe;text-decoration:none;font-weight:800;display:inline-flex;align-items:center;gap:8px}
    .dt-btn:hover{filter:brightness(1.08)}
    .dt-btn.active{background:linear-gradient(180deg,#0ea5e9,#0284c7);color:#00131a;border-color:#075985;box-shadow:0 14px 28px rgba(2,132,199,.20) inset}
    .dt-admin{position:relative}
    .dt-admin details{position:relative}
    .dt-admin summary{list-style:none;cursor:pointer}
    .dt-admin summary{padding:10px 16px;border-radius:14px;border:1px solid #1f2937;
      background:linear-gradient(180deg, rgba(14,165,233,.25), rgba(2,132,199,.12));
      color:#dbeafe;font-weight:800;display:flex;align-items:center;gap:8px}
    .dt-admin summary::-webkit-details-marker{display:none}
    .dt-admin[open] summary{background:linear-gradient(180deg,#0ea5e9,#0284c7);color:#00131a;border-color:#075985}
    .dt-menu{position:absolute;right:0;top:46px;min-width:220px;z-index:30;background:#0f172a;border:1px solid #1f2937;border-radius:14px;padding:8px}
    .dt-menu a{display:block;padding:10px 12px;border-radius:10px;color:#e5e7eb;text-decoration:none}
    .dt-menu a:hover{background:#0b1220}
    #btnInstall{display:none;padding:10px 16px;border-radius:14px;border:1px solid #1f2937;
      background:linear-gradient(180deg,#22c55e,#16a34a);color:#00130b;font-weight:800}
    @media (max-width:720px){.dt-links{flex-wrap:wrap}}
  </style>

  <nav class="dt-nav">
    <div class="dt-brand">
      <img src="/assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
      <strong>Safety Tours</strong>
    </div>

    <div class="dt-links">
      <a class="dt-btn <?= $isActive('dashboard') ? 'active' : '' ?>" href="/dashboard.php" <?= $isActive('dashboard')?'aria-current="page"':''; ?>>Dashboard</a>
      <a class="dt-btn <?= $isActive('new') ? 'active' : '' ?>" href="/form.php" <?= $isActive('new')?'aria-current="page"':''; ?>>New Tour</a>
      <a class="dt-btn <?= $isActive('actions') ? 'active' : '' ?>" href="/actions.php" <?= $isActive('actions')?'aria-current="page"':''; ?>>Actions</a>

      <!-- PWA install button (logic lives in /pwa-install.js, included via pwa_head.php) -->
      <button id="btnInstall" type="button" title="Install app">Install app</button>

      <?php if ($isAdmin): ?>
        <div class="dt-admin">
          <details <?= $isActive('admin')?'open':''; ?>>
            <summary><?= $isActive('admin') ? 'Admin (open)' : 'Admin' ?></summary>
            <div class="dt-menu">
              <a href="/admin_users.php"  <?= $isActive('admin_users') ? 'style="background:#0b1220"' : '' ?>>Users & Roles</a>
              <a href="/audit.php"        <?= $isActive('audit') ? 'style="background:#0b1220"' : '' ?>>Audit Log</a>
              <a href="/health.php">Health Check</a>
              <a href="/mail_test.php">Mail Test</a>
            </div>
          </details>
        </div>
      <?php endif; ?>

      <?php if ($me): ?>
        <a class="dt-btn" href="/logout.php" title="<?= htmlspecialchars($me) ?>">Logout</a>
      <?php else: ?>
        <a class="dt-btn <?= $isActive('login') ? 'active' : '' ?>" href="/login_admin.php">Login</a>
      <?php endif; ?>
    </div>
  </nav>
  <?php
}}
