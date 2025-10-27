<?php
// /action_view.php — Single Action view + close/reopen (with images)
// Requires: includes/functions.php, includes/nav.php, action_close.php

@date_default_timezone_set('Europe/London');

require_once __DIR__ . '/includes/functions.php';

// optional auth (safe if missing)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
  require_once $auth;
  if (function_exists('auth_check')) auth_check(); // make admin-only elsewhere if needed
}

// tiny esc helper if not declared
if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$pdo  = db();
$id   = (int)($_GET['id'] ?? 0);
$back = trim($_GET['back'] ?? 'actions.php');

// Load action + linked tour (tolerant to missing tour)
$action = $tour = null;
$loadErr = '';

try {
  $st = $pdo->prepare("
    SELECT a.*, t.site, t.area, t.tour_date, t.recipients, t.responses
    FROM safety_actions a
    LEFT JOIN safety_tours t ON t.id = a.tour_id
    WHERE a.id = ?
    LIMIT 1
  ");
  $st->execute([$id]);
  $action = $st->fetch();
} catch (Throwable $e) {
  $loadErr = $e->getMessage();
}

if (!$action && !$loadErr) {
  http_response_code(404);
  $loadErr = 'Action not found.';
}

// Attempt to derive “original evidence” images for this action from safety_tours.responses JSON
// Attempt to derive “original evidence” images ONLY for this action
$origImages = [];
if ($action && !empty($action['responses'])) {
  $resp = json_decode((string)$action['responses'], true);
  if (is_array($resp)) {
    $aText = trim((string)($action['action'] ?? ''));
    $aResp = trim((string)($action['responsible'] ?? ''));
    foreach ($resp as $r) {
      // We consider a response "the" action block if:
      // 1) action text matches exactly (strict, case-sensitive trimmed)
      // 2) OR responsible matches too (case-sensitive trimmed)
      $rAction = isset($r['action']) ? trim((string)$r['action']) : '';
      $rResp   = isset($r['responsible']) ? trim((string)$r['responsible']) : '';

      $matches = false;
      if ($aText !== '' && $rAction === $aText) {
        $matches = true;
      } elseif ($aText !== '' && $aResp !== '' && $rAction === $aText && $rResp === $aResp) {
        $matches = true;
      } elseif ($aText === '' && $aResp !== '' && $rResp === $aResp) {
        // very rare: no action text stored, fall back to responsible only
        $matches = true;
      }

      if ($matches && !empty($r['images']) && is_array($r['images'])) {
        foreach ($r['images'] as $p) {
          if (is_string($p) && $p !== '') $origImages[] = $p;
        }
        // Stop at the first matching block — don’t collect from the whole tour
        break;
      }
    }
  }
}
// NOTE: no global fallback — if we didn't find a matching block, we show no "Original evidence" images.

// Closure photos (JSON on safety_actions.close_photos)
$closePhotos = [];
if ($action && !empty($action['close_photos'])) {
  $arr = json_decode((string)$action['close_photos'], true);
  if (is_array($arr)) {
    foreach ($arr as $p) if (is_string($p) && $p!=='') $closePhotos[] = $p;
  }
}

// role
$isAdmin = false;
if (function_exists('auth_is_admin')) { $isAdmin = (bool)auth_is_admin(); }
elseif (function_exists('is_admin'))  { $isAdmin = (bool)is_admin(); }

// helpers for UK date display
function ukd(?string $y_m_d): string {
  if (!$y_m_d) return '—';
  $ts = strtotime($y_m_d);
  return $ts ? date('d/m/Y', $ts) : h($y_m_d);
}
function ukn(?string $dt): string {
  if (!$dt) return '—';
  $ts = strtotime($dt);
  return $ts ? date('d/m/Y H:i', $ts) : h($dt);
}

require_once __DIR__ . '/includes/nav.php'; render_nav('actions');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Action #<?= (int)$id ?></title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{--bg:#0b1220;--card:#0f172a;--muted:#94a3b8;--text:#e5e7eb;--border:#1f2937;--radius:18px;--ok:#16a34a;--warn:#b08900;--hi:#7f1d1d}
  *{box-sizing:border-box} body{margin:0;background:
    radial-gradient(1200px 800px at 75% -100px, rgba(14,165,233,.12), transparent 60%),var(--bg);
    color:var(--text);font:16px/1.6 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:980px;margin:0 auto;padding:18px}
  h1{font-size:1.5rem;margin:0 0 12px}
  .card{background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin:12px 0}
  .muted{color:var(--muted)}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:800px){.grid2{grid-template-columns:1fr}}
  .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#0f172a}
  .low{background:#064e3b;color:#d1fae5}
  .med{background:#3f2e00;color:#fde68a}
  .hi{ background:#3b0a0a;color:#fecaca}
  .imgs{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
  @media (max-width:720px){.imgs{grid-template-columns:repeat(2,1fr)}}
  .imgs img{display:block;width:100%;height:160px;object-fit:cover;border-radius:12px;border:1px solid var(--border);background:#0b1220}
  .btn{display:inline-block;padding:10px 14px;border-radius:12px;background:#0ea5e9;color:#00131a;font-weight:800;text-decoration:none;border:0}
  .btn.ghost{background:#0b1220;color:#e5e7eb;border:1px solid var(--border)}
  .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  @media (max-width:720px){.row{grid-template-columns:1fr}}
  input, textarea, select{width:100%;background:#0b1220;color:#e5e7eb;border:1px solid var(--border);border-radius:12px;padding:10px}
  label{display:block;margin:6px 0 6px;color:#cbd5e1;font-weight:600}
  .err{margin:10px 0;padding:10px;border:1px solid #7f1d1d;background:#3b0a0a22;border-radius:12px;color:#fecaca}
</style>
</head>
<body>
<div class="wrap">
  <?php if ($loadErr): ?>
    <div class="card err"><strong>Error:</strong> <?= h($loadErr) ?></div>
    <p><a class="btn ghost" href="<?= h($back) ?>">← Back</a></p>
  <?php elseif (!$action): ?>
    <div class="card err">Not found</div>
    <p><a class="btn ghost" href="<?= h($back) ?>">← Back</a></p>
  <?php else: ?>
    <?php
      $prio = strtolower((string)($action['priority'] ?? $action['severity'] ?? ''));
      $cls  = $prio==='high' ? 'hi' : ($prio==='medium' ? 'med' : ($prio==='low' ? 'low' : ''));
    ?>
    <h1>Action #<?= (int)$action['id'] ?> <?= $action['status']==='Closed' ? '· <span class="pill" style="background:#052e1a;color:#b7f7cf">Closed</span>' : '' ?></h1>

    <div class="card">
      <div class="grid2">
        <div>
          <div class="muted">Action</div>
          <div style="font-weight:700"><?= h($action['action']) ?></div>
        </div>
        <div>
          <div class="muted">Priority</div>
          <div><span class="pill <?= $cls ?>"><?= $action['priority'] ?? $action['severity'] ?? '—' ?></span></div>
        </div>
        <div>
          <div class="muted">Responsible</div>
          <div><?= h($action['responsible'] ?? '—') ?></div>
        </div>
        <div>
          <div class="muted">Due date</div>
          <div><?= ukd($action['due_date'] ?? null) ?></div>
        </div>
        <div>
          <div class="muted">Status</div>
          <div><?= h($action['status'] ?? 'Open') ?></div>
        </div>
        <div>
          <div class="muted">Tour</div>
          <?php if (!empty($action['tour_id'])): ?>
            <div>#<?= (int)$action['tour_id'] ?> · <?= h($action['site'] ?? '') ?><?= !empty($action['area']) ? ' / '.h($action['area']) : '' ?> · <?= ukn($action['tour_date'] ?? null) ?></div>
            <div style="margin-top:6px">
              <a class="btn ghost" href="edit.php?id=<?= (int)$action['tour_id'] ?>">Open tour</a>
              <a class="btn ghost" href="pdf.php?id=<?= (int)$action['tour_id'] ?>" target="_blank">PDF</a>
            </div>
          <?php else: ?>
            <div>—</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($origImages): ?>
      <div class="card">
        <strong>Original evidence (from tour)</strong>
        <div class="imgs" style="margin-top:10px">
          <?php foreach ($origImages as $p): ?>
            <a href="/<?= ltrim($p,'/') ?>" target="_blank" rel="noopener">
              <img src="/<?= ltrim($p,'/') ?>" alt="">
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($action['close_note']) || $closePhotos): ?>
      <div class="card">
        <strong>Closure details</strong>
        <div class="muted" style="margin-top:6px">
          <?= $action['closed_by'] ? 'Closed by '.h($action['closed_by']).' at '.ukn($action['closed_at']) : '' ?>
        </div>
        <?php if (!empty($action['close_note'])): ?>
          <div style="margin-top:10px;white-space:pre-wrap"><?= h($action['close_note']) ?></div>
        <?php endif; ?>
        <?php if ($closePhotos): ?>
          <div class="imgs" style="margin-top:10px">
            <?php foreach ($closePhotos as $p): ?>
              <a href="/<?= ltrim($p,'/') ?>" target="_blank" rel="noopener">
                <img src="/<?= ltrim($p,'/') ?>" alt="">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn ghost" href="<?= h($back) ?>">← Back</a>
        <?php if ($isAdmin): ?>
          <?php if (($action['status'] ?? 'Open') !== 'Closed'): ?>
            <a class="btn" href="#close">Close this action</a>
          <?php else: ?>
            <a class="btn" href="action_close.php?id=<?= (int)$action['id'] ?>&do=reopen&back=<?= rawurlencode($back) ?>">Reopen</a>
          <?php endif; ?>
        <?php else: ?>
          <span class="muted">Admin required to close/reopen</span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($isAdmin && ($action['status'] ?? 'Open') !== 'Closed'): ?>
      <!-- Close action panel -->
      <div id="close" class="card">
        <h2 style="margin:0 0 8px">Close this action</h2>
        <form action="action_close.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= (int)$action['id'] ?>">
          <input type="hidden" name="back" value="<?= h($back) ?>">
          <div class="row">
            <div>
              <label>Closure note (what was done)</label>
              <textarea name="close_note" rows="4" placeholder="Explain the corrective action completed…"></textarea>
            </div>
            <div>
              <label>Closure photos (optional, multiple)</label>
              <input type="file" name="close_photos[]" accept="image/*" multiple>
            </div>
          </div>
          <div style="margin-top:8px">
            <?php
              // show who would be emailed (tour recipients)
              $recList = [];
              if (!empty($action['recipients'])) {
                foreach (explode(',', (string)$action['recipients']) as $r) {
                  $r = trim($r);
                  if ($r !== '') $recList[] = $r;
                }
              }
            ?>
            <label style="display:flex;gap:8px;align-items:center">
              <input type="checkbox" name="email_recipients" value="1" checked>
              <span>Email tour recipients on close<?= $recList ? ': '.h(implode(', ', $recList)) : '' ?></span>
            </label>
          </div>
          <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:10px">
            <button class="btn" type="submit">Close action</button>
            <a class="btn ghost" href="<?= h($back) ?>">Cancel</a>
          </div>
        </form>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>
