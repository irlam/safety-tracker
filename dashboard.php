<?php  
// /dashboard.php
// auth (optional-safe)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    if (function_exists('auth_check')) {
        auth_check();           // or: auth_check(true); // if this page is admin-only
    }
}

require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

function table_exists(PDO $pdo, string $name): bool {
  try {
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$name]);
    return (bool)$stmt->fetchColumn();
  } catch (Throwable $e) { return false; }
}

$pdo = db();

// admin flag (safe if auth helpers missing)
$isAdmin = false;
if (function_exists('auth_is_admin')) { $isAdmin = (bool)auth_is_admin(); }
elseif (function_exists('is_admin'))  { $isAdmin = (bool)is_admin(); }

/* -----------------------------
   Filters (GET)
------------------------------*/
$from   = trim($_GET['from']   ?? '');
$to     = trim($_GET['to']     ?? '');
$q      = trim($_GET['q']      ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$args  = [];

if ($from !== '') { $where[] = 'tour_date >= ?'; $args[] = date('Y-m-d 00:00:00', strtotime($from)); }
if ($to   !== '') { $where[] = 'tour_date <= ?'; $args[] = date('Y-m-d 23:59:59', strtotime($to)); }
if ($q    !== '') { $where[] = '(site LIKE ? OR area LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($status === 'Open' || $status === 'Closed') { $where[] = 'status = ?'; $args[] = $status; }

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

/* -----------------------------
   Totals & rows
------------------------------*/
$rows = [];
try {
  $stmt = $pdo->prepare("
    SELECT id, tour_date, site, area, lead_name, status, score_percent
    FROM safety_tours
    $whereSql
    ORDER BY id DESC
    LIMIT 500
  ");
  $stmt->execute($args);
  $rows = $stmt->fetchAll();
} catch (Throwable $e) { $rows = []; }

$totalTours = 0;
$openCount  = 0;
$closedCount= 0;
$avgScore   = null;

if ($rows) {
  $totalTours = count($rows);
  $sum = 0.0; $n = 0;
  foreach ($rows as $r) {
    if (($r['status'] ?? '') === 'Open')   $openCount++;
    if (($r['status'] ?? '') === 'Closed') $closedCount++;
    if ($r['score_percent'] !== null && $r['score_percent'] !== '') {
      $sum += (float)$r['score_percent']; $n++;
    }
  }
  $avgScore = $n ? round($sum / $n, 2) : null;
}

/* -----------------------------
   Sparkline (last 30 days)
------------------------------*/
$start30 = date('Y-m-d', strtotime('-29 days'));
$countsByDay = array_fill_keys(
  array_map(fn($i)=>date('Y-m-d', strtotime("$start30 +$i day")), range(0,29)), 0
);

try {
  // Apply filters except from/to (we always show last 30 days)
  $sparkWhere = ["DATE(tour_date) BETWEEN ? AND ?"];
  $sparkArgs  = [$start30, date('Y-m-d')];

  if ($q    !== '') { $sparkWhere[] = '(site LIKE ? OR area LIKE ?)'; $sparkArgs[] = "%$q%"; $sparkArgs[] = "%$q%"; }
  if ($status === 'Open' || $status === 'Closed') { $sparkWhere[] = 'status = ?'; $sparkArgs[] = $status; }

  $sparkSql = 'WHERE '.implode(' AND ', $sparkWhere);
  $stmt = $pdo->prepare("
    SELECT DATE(tour_date) d, COUNT(*) c
    FROM safety_tours
    $sparkSql
    GROUP BY DATE(tour_date)
  ");
  $stmt->execute($sparkArgs);
  foreach ($stmt->fetchAll() as $row) {
    $d = $row['d'];
    if (isset($countsByDay[$d])) $countsByDay[$d] = (int)$row['c'];
  }
} catch (Throwable $e) {}

/* Build sparkline path */
$sparkW = 360; $sparkH = 44; $maxY = max(1, max($countsByDay));
$pts = [];
$i = 0; $n = count($countsByDay);
foreach ($countsByDay as $d => $c) {
  $x = ($n>1) ? ($i * ($sparkW/($n-1))) : 0;
  // Invert for SVG; leave 6px top/btm padding
  $y = 6 + ($sparkH-12) * (1 - ($c / $maxY));
  $pts[] = $x.','.$y;
  $i++;
}
$path = 'M '.implode(' L ', $pts);

/* -----------------------------
   Actions stats (optional table)
------------------------------*/
$actsLow=$actsMed=$actsHigh=0;
if (table_exists($pdo,'safety_actions')) {
  try {
    $aw = []; $aa = [];
    if ($from !== '') { $aw[]='created_at >= ?'; $aa[]=date('Y-m-d 00:00:00', strtotime($from)); }
    if ($to   !== '') { $aw[]='created_at <= ?'; $aa[]=date('Y-m-d 23:59:59', strtotime($to)); }
    if ($q    !== '') { $aw[]='(site LIKE ? OR area LIKE ?)'; $aa[]="%$q%"; $aa[]="%$q%"; }
    if ($status === 'Open' || $status === 'Closed') { $aw[]='tour_status = ?'; $aa[]=$status; }
    $awSql = $aw ? ('WHERE '.implode(' AND ',$aw)) : '';
    // coalesce priority/severity to be safe with either schema
    $stmt = $pdo->prepare("
      SELECT COALESCE(priority, severity) AS p, COUNT(*) c
      FROM safety_actions
      $awSql
      GROUP BY COALESCE(priority, severity)
    ");
    $stmt->execute($aa);
    foreach ($stmt->fetchAll() as $r) {
      $p = strtolower($r['p'] ?? '');
      if ($p==='low')    $actsLow  = (int)$r['c'];
      if ($p==='medium') $actsMed  = (int)$r['c'];
      if ($p==='high')   $actsHigh = (int)$r['c'];
    }
  } catch (Throwable $e) {}
}

$showDeletedToast = !empty($_GET['deleted']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<?php
  // PWA head — this should output: <title>, <meta theme-color>, manifest link, icons and SW registration
  $PAGE_TITLE = 'Dashboard — Safety Tours';
  require __DIR__ . '/includes/pwa_head.php';
?>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<!-- If your pwa_head.php does NOT include the JS, keep this line; harmless if duplicated -->
<script src="/pwa-install.js" defer></script>
<style>
  :root{
    --bg:#0b1220; --text:#e5e7eb; --muted:#94a3b8; --border:#1f2937;
    --card:#0f172a; --accent1:#0ea5e9; --accent2:#0284c7;
    --ok:#16a34a; --warn:#b08900; --high:#7f1d1d;
    --radius:18px
  }
  *{box-sizing:border-box} html,body{margin:0;padding:0}
  body{background:
    radial-gradient(1200px 800px at 75% -100px, rgba(14,165,233,.10), transparent 60%),
    var(--bg);
    color:var(--text); font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto}
  a{color:#93c5fd; text-decoration:none}

  .wrap{max-width:1200px;margin:0 auto;padding:18px}

  /* Nav */
  .nav{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:14px}
  .brand{display:flex;align-items:center;gap:10px}
  .brand img{height:30px}
  .brand h1{font-size:1.25rem;margin:0}
  .navbtns{display:flex;gap:14px}
  .navbtn{
    padding:10px 16px;border-radius:14px;border:1px solid var(--border);
    background:linear-gradient(180deg, rgba(14,165,233,.25), rgba(2,132,199,.12));
    box-shadow:0 12px 24px rgba(2,132,199,.08) inset;
    color:#dbeafe;font-weight:800
  }

  /* Cards / layout */
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
  @media (max-width:1100px){.grid{grid-template-columns:repeat(2,1fr)}}
  @media (max-width:620px){.grid{grid-template-columns:1fr}}

  .card{
    background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));
    border:1px solid var(--border); border-radius:var(--radius); padding:16px
  }
  .card h3{margin:2px 0 10px; font-size:1.02rem}
  .big{font-size:2.2rem; font-weight:800}
  .subpill{display:inline-block;margin-top:8px;padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:#0b1220;color:var(--muted)}

  /* Sparkline */
  .spark{width:100%;height:52px;margin-top:12px;border-top:1px dashed #1e293b}
  .spark svg{display:block;width:100%;height:44px}

  /* L/M/H badges */
  .badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}
  .badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 12px;border:1px solid var(--border);font-weight:700}
  .low{background:#064e3b;color:#d1fae5}
  .med{background:#3f2e00;color:#fde68a}
  .hi{ background:#3b0a0a;color:#fecaca}

  /* Filter bar */
  .filters{margin:14px 0}
  .fgrid{display:grid;grid-template-columns:repeat(4,1fr) 160px 120px;gap:12px}
  @media (max-width:1100px){.fgrid{grid-template-columns:1fr 1fr 1fr 1fr}}
  label{display:block;margin:2px 0 6px;color:#cbd5e1}
  input,select{
    width:100%;background:#0b1220;color:var(--text);border:1px solid var(--border);border-radius:12px;padding:10px
  }
  .btn{
    background:linear-gradient(180deg, var(--accent1), var(--accent2));
    border:0;border-radius:14px;color:#00131a;font-weight:800;padding:12px 16px;cursor:pointer;
    box-shadow:0 20px 40px rgba(2,132,199,.25) inset
  }
  .btn-ghost{
    background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.03));
    color:#d1d5db;border:1px solid var(--border)
  }

  /* Table */
  table{width:100%;border-collapse:collapse;margin-top:14px}
  th,td{border:1px solid var(--border);padding:12px 10px}
  th{background:#0f172a;text-align:left}
  .pill{display:inline-block;padding:6px 12px;border-radius:999px;border:1px solid var(--border);background:#0f172a}
  .muted{color:var(--muted)}
  .right{text-align:right}
  .center{display:flex;justify-content:center;align-items:center;height:100%}

  /* Toast */
  .toast {
    position: fixed; right: 16px; top: 16px; z-index: 9999;
    background: linear-gradient(180deg,#22c55e,#16a34a);
    color:#00130b; border:1px solid #065f46; border-radius:14px;
    padding:10px 14px; font-weight:700; box-shadow:0 6px 24px rgba(0,0,0,.25);
    opacity: 0; transform: translateY(-6px); transition: opacity .2s, transform .2s;
  }
  .toast.show { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>
  <div class="wrap">
    <!-- NAV -->
    <nav class="nav">
      <div class="brand">
        <img src="/assets/img/logo.png" alt="McGoff" onerror="this.style.display='none'">
        <h1>Safety Tours</h1>
      </div>
      <div class="navbtns">
        <a class="navbtn" href="/dashboard.php">Dashboard</a>
        <a class="navbtn" href="/form.php">New Tour</a>
        <a class="navbtn" href="/actions.php">Actions</a>
        <!-- PWA install button: hidden until pwa-install.js decides it's eligible -->
        <button id="btnInstall" class="navbtn" type="button" style="display:none">Install app</button>
      </div>
    </nav>

    <?php if ($showDeletedToast): ?>
      <div id="toast" class="toast">Deleted successfully</div>
      <script>
        (function(){
          var t = document.getElementById('toast');
          if(!t) return;
          requestAnimationFrame(function(){ t.classList.add('show'); });
          setTimeout(function(){ t.classList.remove('show'); }, 2600);
        })();
      </script>
    <?php endif; ?>

    <!-- FILTERS -->
    <form class="card filters" method="get" action="dashboard.php">
      <div class="fgrid">
        <div>
          <label>From</label>
          <input type="date" name="from" value="<?= h($from) ?>">
        </div>
        <div>
          <label>To</label>
          <input type="date" name="to" value="<?= h($to) ?>">
        </div>
        <div>
          <label>Site / Area</label>
          <input type="text" name="q" placeholder="search…" value="<?= h($q) ?>">
        </div>
        <div>
          <label>Status</label>
          <select name="status">
            <?php foreach ([''=>'Any','Open'=>'Open','Closed'=>'Closed'] as $v=>$t): ?>
              <option value="<?= h($v) ?>" <?= ($status===$v?'selected':'') ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="center">
          <button class="btn" type="submit">Apply filters</button>
        </div>
        <div class="center">
          <a class="btn btn-ghost" href="dashboard.php">Reset</a>
        </div>
      </div>
    </form>

    <!-- TOP CARDS -->
    <section class="grid">
      <!-- Total Tours -->
      <div class="card">
        <h3>Total Tours</h3>
        <div class="big"><?= (int)$totalTours ?></div>
        <div class="subpill">Last 30 days: <?= array_sum($countsByDay) ?></div>
        <div class="spark">
          <svg viewBox="0 0 <?= $sparkW ?> <?= $sparkH ?>" preserveAspectRatio="none" aria-hidden="true">
            <defs>
              <linearGradient id="grad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#38bdf8" stop-opacity="0.65"/>
                <stop offset="100%" stop-color="#0ea5e9" stop-opacity="0.25"/>
              </linearGradient>
            </defs>
            <path d="<?= h($path) ?>" fill="none" stroke="url(#grad)" stroke-width="3" />
          </svg>
        </div>
      </div>

      <!-- Open vs Closed -->
      <div class="card">
        <h3>Open vs Closed</h3>
        <div class="big"><?= (int)$openCount ?></div>
        <div class="subpill">Closed: <?= (int)$closedCount ?></div>
        <?php
          $total = max(1, $openCount + $closedCount);
          $openPct = round(($openCount/$total)*100);
        ?>
        <div style="height:14px;border-radius:999px;border:1px solid var(--border);background:#0b1220;margin-top:14px;overflow:hidden">
          <div style="height:100%;width:<?= $openPct ?>%;background:linear-gradient(180deg,#16a34a,#15803d)"></div>
        </div>
        <div class="muted" style="margin-top:6px"><?= $openPct ?>% open</div>
      </div>

      <!-- Average Score -->
      <div class="card" style="display:flex;flex-direction:column;justify-content:center;align-items:center">
        <h3 style="width:100%;text-align:left">Average Score % (filtered)</h3>
        <div class="big"><?= $avgScore===null ? '0.00' : number_format($avgScore,2) ?>%</div>
      </div>

      <!-- Actions (optional) -->
      <div class="card">
        <h3>Total Actions (filtered)</h3>
        <div class="big"><?= (int)($actsLow + $actsMed + $actsHigh) ?></div>
        <div class="badges">
          <span class="badge low">Low: <?= (int)$actsLow ?></span>
          <span class="badge med">Medium: <?= (int)$actsMed ?></span>
          <span class="badge hi">High: <?= (int)$actsHigh ?></span>
        </div>
        <div style="margin-top:12px">
          <a class="btn" href="/actions.php">Open register →</a>
        </div>
      </div>
    </section>

    <!-- TABLE -->
    <div class="card" style="margin-top:14px">
      <table>
        <tr>
          <th>#</th>
          <th>Date</th>
          <th>Site / Area</th>
          <th>Lead</th>
          <th>Score</th>
          <th>Status</th>
          <th class="right">PDF</th>
          <th class="right">Edit</th>
          <?php if ($isAdmin): ?><th class="right">Delete</th><?php endif; ?>
        </tr>
        <?php if (!$rows): ?>
          <tr><td colspan="<?= $isAdmin ? 9 : 8 ?>" class="muted">No tours match your filters.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= h(date('Y-m-d H:i', strtotime($r['tour_date']))) ?></td>
            <td><?= h($r['site']) ?> / <?= h($r['area'] ?? '') ?></td>
            <td><?= h($r['lead_name']) ?></td>
            <td>
              <?php if ($r['score_percent'] === null || $r['score_percent'] === ''): ?>
                <span class="muted">—</span>
              <?php else: ?>
                <span class="pill"><?= number_format((float)$r['score_percent'],2) ?>%</span>
              <?php endif; ?>
            </td>
            <td><?= h($r['status']) ?></td>
            <td class="right"><a href="/pdf.php?id=<?= (int)$r['id'] ?>" target="_blank">PDF</a></td>
            <td class="right"><a href="/edit.php?id=<?= (int)$r['id'] ?>">Edit</a></td>
            <?php if ($isAdmin): ?>
              <td class="right">
                <a
                  href="/delete_tour.php?id=<?= (int)$r['id'] ?>&back=<?= urlencode('dashboard.php') ?>"
                  style="display:inline-block;padding:8px 10px;border-radius:10px;border:1px solid #7f1d1d;background:linear-gradient(180deg,#ef4444,#b91c1c);color:#fff"
                >Delete</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </table>
    </div>

    <div class="muted" style="margin:16px 4px">© Defect Tracker. Built for real sites, by real people. Developed And Maintained By Chris Irlam</div>
  </div>

  <!-- If your pwa_head.php didn't already register the SW, this is a safe backup -->
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js').catch(function(){});
    }
  </script>
</body>
</html>
