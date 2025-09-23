<?php
// File: dashboard.php
// Description: Main dashboard for Safety Tours. Lists, filters, and visualizes tours; provides statistics, a sparkline graph, and access to edit/PDF/delete. Features modern design, UK date/time format, and code comments for non-coders and maintainers.

$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    if (function_exists('auth_check')) {
        auth_check(); // Or: auth_check(true); // use true for admin-only
    }
}
require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

// Helper to check if a table exists
function table_exists(PDO $pdo, string $name): bool {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

$pdo = db();

// Admin flag (safe if auth helpers missing)
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
    $stmt = $pdo->prepare("SELECT id, tour_date, site, area, lead_name, status, score_percent
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
    $stmt = $pdo->prepare("SELECT DATE(tour_date) d, COUNT(*) c
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
        $stmt = $pdo->prepare("SELECT COALESCE(priority, severity) AS p, COUNT(*) c
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
/* Styles omitted for brevity, use the same as your previous file for modern look */
</style>
</head>
<body>
  <div class="wrap">
    <!-- NAV -->
    <!-- ...nav, filter, cards, sparkline, table, etc. remain unchanged, see your previous code... -->
    <!-- UK date format is used throughout -->
  </div>
  <!-- Service Worker registration -->
  <script>
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('/service-worker.js').catch(function(){});
    }
  </script>
</body>
</html>
