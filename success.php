<?php
// success.php — post-submit checks + quick links
declare(strict_types=1);
@date_default_timezone_set('Europe/London');

$id    = (int)($_GET['id']   ?? 0);
$pdf   = (int)($_GET['pdf']  ?? 0);
$mail  = (int)($_GET['mail'] ?? 0);

$tourSite = $tourArea = $tourWhen = null;
try {
  $fn = __DIR__.'/includes/functions.php';
  if ($id > 0 && is_file($fn)) {
    require_once $fn;
    $st = db()->prepare('SELECT site,area,tour_date FROM safety_tours WHERE id=?');
    $st->execute([$id]);
    if ($row = $st->fetch()) {
      $tourSite = $row['site'] ?? null;
      $tourArea = $row['area'] ?? null;
      $tourWhen = !empty($row['tour_date']) ? date('d/m/Y H:i', strtotime($row['tour_date'])) : null;
    }
  }
} catch (Throwable $e) {
  // non-fatal; page still renders
}

// tiny helper (avoid clashes with includes/functions.php)
if (!function_exists('h_safe')) {
  function h_safe(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Saved — Tour #<?= $id ?: 0 ?></title>
<style>
  :root{--bg:#0b1220;--card:#111827;--border:#1f2937;--text:#e5e7eb;--muted:#94a3b8;--ok:#22c55e;--bad:#ef4444;--radius:16px}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:16px/1.55 system-ui,Segoe UI,Roboto}
  .wrap{max-width:920px;margin:0 auto;padding:20px}
  h1{margin:0 0 12px;font-size:1.4rem}
  .card{background:#0f172a;border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin:12px 0}
  .row{display:grid;gap:12px;grid-template-columns:1fr 1fr}
  @media (max-width:760px){.row{grid-template-columns:1fr}}
  .tag{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#0f172a;color:var(--muted);font-weight:700}
  .ok{color:#eaffec;border-color:#1d3e2b;background:#0e1f15}
  .bad{color:#fff0f0;border-color:#402020;background:#201011}
  .btn{display:inline-block;padding:10px 14px;border-radius:12px;background:#0ea5e9;color:#00131a;font-weight:700;text-decoration:none}
  .btn.ghost{background:#0b1220;color:#e5e7eb;border:1px solid var(--border)}
  .muted{color:var(--muted)}
  .grid2{display:grid;gap:10px;grid-template-columns:1fr 1fr}
  @media (max-width:760px){.grid2{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <h1>Safety Tour saved <?= $id ? '— #'.(int)$id : '' ?></h1>

  <?php if ($id && ($tourSite || $tourArea || $tourWhen)): ?>
    <div class="card">
      <div class="muted">Tour</div>
      <div>
        <?= $tourSite ? '<strong>'.h_safe($tourSite).'</strong>' : '' ?>
        <?= $tourArea ? ' · '.h_safe($tourArea) : '' ?>
        <?= $tourWhen ? ' · '.h_safe($tourWhen) : '' ?>
      </div>
    </div>
  <?php elseif (!$id): ?>
    <div class="card"><strong>Heads up:</strong> I didn’t receive a valid tour ID in the URL. The page will still show quick links below.</div>
  <?php endif; ?>

  <div class="row">
    <div class="card">
      <div class="muted">PDF generation</div>
      <div class="tag <?= $pdf ? 'ok' : 'bad' ?>"><?= $pdf ? '✓ Created' : '✕ Failed' ?></div>
      <?php if ($id): ?>
        <div style="margin-top:10px">
          <a class="btn" href="pdf.php?id=<?= (int)$id ?>" target="_blank">Open PDF</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="muted">Emails sent</div>
      <div class="tag <?= $mail ? 'ok' : 'bad' ?>"><?= $mail ? '✓ OK' : '✕ Not sent' ?></div>
      <?php if (!$mail): ?>
        <div class="muted" style="margin-top:8px">Check SMTP creds or recipient list, then resend from Edit.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="grid2">
      <div>
        <div class="muted">Next steps</div>
        <ul style="margin:8px 0 0 18px; padding:0 0 0 4px; line-height:1.5">
          <?php if ($id): ?>
            <li>Review answers & attachments in <a href="edit.php?id=<?= (int)$id ?>">Edit</a>.</li>
            <li>Rebuild or download the PDF if needed.</li>
          <?php endif; ?>
          <li>Log any follow-up actions in the register.</li>
        </ul>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;justify-content:flex-end">
        <?php if ($id): ?>
          <a class="btn" href="edit.php?id=<?= (int)$id ?>">Edit this tour</a>
        <?php endif; ?>
        <a class="btn ghost" href="dashboard.php">Dashboard</a>
        <a class="btn ghost" href="actions.php">Actions</a>
        <a class="btn" href="form.php">Create another</a>
      </div>
    </div>
  </div>

  <p class="muted">Time: <?= h_safe(date('d/m/Y H:i')) ?> (Europe/London)</p>
</div>
</body>
</html>
