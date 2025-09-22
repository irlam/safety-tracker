<?php
// action_close.php — close/view a single action
// auth (optional-safe)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    if (function_exists('auth_check')) {
        auth_check();           // or: auth_check(true); // if this page is admin-only
    }
}
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
render_nav('actions');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: actions.php'); exit; }

// Load
$st = db()->prepare("SELECT a.*, t.site, t.area, t.lead_name, t.tour_date FROM safety_actions a JOIN safety_tours t ON t.id=a.tour_id WHERE a.id=?");
$st->execute([$id]); $row = $st->fetch();
if (!$row) { header('Location: actions.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $close_note = trim($_POST['close_note'] ?? '');
  $closed_by  = trim($_POST['closed_by'] ?? '');
  $photos     = json_decode($row['close_photos'] ?: '[]', true) ?: [];

  if (!empty($_FILES['photos']['name'][0])) {
    foreach ($_FILES['photos']['name'] as $i => $name) {
      $tmp = [
        'name' => $_FILES['photos']['name'][$i],
        'type' => $_FILES['photos']['type'][$i],
        'tmp_name' => $_FILES['photos']['tmp_name'][$i],
        'error' => $_FILES['photos']['error'][$i],
        'size' => $_FILES['photos']['size'][$i],
      ];
      $rel = save_file($tmp, 'action_closeouts'); if ($rel) $photos[] = $rel;
    }
  }

  $stmt = db()->prepare("UPDATE safety_actions SET status='Closed', close_note=?, close_photos=?, closed_by=?, closed_at=NOW() WHERE id=?");
  $stmt->execute([$close_note, json_encode($photos, JSON_UNESCAPED_UNICODE), $closed_by ?: $row['responsible'], $id]);

  // Rebuild tour PDF so close-outs appear
  $tour = db()->prepare('SELECT * FROM safety_tours WHERE id=?'); $tour->execute([(int)$row['tour_id']]); $tour = $tour->fetch();
  if ($tour) {
    $pdfPath = __DIR__ . '/uploads/tours/tour-'.$tour['id'].'.pdf';
    if (!is_dir(dirname($pdfPath))) mkdir(dirname($pdfPath), 0775, true);
    try { render_pdf($tour, $pdfPath); } catch (Throwable $e) { error_log('PDF: '.$e->getMessage()); }
  }

  // Optional: email the lead when closed (uncomment to auto):
  // send_mail_multi([SMTP_USER], 'Action closed — Tour #'.$row['tour_id'], '<p>Action closed: '.htmlspecialchars($row['action']).'</p>');

  header('Location: action_close.php?id='.$id.'&saved=1'); exit;
}

$closedPhotos = json_decode($row['close_photos'] ?: '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Close Action #<?= (int)$id ?></title>
<style>
  :root{--bg:#0b1220;--card:#111827;--muted:#94a3b8;--text:#e5e7eb;--border:#1f2937;--radius:16px}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 system-ui,Segoe UI,Roboto}
  .wrap{max-width:900px;margin:0 auto;padding:18px}
  h1{margin:0 0 10px;font-size:1.2rem}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin:10px 0}
  label{display:block;margin:6px 0 4px;color:var(--muted)}
  input,textarea{width:100%;padding:10px;border-radius:10px;border:1px solid var(--border);background:#0b1220;color:#e5e7eb}
  .grid{display:grid;gap:12px;grid-template-columns:1fr 1fr} @media (max-width:800px){.grid{grid-template-columns:1fr}}
  .pill{display:inline-block;padding:4px 8px;border:1px solid var(--border);border-radius:999px;background:#0f172a;color:#94a3b8}
  .photos{display:grid;grid-template-columns:repeat(4,1fr);gap:8px} @media (max-width:700px){.photos{grid-template-columns:repeat(2,1fr)}}
  img{width:100%;border-radius:10px;border:1px solid var(--border)}
  .right{display:flex;gap:8px;justify-content:flex-end}
</style>
</head>
<body>
<div class="wrap">
  <h1>Action #<?= (int)$row['id'] ?> — Tour #<?= (int)$row['tour_id'] ?> (<?= htmlspecialchars($row['site']) ?>)</h1>
  <?php if (isset($_GET['saved'])): ?><div class="pill">Saved</div><?php endif; ?>

  <div class="card">
    <div class="grid">
      <div><label>Action</label><input value="<?= htmlspecialchars($row['action']) ?>" readonly></div>
      <div><label>Responsible</label><input value="<?= htmlspecialchars($row['responsible'] ?? '') ?>" readonly></div>
    </div>
    <div class="grid">
      <div><label>Due</label><input value="<?= $row['due_date'] ? htmlspecialchars(date('Y-m-d', strtotime($row['due_date']))) : '—' ?>" readonly></div>
      <div><label>Status</label><input value="<?= htmlspecialchars($row['status']) ?>" readonly></div>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="card">
    <label>Close-out description</label>
    <textarea name="close_note" rows="4" placeholder="What was done to close this action?"><?= htmlspecialchars($row['close_note'] ?? '') ?></textarea>

    <div class="grid">
      <div>
        <label>Closed by</label>
        <input name="closed_by" value="<?= htmlspecialchars($row['closed_by'] ?? '') ?>" placeholder="Name">
      </div>
      <div>
        <label>Attach images</label>
        <input type="file" name="photos[]" accept="image/*" multiple>
      </div>
    </div>

    <?php if ($closedPhotos): ?>
      <div style="margin-top:8px">
        <div class="pill" style="margin-bottom:6px">Existing close-out photos</div>
        <div class="photos">
          <?php foreach ($closedPhotos as $p): ?>
            <a href="<?= htmlspecialchars($p) ?>" target="_blank"><img src="<?= htmlspecialchars($p) ?>"></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="right" style="margin-top:10px">
      <a class="pill" href="actions.php">← Back</a>
      <button type="submit">Save & Close</button>
    </div>
  </form>

  <div class="right" style="margin-top:10px">
    <a class="pill" href="pdf.php?id=<?= (int)$row['tour_id'] ?>" target="_blank">Open Tour PDF</a>
  </div>
</div>
</body>
</html>
