<?php
/**
 * File: audit.php
 * 
 * Safety Audit Log Viewer
 * 
 * This file provides a comprehensive audit trail for the Safety Tracker system.
 * It displays:
 * - All system events (tour creation, updates, deletions, etc.)
 * - User actions and authentication events
 * - PDF generation and email sending activities
 * - Search and filtering capabilities by date range, event type, and keywords
 * - All timestamps displayed in UK format (dd/mm/yyyy HH:MM)
 * 
 * The page includes filtering options and displays up to 1000 records at once.
 * This is typically an admin-only page for system monitoring and troubleshooting.
 */

declare(strict_types=1);

// Set UK timezone for consistent date/time display
date_default_timezone_set('Europe/London');

// Optional authentication - typically admin-only access
$authPath = __DIR__ . '/includes/auth.php';
if (is_file($authPath)) {
    require_once $authPath;
    if (function_exists('auth_check')) {
        // Set to true for admin-only, false for public access
        auth_check(true); 
    }
}

// Load core functions for database access and HTML escaping
require_once __DIR__ . '/includes/functions.php';

// Load and render navigation if available
$navigationPath = __DIR__ . '/includes/nav.php';
ob_start();
if (is_file($navigationPath)) {
    require_once $navigationPath;
    if (function_exists('render_nav')) {
        render_nav('audit');
    }
}
$navigationHtml = ob_get_clean();

// Establish database connection
$database = db();

/**
 * =============================================================================
 * AUDIT LOG FILTERING
 * =============================================================================
 * Extract and process filter parameters from GET request
 */

// Filter parameters from query string
$dateFrom = trim($_GET['from'] ?? '');    // Start date filter
$dateTo = trim($_GET['to'] ?? '');        // End date filter  
$eventType = trim($_GET['event'] ?? '');  // Event type (create, update, delete, etc.)
$searchQuery = trim($_GET['q'] ?? '');    // General search in details/actor/IP
$recordLimit = max(50, min(1000, (int)($_GET['limit'] ?? 500))); // Records per page

// Build WHERE clause conditions and parameters
$whereConditions = [];
$queryParameters = [];

// Date range filtering
if ($dateFrom !== '') { 
    $whereConditions[] = 'event_time >= ?'; 
    $queryParameters[] = date('Y-m-d 00:00:00', strtotime($dateFrom)); 
}
if ($dateTo !== '') { 
    $whereConditions[] = 'event_time <= ?'; 
    $queryParameters[] = date('Y-m-d 23:59:59', strtotime($dateTo)); 
}

// Event type filtering
if ($eventType !== '') { 
    $whereConditions[] = 'event = ?'; 
    $queryParameters[] = $eventType; 
}

// General search in multiple fields
if ($searchQuery !== '') {
    $whereConditions[] = '(details LIKE ? OR actor LIKE ? OR ip LIKE ?)';
    $queryParameters[] = "%$searchQuery%";
    $queryParameters[] = "%$searchQuery%"; 
    $queryParameters[] = "%$searchQuery%";
}

// Construct final WHERE clause
$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

/**
 * =============================================================================
 * DATABASE QUERY EXECUTION
 * =============================================================================
 * Execute audit log query with filtering and error handling
 */

$auditQuery = "
    SELECT id, event_time, event, tour_id, action_id, actor,
           `action`, entity_type, entity_id, details, ip, created_at
    FROM safety_audit_log
    $whereClause
    ORDER BY id DESC
    LIMIT $recordLimit
";

$auditRecords = [];
$queryError = '';

try {
    $statement = $database->prepare($auditQuery);
    $statement->execute($queryParameters);
    $auditRecords = $statement->fetchAll();
} catch (Throwable $e) {
    $queryError = $e->getMessage();
}

