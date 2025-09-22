<?php
// delete_tour.php — admin-only deletion of a Safety Tour and related data/files + audit log
declare(strict_types=1);

// Auth (require admin)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(true); }

require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

// helpers
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
$actor = 'admin';
if (function_exists('current_user_name')) { $actor = (string)current_user_name() ?: 'admin'; }
elseif (function_exists('auth_is_admin') && auth_is_admin()) { $actor = 'admin'; }

// inputs
$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$back = (string)($_GET['back'] ?? $_POST['back'] ?? 'dashboard.php');
if ($id <= 0) { header('Location: '.$back); exit; }

// load tour
$st = db()->prepare('SELECT * FROM safety_tours WHERE id=?');
$st->execute([$id]);
$tour = $st->fetch();
if (!$tour) { header('Location: '.$back); exit; }

// confirm or delete
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  ?>
  <!doctype html>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Delete Tour #<?= (int)$id ?></title>
  <style>
    :root{--bg:#0b1220;--card:#111827;--text:#e5e7eb;--muted:#94a3b8;--border:#1f2937}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:16px/1.6 system-ui}
    .wrap{max-width:720px;margin:10vh auto;padding:18px}
    .card{background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));border:1px solid var(--border);border-radius:16px;padding:16px}
    .row{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
    .btn{padding:10px 14px;border-radius:12px;border:1px solid var(--border);text-decoration:none;color:#e5e7eb;background:#0f172a}
    .danger{background:linear-gradient(180deg,#ef4444,#b91c1c);border-color:#7f1d1d;color:#fff}
  </style>
  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 6px">Delete Safety Tour #<?= (int)$id ?></h2>
      <div class="muted" style="color:var(--muted);margin-bottom:12px">
        Project: <strong><?= h($tour['site'] ?? '') ?></strong>
        <?php if (!empty($tour['area'])): ?> — Area: <strong><?= h($tour['area']) ?></strong><?php endif; ?><br>
        Date: <?= h(date('d/m/Y H:i', strtotime($tour['tour_date']))) ?><br><br>
        This will remove the tour, its actions, and uploaded files (photos, signature, generated PDF). This cannot be undone.
      </div>

      <form method="post" class="row">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="back" value="<?= h($back) ?>">
        <a class="btn" href="<?= h($back) ?>">Cancel</a>
        <button class="btn danger" type="submit">Delete permanently</button>
      </form>
    </div>
  </div>
  <?php
  exit;
}

// POST -> perform deletion
$pdo = db();
$pdo->beginTransaction();
try {
  // pre-calc counts & files for logging + delete after commit
  $filesToDelete = [];
  $photos = json_decode((string)($tour['photos'] ?? '[]'), true) ?: [];
  foreach ($photos as $rel) {
    $abs = __DIR__ . '/' . ltrim((string)$rel, '/');
    if (is_file($abs)) $filesToDelete[] = $abs;
  }
  if (!empty($tour['signature_path'])) {
    $absSig = __DIR__ . '/' . ltrim((string)$tour['signature_path'], '/');
    if (is_file($absSig)) $filesToDelete[] = $absSig;
  }
  $pdfAbs = __DIR__ . '/uploads/tours/tour-' . (int)$id . '.pdf';
  if (is_file($pdfAbs)) $filesToDelete[] = $pdfAbs;

  // count actions to include in log
  $ac = (int)($pdo->query('SELECT COUNT(*) FROM safety_actions WHERE tour_id='.(int)$id)->fetchColumn());

  // delete related rows
  $pdo->prepare('DELETE FROM safety_actions WHERE tour_id=?')->execute([$id]);
  $pdo->prepare('DELETE FROM safety_tours WHERE id=?')->execute([$id]);

  // audit log (inside the transaction)
  $details = [
    'site'        => $tour['site'] ?? '',
    'area'        => $tour['area'] ?? '',
    'tour_date'   => $tour['tour_date'] ?? '',
    'actions_deleted' => $ac,
    'photos_deleted'  => count($photos),
    'signature_deleted'=> !empty($tour['signature_path']),
  ];
  $log = $pdo->prepare('INSERT INTO safety_audit_log (event, tour_id, action_id, actor, details) VALUES (?,?,?,?,?)');
  $log->execute(['delete_tour', $id, null, $actor, json_encode($details, JSON_UNESCAPED_UNICODE)]);

  $pdo->commit();

  // best-effort file deletion (after commit)
  foreach ($filesToDelete as $f) { @unlink($f); }

  // back with toast
  $dest = strpos($back, '?') !== false ? $back.'&deleted=1' : $back.'?deleted=1';
  header('Location: '.$dest);
  exit;

} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo '<!doctype html><meta charset="utf-8"><body style="background:#0b1220;color:#e5e7eb;font-family:system-ui">';
  echo '<div style="max-width:720px;margin:10vh auto;padding:16px;border:1px solid #1f2937;border-radius:16px;background:#111827">';
  echo '<h2>Delete failed</h2><p style="color:#fca5a5">'.h($e->getMessage()).'</p>';
  echo '<p><a style="color:#93c5fd" href="'.h($back).'">Back</a></p></div></body>';
  exit;
}
