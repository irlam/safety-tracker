<?php
// File: check.php
// Description: Displays the result of a Safety Tour submission. Shows success/failure status for database save, PDF generation, and email, plus links to next actions. All times are shown in UK format. Code is modernised, formatted, and commented for clarity and future use by non-coders.

if (file_exists(__DIR__ . '/includes/auth.php')) {
    require_once __DIR__ . '/includes/auth.php';
    auth_check();
}
require_once __DIR__ . '/includes/functions.php';

// Get parameters from the URL or set defaults
$id      = (int)($_GET['id']    ?? 0);
$ok_db   = ((int)($_GET['db']   ?? 0)) === 1;
$ok_pdf  = ((int)($_GET['pdf']  ?? 0)) === 1;
$ok_mail = ((int)($_GET['mail'] ?? 0)) === 1;
$hadErr  = ((int)($_GET['error']?? 0)) === 1;

// Fetch tour details if an ID is given
tour = null;
if ($id > 0) {
    $st = db()->prepare('SELECT id, site, area, lead_name, tour_date FROM safety_tours WHERE id=?');
    $st->execute([$id]);
    $tour = $st->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Submission result — Safety Tour</title>
<style>
  :root {
    --bg: #0b1220;
    --card: #111827;
    --muted: #94a3b8;
    --text: #e5e7eb;
    --ok: #22c55e;
    --warn: #f59e0b;
    --err: #ef4444;
    --border: #1f2937;
    --radius: 18px;
  }
  * { box-sizing: border-box; }
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font: 16px/1.6 system-ui, Segoe UI, Roboto;
  }
  .wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 18px;
  }
  h1 { margin: 0 0 10px; }
  .card {
    background: #0f172a;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    margin: 10px 0;
  }
  .row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  @media(max-width:720px) {
    .row { grid-template-columns: 1fr; }
  }
  .pill {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #0f172a;
  }
  .ok { color: var(--ok); border-color: #1f5130; }
  .no { color: var(--err); border-color: #5b1f1f; }
  a.btn {
    display: inline-block;
    background: #0ea5e9;
    color: #00131a;
    text-decoration: none;
    font-weight: 700;
    padding: 10px 14px;
    border-radius: 12px;
  }
  .muted { color: var(--muted); }
</style>
</head>
<body>
<div class="wrap">
  <h1>Submission result</h1>

  <?php if ($tour): ?>
    <div class="card">
      <div class="muted">Tour</div>
      <div>
        <strong>#<?= (int)$tour['id'] ?></strong> —
        <?= htmlspecialchars($tour['site']) ?> /
        <?= htmlspecialchars($tour['area'] ?? '') ?> —
        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($tour['tour_date']))) ?>
        (Lead: <?= htmlspecialchars($tour['lead_name']) ?>)
      </div>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="card">
      <div class="muted">Database save</div>
      <div class="pill <?= $ok_db ? 'ok' : 'no' ?>">
        <?= $ok_db ? '✓ Saved' : '✕ Failed' ?>
      </div>
    </div>
    <div class="card">
      <div class="muted">PDF generation</div>
      <div class="pill <?= $ok_pdf ? 'ok' : 'no' ?>">
        <?= $ok_pdf ? '✓ Created' : '✕ Failed' ?>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="card">
      <div class="muted">Email</div>
      <div class="pill <?= $ok_mail ? 'ok' : 'no' ?>">
        <?= $ok_mail ? '✓ Sent (at least one recipient)' : '✕ Not sent' ?>
      </div>
    </div>
    <div class="card">
      <div class="muted">Next</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
        <?php if ($id): ?>
          <a class="btn" href="pdf.php?id=<?= (int)$id ?>" target="_blank">Open PDF</a>
        <?php endif; ?>
        <a class="btn" href="dashboard.php">Go to Dashboard</a>
        <a class="btn" href="form.php">Create another</a>
      </div>
    </div>
  </div>

  <?php if ($hadErr): ?>
    <div class="card">
      <div class="muted">
        There was an error during submission. Check your PHP error log (Plesk) for details.
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>