/* ------------ HTML ------------- */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Audit Log</title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{--bg:#0b1220;--card:#0f172a;--muted:#94a3b8;--text:#e5e7eb;--border:#1f2937;--radius:18px}
  *{box-sizing:border-box} html,body{margin:0;padding:0}
  body{background:radial-gradient(1200px 800px at 75% -100px, rgba(14,165,233,.12), transparent 60%),var(--bg);color:var(--text);font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1200px;margin:0 auto;padding:18px}
  h1{font-size:1.4rem;margin:8px 0 14px}
  .card{background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));border:1px solid var(--border);border-radius:var(--radius);padding:14px}
  .grid{display:grid;grid-template-columns:repeat(4,1fr) 120px 120px;gap:12px}
  @media (max-width:1000px){.grid{grid-template-columns:1fr 1fr}}
  label{display:block;margin:2px 0 6px;color:#cbd5e1}
  input,select{width:100%;background:#0b1220;color:var(--text);border:1px solid var(--border);border-radius:12px;padding:10px}
  .btn{background:linear-gradient(180deg,#0ea5e9,#0284c7);border:0;border-radius:14px;color:#00131a;font-weight:800;padding:12px 16px;cursor:pointer}
  .btn-ghost{background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.03));color:#d1d5db;border:1px solid var(--border);border-radius:14px;padding:12px 16px;text-decoration:none;display:inline-block;text-align:center}
  table{width:100%;border-collapse:collapse;margin-top:14px}
  th,td{border:1px solid var(--border);padding:10px;vertical-align:top}
  th{background:#0f172a;text-align:left}
  .muted{color:var(--muted)}
  .details{max-width:520px;white-space:pre-wrap;word-wrap:break-word}
  .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#0f172a}
  .row{display:flex;gap:12px;align-items:center;justify-content:space-between;margin:8px 0}
  .toast{background:#052d17;border:1px solid #14532d;color:#bbf7d0;padding:10px 12px;border-radius:12px;display:inline-block}
</style>
</head>
<body>
<div class="wrap">
  <?= $navHtml ?>

  <h1>Audit Log</h1>

  <form class="card" method="get" action="audit.php">
    <div class="grid">
      <div>
        <label>From</label>
        <input type="date" name="from" value="<?= h($from) ?>">
      </div>
      <div>
        <label>To</label>
        <input type="date" name="to" value="<?= h($to) ?>">
      </div>
      <div>
        <label>Event</label>
        <select name="event">
          <?php
            $opts = [''=>'Any','create'=>'create','update'=>'update','close_action'=>'close_action','reopen_action'=>'reopen_action','delete_tour'=>'delete_tour','email_sent'=>'email_sent','pdf_built'=>'pdf_built'];
            foreach ($opts as $val=>$lab) {
              $sel = ($event===$val)?'selected':'';
              echo '<option value="'.h($val).'" '.$sel.'>'.h($lab).'</option>';
            }
          ?>
        </select>
      </div>
      <div>
        <label>Search</label>
        <input type="text" name="q" placeholder="details / actor / IP" value="<?= h($q) ?>">
      </div>
      <div>
        <label>Limit</label>
        <input type="number" name="limit" min="50" max="1000" value="<?= (int)$limit ?>">
      </div>
      <div style="display:flex;align-items:end;gap:10px">
        <button class="btn" type="submit">Apply</button>
        <a class="btn-ghost" href="audit.php">Reset</a>
      </div>
    </div>
  </form>

  <?php if ($err): ?>
    <div class="card" style="margin-top:12px"><span class="pill">SQL error</span> <code class="muted"><?= h($err) ?></code></div>
  <?php endif; ?>

  <div class="card" style="margin-top:12px">
    <table>
      <tr>
        <th>#</th>
        <th>Time (UK)</th>
        <th>Event</th>
        <th>Tour</th>
        <th>Action</th>
        <th>Actor</th>
        <th>Entity</th>
        <th>Details</th>
        <th>IP</th>
      </tr>
      <?php if (!$auditRecords): ?>
        <tr><td colspan="9" class="muted">No entries.</td></tr>
      <?php else: foreach ($auditRecords as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h(date('d/m/Y H:i', strtotime($r['event_time'] ?? $r['created_at'] ?? 'now'))) ?></td>
          <td><span class="pill"><?= h($r['event'] ?? '') ?></span></td>
          <td><?= $r['tour_id'] ? '<a href="edit.php?id='.(int)$r['tour_id'].'">#'.(int)$r['tour_id'].'</a>' : '<span class="muted">—</span>' ?></td>
          <td><?= $r['action_id'] ? '#'.(int)$r['action_id'] : '<span class="muted">—</span>' ?></td>
          <td><?= h($r['actor'] ?? '') ?></td>
          <td><?= h(trim(($r['entity_type'] ?? '').' '.($r['entity_id'] ?? ''))) ?></td>
          <td class="details"><?= nl2br(h($r['details'] ?? '')) ?></td>
          <td class="muted"><?= h($r['ip'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</div>
</body>
</html>
