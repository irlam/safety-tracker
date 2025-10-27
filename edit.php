<?php
// edit.php — Edit an existing Site Safety Tour
declare(strict_types=1);

// Auth (optional-safe)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

// Core helpers
require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

// Simple escape
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
}

// Load nav
require_once __DIR__ . '/includes/nav.php';

// ------- Load tour -------
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'Missing id'; exit; }

$pdo = db();
$st  = $pdo->prepare('SELECT * FROM safety_tours WHERE id = ?');
$st->execute([$id]);
$tour = $st->fetch();
if (!$tour) { http_response_code(404); echo 'Tour not found'; exit; }

// Decode responses / photos stored
$responses = json_decode((string)($tour['responses'] ?? '[]'), true);
if (!is_array($responses)) $responses = [];
$photos = json_decode((string)($tour['photos'] ?? '[]'), true);
if (!is_array($photos)) $photos = [];

// ------- Handle POST (save edits) -------
$savedOk = false;
$errMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    // Basic fields
    $site        = trim((string)($_POST['site'] ?? $tour['site'] ?? ''));
    $area        = trim((string)($_POST['area'] ?? $tour['area'] ?? ''));
    $lead_name   = trim((string)($_POST['lead_name'] ?? $tour['lead_name'] ?? ''));
    $participants= trim((string)($_POST['participants'] ?? $tour['participants'] ?? ''));
    $status      = in_array(($_POST['status'] ?? ''), ['Open','Closed'], true) ? $_POST['status'] : ($tour['status'] ?? 'Open');

    // UK date/time to SQL
    $tour_date_in = (string)($_POST['tour_date'] ?? '');
    $tour_date    = $tour_date_in ? date('Y-m-d H:i:s', strtotime($tour_date_in)) : ($tour['tour_date'] ?? date('Y-m-d H:i:s'));

    // Build edited responses from arrays (same order as in the form)
    $check  = $_POST['check_status'] ?? [];
    $prio   = $_POST['f_severity']   ?? [];
    $due    = $_POST['a_due']        ?? [];
    $action = $_POST['a_action']     ?? [];
    $respBy = $_POST['a_resp']       ?? [];
    $note   = $_POST['f_note']       ?? [];
    $cat    = $_POST['f_category']   ?? [];
    $code   = $_POST['f_code']       ?? []; // we’ll include codes as hidden inputs

    // Start fresh rows using what came in
    $newResponses = [];
    $rows = max(count($check), count($prio), count($note), count($cat), count($code));
    for ($i=0; $i<$rows; $i++) {
      $newResponses[$i] = [
        'code'     => trim((string)($code[$i] ?? '')),
        'question' => trim((string)($_POST['f_question'][$i] ?? '')), // hidden field
        'category' => trim((string)($cat[$i] ?? '')),
        'result'   => trim((string)($check[$i] ?? '')),
        'priority' => trim((string)($prio[$i]  ?? '')),
        'due'      => trim((string)($due[$i]   ?? '')),
        'action'   => trim((string)($action[$i]?? '')),
        'responsible'=>trim((string)($respBy[$i]?? '')),
        'note'     => trim((string)($note[$i]  ?? '')),
        'images'   => [], // merge below
      ];
      // If pre-existing responses had images, keep them; we'll apply removals next
      if (!empty($responses[$i]['images']) && is_array($responses[$i]['images'])) {
        $newResponses[$i]['images'] = $responses[$i]['images'];
      }
    }

    // Apply removals for existing images (checkboxes: remove_images[i][])
    if (!empty($_POST['remove_images']) && is_array($_POST['remove_images'])) {
      foreach ($_POST['remove_images'] as $i => $imgList) {
        if (!isset($newResponses[$i]['images'])) continue;
        $toRemove = array_map('strval', (array)$imgList);
        $newResponses[$i]['images'] = array_values(array_filter(
          $newResponses[$i]['images'],
          fn($p) => !in_array((string)$p, $toRemove, true)
        ));
      }
    }

    // Save any newly added per-question files (qphotos[index][])
    if (!empty($_FILES['qphotos']) && !empty($_FILES['qphotos']['name'])) {
      $added = save_question_files($_FILES['qphotos'], $id);
      foreach ($added as $idx => $paths) {
        if (!isset($newResponses[$idx])) continue;
        foreach ($paths as $p) $newResponses[$idx]['images'][] = $p;
      }
    }

    // Extra tour-level photos (append)
    if (!empty($_FILES['photos']['name'][0])) {
      foreach ($_FILES['photos']['name'] as $i => $nm) {
        $tmp = [
          'name'     => $_FILES['photos']['name'][$i],
          'type'     => $_FILES['photos']['type'][$i],
          'tmp_name' => $_FILES['photos']['tmp_name'][$i],
          'error'    => $_FILES['photos']['error'][$i],
          'size'     => $_FILES['photos']['size'][$i],
        ];
        $rel = save_file($tmp, 'tours/'.$id);
        if ($rel) $photos[] = $rel;
      }
    }

    // Signature: keep unless remove or new one provided
    $signature_path = $tour['signature_path'] ?? null;

    $removeSig = !empty($_POST['remove_signature']);
    if ($removeSig) $signature_path = null;

    // Canvas dataURL overrides file if present
    $sigData = (string)($_POST['signature_data'] ?? '');
    if ($sigData && strpos($sigData, 'data:image/') === 0) {
      // Decode data URL
      if (preg_match('#^data:image/(\w+);base64,(.+)$#', $sigData, $m)) {
        $ext = strtolower($m[1]); if (!in_array($ext, ['png','jpg','jpeg','webp'], true)) $ext='png';
        $bin = base64_decode($m[2], true);
        if ($bin !== false) {
          $dir = __DIR__ . '/uploads/signatures';
          if (!is_dir($dir)) mkdir($dir, 0775, true);
          $name = 'sig_'.$id.'_'.bin2hex(random_bytes(4)).'.'.$ext;
          $abs  = $dir.'/'.$name;
          file_put_contents($abs, $bin);
          $signature_path = 'uploads/signatures/'.$name;
        }
      }
    } elseif (!empty($_FILES['signature_file']['tmp_name'])) {
      $rel = save_file($_FILES['signature_file'], 'signatures');
      if ($rel) $signature_path = $rel;
    }

    // Recompute scores from edited responses
    $tally = tally_score($newResponses);
    $score_percent  = $tally['percent'];
    $score_total    = $tally['counts']['pass'] + $tally['counts']['fail'] + $tally['counts']['improvement'];
    $score_achieved = $tally['counts']['pass'];

    // Recipients handling (chips)
    $recipientsCsv = trim((string)($_POST['recipients'] ?? ''));
    $recipientsArr = array_values(array_filter(array_map('trim', explode(',', $recipientsCsv))));
    // Persist to separate table for reuse
    if ($recipientsArr) {
      try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO safety_recipient_emails (email, label, use_count, last_used)
                              VALUES (?, NULL, 1, NOW())
                              ON DUPLICATE KEY UPDATE use_count=use_count+1, last_used=NOW()");
        foreach ($recipientsArr as $em) {
          if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $em)) {
            $ins->execute([strtolower($em)]);
          }
        }
        $pdo->commit();
      } catch (Throwable $e) { $pdo->rollBack(); /* not fatal */ }
    }

    // Update row
    $upd = $pdo->prepare("
      UPDATE safety_tours
         SET tour_date      = ?,
             site           = ?,
             area           = ?,
             lead_name      = ?,
             participants   = ?,
             status         = ?,
             responses      = ?,
             photos         = ?,
             signature_path = ?,
             recipients     = ?,       -- if this column exists; ignore error below
             score_achieved = ?,
             score_total    = ?,
             score_percent  = ?
       WHERE id = ?
    ");

    // Try update; if recipients col doesn’t exist, retry without it
    try {
      $upd->execute([
        $tour_date, $site, $area, $lead_name, $participants, $status,
        json_encode($newResponses, JSON_UNESCAPED_UNICODE),
        json_encode($photos, JSON_UNESCAPED_UNICODE),
        $signature_path,
        implode(',', $recipientsArr),
        $score_achieved, $score_total, $score_percent,
        $id
      ]);
    } catch (Throwable $e) {
      // Retry without recipients
      $upd2 = $pdo->prepare("
        UPDATE safety_tours
           SET tour_date      = ?,
               site           = ?,
               area           = ?,
               lead_name      = ?,
               participants   = ?,
               status         = ?,
               responses      = ?,
               photos         = ?,
               signature_path = ?,
               score_achieved = ?,
               score_total    = ?,
               score_percent  = ?
         WHERE id = ?
      ");
      $upd2->execute([
        $tour_date, $site, $area, $lead_name, $participants, $status,
        json_encode($newResponses, JSON_UNESCAPED_UNICODE),
        json_encode($photos, JSON_UNESCAPED_UNICODE),
        $signature_path,
        $score_achieved, $score_total, $score_percent,
        $id
      ]);
    }

    // Re-render PDF with UK filename (YYYY-MM-DD HH:mm → DD-MM-YYYY HH-mm)
    $stR = $pdo->prepare('SELECT * FROM safety_tours WHERE id=?');
    $stR->execute([$id]);
    $tour = $stR->fetch(); // refresh for render
    $ukDateForName = $tour['tour_date'] ? date('d-m-Y_H-i', strtotime($tour['tour_date'])) : date('d-m-Y_H-i');
    $safeSite = preg_replace('/[^a-z0-9\-]+/i', '-', (string)$tour['site']);
    $pdfAbs = __DIR__.'/uploads/tours/SafetyTour_'.$safeSite.'_'.$ukDateForName.'.pdf';
    if (!is_dir(dirname($pdfAbs))) mkdir(dirname($pdfAbs), 0775, true);
    render_pdf($tour, $pdfAbs);

    $responses = json_decode((string)$tour['responses'], true) ?: [];
    $photos    = json_decode((string)($tour['photos'] ?? '[]'), true) ?: [];

    $savedOk = true;
  } catch (Throwable $e) {
    $errMsg = $e->getMessage();
  }
}

