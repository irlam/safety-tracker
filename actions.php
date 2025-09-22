<?php
// actions.php — Actions register (view, filter, close/reopen)
// ---------------------------------------------------------

// auth (optional-safe)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
  require_once $auth;
  if (function_exists('auth_check')) auth_check(); // use auth_check(true) on admin-only pages
}

require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

// safe escape helper (if not already available)
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

// DB
$pdo = db();

// are we admin? (only if auth defines it)
$isAdmin = function_exists('auth_is_admin') ? (bool)auth_is_admin() : false;

/* ----------------------------------------
 * detect column name: priority vs severity
 * --------------------------------------*/
$prioCol = 'priority';
try {
  $hasPriority = (bool)$pdo->query("SHOW COLUMNS FROM `safety_actions` LIKE 'priority'")->fetch();
  $hasSeverity = (bool)$pdo->query("SHOW COLUMNS FROM `safety_actions` LIKE 'severity'")->fetch();
  if (!$hasPriority && $hasSeverity) $prioCol = 'severity';
} catch (Throwable $e) {
  // ignore, default stays as 'priority'
}

/* -------------
 * filter inputs
 * ----------- */
$q      = trim((string)($_GET['q']      ?? ''));
$status =        (string)($_GET['status'] ?? 'Open');   // Open | Closed | Any
$due    =        (string)($_GET['due']    ?? 'Any');    // Any | Overdue | Today | 7days

$where = [];
$args  = [];