// For the form render we need the checklist again to print questions in order.
// We’ll reconstruct from whatever we stored originally (responses has code, question, category).
// If you still keep a static $CHECKLIST somewhere, you can require it; otherwise we iterate responses.
$hasStaticChecklist = false;

// Load some previously used recipients
$known = [];
try {
  $known = $pdo->query("SELECT email, COALESCE(label,'') AS label FROM safety_recipient_emails ORDER BY use_count DESC, last_used DESC LIMIT 50")->fetchAll();
} catch (Throwable $e) { $known = []; }

// Active tab in nav
render_nav('edit');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Tour #<?= (int)$id ?></title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{--bg:#111827;--card:#0f172a;--border:#1f2937;--text:#f9fafb;--muted:#94a3b8;--input:#0b1220;--radius:14px}
  *{box-sizing:border-box}
  body{background:var(--bg);color:var(--text);font-family:system-ui, sans-serif;margin:0}
  .wrap{max-width:1024px;margin:0 auto;padding:18px}
  h1{font-size:1.8rem;margin:0 0 12px}
  h2{font-size:1.15rem;margin:18px 0 8px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin:12px 0}
  label{display:block;margin:8px 0 6px;color:var(--muted);font-weight:600}
  input,select,textarea{width:100%;background:var(--input);color:#e5e7eb;border:1px solid var(--border);border-radius:10px;padding:10px}
  textarea{min-height:90px;resize:vertical}
  .row2{display:grid;gap:12px;grid-template-columns:1fr 1fr;align-items:start}
  .row3{display:grid;gap:12px;grid-template-columns:1.4fr 1fr 0.8fr;align-items:start}
  .full{grid-column:1 / -1}
  @media (max-width:820px){ .row2,.row3{grid-template-columns:1fr} }
  .muted{color:var(--muted)}
  .ok{color:#86efac}
  .err{color:#fecaca;background:#3b0a0a33;border:1px solid #7f1d1d;padding:8px;border-radius:10px;margin:8px 0}
  .thumbs{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
  .thumb{position:relative;border:1px solid var(--border);border-radius:8px;padding:4px;background:#0b1220}
  .thumb img{display:block;width:120px;height:90px;object-fit:cover;border-radius:6px}
  .thumb label{position:absolute;top:6px;left:6px;background:#0008;padding:4px 6px;border-radius:6px;color:#eee;font-size:.8rem}
  .sig-wrap{background:#0b1220;border:1px dashed #334155;border-radius:12px;padding:10px}
  canvas#sig{display:block;width:100%;height:160px;background:#00000000;border-radius:8px;touch-action:none}
  .sig-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:space-between;margin-top:8px}
  button{background:#0ea5e9;color:#00131a;font-weight:700;border:0;border-radius:10px;padding:12px 14px;cursor:pointer}
</style>
</head>
<body>
<div class="wrap">
  <h1>Edit Safety Tour <span class="muted">#<?= (int)$id ?></span></h1>

  <?php if ($savedOk): ?>
    <div class="card ok">Saved. PDF regenerated.</div>
  <?php elseif ($errMsg): ?>
    <div class="card err"><strong>Save failed:</strong> <?= h($errMsg) ?></div>
  <?php endif; ?>

  <form action="edit.php?id=<?= (int)$id ?>" method="post" enctype="multipart/form-data" class="card" id="tourForm">
    <!-- Header -->
    <div class="row2">
      <div><label>Project Name *</label><input type="text" name="site" required value="<?= h($tour['site'] ?? '') ?>"></div>
      <div><label>Location *</label><input type="text" name="area" required value="<?= h($tour['area'] ?? '') ?>"></div>
    </div>
    <div class="row2">
      <div><label>Site Manager *</label><input type="text" name="lead_name" required value="<?= h($tour['lead_name'] ?? '') ?>"></div>
      <div><label>Inspection Date &amp; Time *</label>
        <input type="datetime-local" name="tour_date" required value="<?= h(date('Y-m-d\TH:i', strtotime($tour['tour_date'] ?? date('Y-m-d H:i:s')))) ?>">
      </div>
    </div>
    <div class="row2">
      <div><label>Prepared by / Participants</label><input type="text" name="participants" value="<?= h($tour['participants'] ?? '') ?>"></div>
      <div>
        <label>Status</label>
        <select name="status">
          <?php foreach (['Open','Closed'] as $st): ?>
            <option value="<?= $st ?>" <?= (($tour['status'] ?? 'Open')===$st?'selected':'') ?>><?= $st ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Questions -->
    <div class="card">
      <h2>Questions</h2>
      <?php if (!$responses): ?>
        <div class="muted">No questions captured on this tour.</div>
      <?php else: foreach ($responses as $i => $r): ?>
        <div class="card" style="border-color:#253044">
          <div class="full" style="font-weight:600">
            <span style="opacity:.8;margin-right:8px"><?= h($r['code'] ?? '') ?></span>
            <?= h($r['question'] ?? '') ?>
          </div>

          <!-- Hidden helpers to keep code/question -->
          <input type="hidden" name="f_code[]"     value="<?= h($r['code'] ?? '') ?>">
          <input type="hidden" name="f_question[]" value="<?= h($r['question'] ?? '') ?>">

          <div class="row3" style="margin-top:10px">
            <div>
              <label>Result</label>
              <?php $res = strtolower((string)($r['result'] ?? '')); ?>
              <select name="check_status[]">
                <option value=""        <?= $res===''?'selected':'' ?>>Select</option>
                <option value="Pass"        <?= $res==='pass'?'selected':'' ?>>Pass</option>
                <option value="Improvement" <?= $res==='improvement'?'selected':'' ?>>Improvement</option>
                <option value="Fail"        <?= $res==='fail'?'selected':'' ?>>Fail</option>
                <option value="N/A"         <?= ($res==='n/a'||$res==='na')?'selected':'' ?>>N/A</option>
              </select>
            </div>
            <div>
              <label>Priority</label>
              <?php $pr = strtolower((string)($r['priority'] ?? '')); ?>
              <select name="f_severity[]">
                <option value=""       <?= $pr===''?'selected':'' ?>>Priority</option>
                <option value="Low"    <?= $pr==='low'?'selected':'' ?>>Low</option>
                <option value="Medium" <?= $pr==='medium'?'selected':'' ?>>Medium</option>
                <option value="High"   <?= $pr==='high'?'selected':'' ?>>High</option>
              </select>
            </div>
            <div>
              <label>Due (if action)</label>
              <input type="date" name="a_due[]" value="<?= h($r['due'] ?? '') ?>">
            </div>
          </div>

          <div class="row2">
            <div><label>Action / To do</label><input type="text" name="a_action[]" value="<?= h($r['action'] ?? '') ?>"></div>
            <div><label>Responsible</label><input type="text" name="a_resp[]" value="<?= h($r['responsible'] ?? '') ?>"></div>
          </div>

          <div class="full">
            <label>Notes / Evidence</label>
            <textarea name="f_note[]"><?= h($r['note'] ?? '') ?></textarea>
            <input type="hidden" name="f_category[]" value="<?= h($r['category'] ?? '') ?>">

            <?php $imgs = is_array($r['images'] ?? null) ? $r['images'] : []; ?>
            <?php if ($imgs): ?>
              <div class="thumbs">
                <?php foreach ($imgs as $p): ?>
                  <div class="thumb">
                    <img src="/<?= h($p) ?>" alt="">
                    <label><input type="checkbox" name="remove_images[<?= (int)$i ?>][]" value="<?= h($p) ?>"> remove</label>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div style="color:#9fb3c8;margin-top:6px">Add more photos (optional)</div>
            <input type="file" name="qphotos[<?= (int)$i ?>][]" accept="image/*" multiple>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- Recipients -->
    <?php
      $existingRecipients = '';
      if (!empty($tour['recipients'])) $existingRecipients = (string)$tour['recipients'];
    ?>
    <div class="card">
      <strong>Recipients</strong>
      <div class="muted">Type an email and press Enter. Tap chip to remove. Tap a “Previously used” to add.</div>

      <div id="recChips" style="display:flex;flex-wrap:wrap;gap:8px;margin:8px 0"></div>
      <input type="email" id="recInput" placeholder="name@company.com">
      <input type="hidden" name="recipients" id="recipients" value="<?= h($existingRecipients) ?>">

      <?php if ($known): ?>
        <div style="margin-top:10px">
          <div class="muted" style="margin-bottom:6px">Previously used</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($known as $k): ?>
              <button type="button" class="rec-suggest"
                      data-email="<?= h($k['email']) ?>"
                      style="border:1px solid var(--border);background:#0b1220;color:#e5e7eb;border-radius:999px;padding:6px 10px;cursor:pointer">
                <?= h($k['label'] ?: $k['email']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Extra photos -->
    <div class="card">
      <strong>Extra photos</strong>
      <?php if ($photos): ?>
        <div class="thumbs">
          <?php foreach ($photos as $p): ?>
            <div class="thumb"><img src="/<?= h($p) ?>" alt=""></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="muted">No extra photos yet.</div>
      <?php endif; ?>
      <div style="margin-top:6px">Upload more</div>
      <input type="file" name="photos[]" accept="image/*" multiple>
    </div>

    <!-- Signature -->
    <div class="card">
      <strong>Signature</strong>
      <?php if (!empty($tour['signature_path'])): ?>
        <div class="thumbs" style="margin-top:6px">
          <div class="thumb"><img src="/<?= h($tour['signature_path']) ?>" style="width:200px;height:auto" alt=""></div>
        </div>
        <label><input type="checkbox" name="remove_signature" value="1"> Remove existing signature</label>
      <?php endif; ?>

      <div class="sig-wrap" style="margin-top:8px">
        <canvas id="sig"></canvas>
      </div>
      <div class="sig-actions">
        <div class="muted">Or upload:</div>
        <input type="file" name="signature_file" accept="image/*">
        <button type="button" id="sigClear">Clear</button>
      </div>
      <input type="hidden" name="signature_data" id="signature_data">
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <a class="muted" href="pdf.php?id=<?= (int)$id ?>" target="_blank" style="text-decoration:none;border:1px solid var(--border);padding:10px 14px;border-radius:10px">Open PDF</a>
      <button type="submit">Save changes</button>
    </div>
  </form>
</div>

<script>
// Recipients chips (prefill from hidden value)
(function(){
  const chips = document.getElementById('recChips');
  const input = document.getElementById('recInput');
  const hidden= document.getElementById('recipients');
  const set   = new Set((hidden.value || '').split(',').map(s=>s.trim()).filter(Boolean));

  function paint(){
    chips.innerHTML='';
    [...set].forEach(email=>{
      const chip=document.createElement('span');
      chip.style.cssText='display:inline-flex;gap:6px;align-items:center;border:1px solid var(--border);border-radius:999px;padding:6px 10px;background:#0f172a';
      chip.textContent=email+' ';
      const x=document.createElement('button'); x.type='button'; x.textContent='×';
      x.style.cssText='margin-left:6px;background:transparent;border:0;color:#94a3b8;cursor:pointer';
      x.onclick=()=>{ set.delete(email); sync(); };
      chip.appendChild(x); chips.appendChild(chip);
    });
  }
  function sync(){ hidden.value=[...set].join(','); paint(); }
  function add(v){ v=(v||'').trim().toLowerCase(); if(!v) return;
    if(!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v)) { input.value=''; return; }
    set.add(v); input.value=''; sync();
  }
  input.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); add(input.value); } });
  document.querySelectorAll('.rec-suggest').forEach(b=> b.addEventListener('click', ()=> add(b.dataset.email||'')));
  sync();
})();

// Signature pad
(function(){
  const cvs = document.getElementById('sig');
  const dataField = document.getElementById('signature_data');
  const clearBtn = document.getElementById('sigClear');
  let drawing=false, dirty=false, ctx;

  function size(){ const r=cvs.getBoundingClientRect(); cvs.width=Math.max(600, r.width*2); cvs.height=260;
    ctx=cvs.getContext('2d'); ctx.lineWidth=3; ctx.lineCap='round'; ctx.strokeStyle='#e5e7eb';
    ctx.clearRect(0,0,cvs.width,cvs.height); dirty=false; dataField.value='';
  }
  function pos(e){ const rect=cvs.getBoundingClientRect(); const x=(e.touches?e.touches[0].clientX:e.clientX)-rect.left;
    const y=(e.touches?e.touches[0].clientY:e.clientY)-rect.top; const sx=cvs.width/rect.width; const sy=cvs.height/rect.height; return {x:x*sx,y:y*sy}; }
  function start(e){ drawing=true; dirty=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault(); }
  function move(e){ if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); e.preventDefault(); }
  function end(){ drawing=false; if (dirty) { dataField.value=cvs.toDataURL('image/png'); } }

  size(); window.addEventListener('resize', size);
  cvs.addEventListener('mousedown', start); cvs.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
  cvs.addEventListener('touchstart', start, {passive:false}); cvs.addEventListener('touchmove', move, {passive:false}); cvs.addEventListener('touchend', end);
  clearBtn.addEventListener('click', size);
})();
</script>
</body>
</html>