if ($status === 'Open' || $status === 'Closed') { $where[] = "`a`.`status` = ?"; $args[] = $status; }
if ($q !== '') {
  $where[] = "(`t`.`site` LIKE ? OR `t`.`area` LIKE ? OR `a`.`action` LIKE ? OR `a`.`responsible` LIKE ?)";
  array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($due === 'Overdue') {
  $where[] = "`a`.`status`='Open' AND `a`.`due_date` IS NOT NULL AND `a`.`due_date` < CURDATE()";
} elseif ($due === 'Today') {
  $where[] = "`a`.`due_date` = CURDATE()";
} elseif ($due === '7days') {
  $where[] = "`a`.`due_date` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
}
$whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

/* ------------
 * quick stats
 * ----------*/
$tot     = (int)$pdo->query("SELECT COUNT(*) FROM `safety_actions`")->fetchColumn();
$open    = (int)$pdo->query("SELECT COUNT(*) FROM `safety_actions` WHERE `status`='Open'")->fetchColumn();
$closed  = (int)$pdo->query("SELECT COUNT(*) FROM `safety_actions` WHERE `status`='Closed'")->fetchColumn();
$overdue = (int)$pdo->query("SELECT COUNT(*) FROM `safety_actions` WHERE `status`='Open' AND `due_date` IS NOT NULL AND `due_date` < CURDATE()")->fetchColumn();

/* -----------
 * main query
 * ---------*/
$sql = "
  SELECT
    `a`.`id`,
    `a`.`tour_id`,
    `a`.`action`,
    `a`.`responsible`,
    `a`.`due_date`,
    `a`.`status`,
    `a`.`$prioCol` AS `priority`,
    `t`.`site`,
    `t`.`area`,
    `t`.`tour_date`
  FROM `safety_actions` AS `a`
  LEFT JOIN `safety_tours`  AS `t` ON `t`.`id` = `a`.`tour_id`
  $whereSql
  ORDER BY `a`.`id` DESC
  LIMIT 500
";

$rows = [];
$lastSqlError = '';
try {
  $st = $pdo->prepare($sql);
  $st->execute($args);
  $rows = $st->fetchAll();
} catch (Throwable $e) {
  $lastSqlError = $e->getMessage();
}

// Keep a back link with filters when navigating into Close/Reopen
$back = 'actions.php';
if (!empty($_SERVER['QUERY_STRING'])) $back .= '?'.$_SERVER['QUERY_STRING'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Actions Register</title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{
    --bg:#0b1220;--card:#0f172a;--muted:#94a3b8;--text:#e5e7eb;--border:#1f2937;--radius:18px;
    --g1:#0ea5e9;--g2:#0284c7
  }
  *{box-sizing:border-box}
  body{margin:0;background:
      radial-gradient(1200px 800px at 75% -100px, rgba(14,165,233,.12), transparent 60%),
      var(--bg);
      color:var(--text);font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1200px;margin:0 auto;padding:18px}
  h1{font-size:2rem;margin:0 0 14px}

  /* cards */
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
  @media (max-width:980px){.grid{grid-template-columns:repeat(2,1fr)}}
  @media (max-width:560px){.grid{grid-template-columns:1fr}}
  .card{background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));border:1px solid var(--border);border-radius:var(--radius);padding:14px}
  .big{font-size:2.2rem;font-weight:800}

  /* filters */
  .filters{margin:14px 0}
  .fgrid{display:grid;grid-template-columns:1fr 220px 220px 140px 110px; gap:12px}
  @media (max-width:1000px){.fgrid{grid-template-columns:1fr 1fr}}
  label{display:block;margin:2px 0 6px;color:#cbd5e1}
  input,select{width:100%;background:#0b1220;color:var(--text);border:1px solid var(--border);border-radius:12px;padding:10px}

  .btn{background:linear-gradient(180deg,var(--g1),var(--g2));border:0;border-radius:14px;color:#00131a;font-weight:800;padding:12px 16px;cursor:pointer}
  .btn-ghost{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.03));color:#d1d5db;border:1px solid var(--border);border-radius:14px;padding:12px 16px;text-decoration:none;display:inline-block;text-align:center}

  /* table */
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--border);padding:10px;vertical-align:top}
  th{background:#0f172a;text-align:left}
  .muted{color:#94a3b8}
  .err{margin:10px 0;padding:10px;border:1px solid #7f1d1d;background:#3b0a0a1a;border-radius:12px;color:#fecaca}

  .badge{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border)}
  .low{background:#064e3b;color:#d1fae5}
  .med{background:#3f2e00;color:#fde68a}
  .hi{background:#3b0a0a;color:#fecaca}

  .actbtn{display:inline-block;padding:8px 12px;border-radius:12px;text-decoration:none;border:1px solid var(--border);background:#0b1220;color:#e5e7eb}
  .close{background:linear-gradient(180deg,#16a34a,#15803d);color:#00130b}
  .reopen{background:linear-gradient(180deg,#f59e0b,#b45309);color:#1b0a00}
</style>
</head>
<body>
<div class="wrap">
  <?php require_once __DIR__ . '/includes/nav.php'; render_nav('actions'); ?>

  <h1>Actions Register</h1>

  <!-- Stat cards -->
  <section class="grid">
    <div class="card"><div>Total Actions</div><div class="big"><?= (int)$tot ?></div></div>
    <div class="card"><div>Open</div><div class="big"><?= (int)$open ?></div></div>
    <div class="card"><div>Overdue</div><div class="big"><?= (int)$overdue ?></div></div>
    <div class="card"><div>Closed</div><div class="big"><?= (int)$closed ?></div></div>
  </section>

  <!-- Filters -->
  <form class="card filters" method="get" action="actions.php">
    <div class="fgrid">
      <div>
        <label>Search</label>
        <input type="text" name="q" placeholder="site / area / action / responsible" value="<?= h($q) ?>">
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php foreach(['Open'=>'Open','Closed'=>'Closed','Any'=>'Any'] as $k=>$v): ?>
            <option value="<?= h($k) ?>" <?= ($status===$k?'selected':'') ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Due</label>
        <select name="due">
          <?php foreach(['Any','Overdue','Today','7days'] as $v): ?>
            <option value="<?= h($v) ?>" <?= ($due===$v?'selected':'') ?>><?= h($v) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;align-items:end">
        <button class="btn" type="submit">Apply</button>
      </div>
      <div style="display:flex;align-items:end">
        <a class="btn-ghost" href="actions.php">Reset</a>
      </div>
    </div>
  </form>

  <?php if ($lastSqlError): ?>
    <div class="err">
      <strong>SQL error:</strong> <?= h($lastSqlError) ?><br>
      <code><?= h($sql) ?></code>
    </div>
  <?php endif; ?>

  <!-- Table -->
  <div class="card">
    <table>
      <tr>
        <th>#</th>
        <th>Tour</th>
        <th>Site / Area</th>
        <th>Action</th>
        <th>Responsible</th>
        <th>Due</th>
        <th>Status</th>
        <th>Priority</th>
        <th><?= $isAdmin ? 'Close' : 'Close (Admin)' ?></th>
      </tr>

      <?php if (!$rows): ?>
        <tr><td colspan="9" class="muted">No actions match your filters.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php
          $prio = strtolower((string)($r['priority'] ?? ''));
          $cls  = $prio==='high' ? 'hi' : ($prio==='medium' ? 'med' : ($prio==='low' ? 'low' : ''));
          $isClosed = ($r['status'] === 'Closed');
          $link = 'action_close.php?id='.(int)$r['id'].'&back='.rawurlencode($back);
          $siteArea = trim(($r['site'] ?? '').(($r['area'] ?? '') ? ' / '.$r['area'] : ''));
          $dueDisp = $r['due_date'] ? date('Y-m-d', strtotime($r['due_date'])) : '—';
        ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><a href="edit.php?id=<?= (int)$r['tour_id'] ?>">#<?= (int)$r['tour_id'] ?></a></td>
          <td><?= h($siteArea) ?></td>
          <td><?= h($r['action']) ?></td>
          <td><?= h($r['responsible'] ?? '') ?></td>
          <td><?= h($dueDisp) ?></td>
          <td><?= h($r['status']) ?></td>
          <td><span class="badge <?= $cls ?>"><?= $r['priority'] ? h($r['priority']) : '—' ?></span></td>
          <td>
            <?php if ($isAdmin): ?>
              <a class="actbtn <?= $isClosed ? 'reopen' : 'close' ?>" href="<?= h($link) ?>">
                <?= $isClosed ? 'Reopen' : 'Close' ?>
              </a>
            <?php else: ?>
              <span class="muted">Admin only</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</div>
</body>
</html>
