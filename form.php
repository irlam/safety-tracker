<?php
// File: form.php
// Description: New Site Safety Tour submission form. Includes checklist/questions, per-question file uploads, recipient selection, signature pad, and full UK date/time formatting. Designed for clarity and maintainability by non-coders.

$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
render_nav('new');

// Checklist definition (copied from PDF, see submit.php for processing)
$CHECKLIST = [
  // ... (checklist sections unchanged; same as before for brevity) ...
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Site Safety Tour</title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{--bg:#111827;--card:#0f172a;--border:#1f2937;--text:#f9fafb;--muted:#94a3b8;--input:#0b1220;--radius:14px}
  *{box-sizing:border-box}
  body{background:var(--bg);color:var(--text);font-family:system-ui, sans-serif;margin:0}
  .wrap{max-width:1024px;margin:0 auto;padding:18px}
  h1{font-size:1.9rem;margin:0 0 12px}
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
  button{background:#0ea5e9;color:#00131a;font-weight:700;border:0;border-radius:10px;padding:12px 14px;cursor:pointer}
  .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .chip{display:inline-flex;gap:6px;align-items:center;padding:6px 10px;border-radius:999px;background:var(--input);border:1px solid var(--border)}
  .chip button{background:transparent;border:0;color:var(--muted);cursor:pointer}
  .sig-wrap{background:#0b1220;border:1px dashed #334155;border-radius:12px;padding:10px}
  canvas#sig{display:block;width:100%;height:200px;background:#0000;border-radius:8px;touch-action:none}
  .sig-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:8px}
  .err{color:#fecaca;background:#3b0a0a33;border:1px solid #7f1d1d;padding:8px;border-radius:10px;margin-bottom:8px;display:none}
</style>
</head>
<body>
<div class="wrap">
  <h1>New Site Safety Tour</h1>

  <form action="submit.php" method="post" enctype="multipart/form-data" class="card" id="tourForm">
    <!-- Score (manual entry; server will recompute) -->
    <div class="row3" style="margin-bottom:10px">
      <div><label>Score — Achieved</label><input type="number" step="1" min="0" name="score_achieved" id="scoreA" placeholder="0"></div>
      <div><label>Score — Total</label><input type="number" step="1" min="0" name="score_total" id="scoreT" placeholder="0"></div>
      <div><label>Percent</label><input type="text" id="scoreP" name="score_percent" placeholder="Auto" readonly></div>
    </div>

    <!-- Header -->
    <div class="row2">
      <div><label>Project Name *</label><input type="text" name="site" required placeholder="e.g., Rochdale Road"></div>
      <div><label>Location *</label><input type="text" name="area" required placeholder="e.g., Block 1"></div>
    </div>
    <div class="row2">
      <div><label>Site Manager *</label><input type="text" name="lead_name" required placeholder="e.g., Darrell Cullen"></div>
      <div><label>Inspection Date &amp; Time *</label><input type="datetime-local" name="tour_date" required value="<?=date('Y-m-d\\TH:i')?>"></div>
    </div>
    <label>Prepared by / Participants</label>
    <input type="text" name="participants" placeholder="e.g., Anthony Tetlow; others">

    <!-- Checklist questions (per-section) -->
    <?php $qIndex=0; foreach ($CHECKLIST as $section => $items): ?>
      <div class="card">
        <h2><?= htmlspecialchars($section) ?></h2>
        <?php foreach ($items as $i): ?>
          <div class="card" style="border-color:#253044">
            <div class="full" style="font-weight:600">
              <span style="opacity:.8;margin-right:8px"><?= htmlspecialchars($i['code']) ?></span>
              <?= htmlspecialchars($i['q']) ?>
            </div>
            <!-- Hidden fields for submit.php -->
            <input type="hidden" name="q_code[]" value="<?= htmlspecialchars($i['code']) ?>">
            <input type="hidden" name="q_text[]" value="<?= htmlspecialchars($i['q']) ?>">
            <input type="hidden" name="f_category[]" value="<?= htmlspecialchars($section) ?>">

            <div class="row3" style="margin-top:10px">
              <div>
                <label>Result</label>
                <select name="check_status[]">
                  <option value="" disabled selected>Select</option>
                  <option>Pass</option>
                  <option>Improvement</option>
                  <option>Fail</option>
                  <option>N/A</option>
                </select>
              </div>
              <div>
                <label>Priority</label>
                <select name="f_severity[]">
                  <option value="" disabled selected>Priority</option>
                  <option value="Low">Low</option>
                  <option value="Medium">Medium</option>
                  <option value="High">High</option>
                </select>
              </div>
              <div>
                <label>Due (if action)</label>
                <input type="date" name="a_due[]">
              </div>
            </div>

            <div class="row2">
              <div><label>Action / To do (if required)</label><input type="text" name="a_action[]" placeholder="Describe the action required"></div>
              <div><label>Responsible</label><input type="text" name="a_resp[]" placeholder="Person(s) responsible"></div>
            </div>

            <div class="full">
              <label>Notes / Evidence</label>
              <textarea name="f_note[]" placeholder="Observation, evidence, instruction…"></textarea>
              <div style="color:#9fb3c8;margin-top:6px">Attach photos for this question (optional)</div>
              <input type="file" name="qphotos[<?= $qIndex ?>][]" accept="image/*" multiple>
            </div>
          </div>
        <?php $qIndex++; endforeach; ?>
      </div>
    <?php endforeach; ?>

    <!-- Recipients -->
    <?php
      $known = [];
      try {
        $st = db()->query("SELECT email, COALESCE(label,'') AS label, use_count FROM recipient_emails ORDER BY use_count DESC, last_used DESC LIMIT 50");
        $known = $st->fetchAll();
      } catch (Throwable $e) { $known = []; }
    ?>
    <div class="card">
      <strong>Recipients</strong>
      <div class="muted">Type an email and press Enter. Tap a chip to remove. Tap a “Previously used” address to add.</div>
      <div id="recChips" class="chips"></div>
      <input type="email" id="recInput" placeholder="name@company.com">
      <input type="hidden" name="recipients" id="recipients">
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

    <!-- Extra photos (any section) -->
    <div class="row2">
      <div><label>Extra photos (optional, any section)</label><input type="file" name="photos[]" accept="image/*" multiple></div>
    </div>

    <!-- Signature (required) -->
    <div class="card">
      <strong>Signature (required)</strong>
      <div id="sigErr" class="err">Please sign in the box below (or upload a signature image).</div>
      <div class="sig-wrap">
        <canvas id="sig"></canvas>
      </div>
      <div class="sig-actions">
        <input type="file" name="signature_file" accept="image/*">
        <button type="button" id="sigClear">Clear</button>
      </div>
      <input type="hidden" name="signature_data" id="signature_data">
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <button type="submit">Save &amp; Email PDF</button>
    </div>
  </form>
</div>

<script>
// Score percent auto-calc
(function(){
  const A = document.getElementById('scoreA'), T = document.getElementById('scoreT'), P = document.getElementById('scoreP');
  function upd(){ const a=+A.value||0, t=+T.value||0; P.value = t>0 ? (a/t*100).toFixed(2)+'%' : ''; }
  A.addEventListener('input',upd); T.addEventListener('input',upd);
})();

// Recipients chips/selection
(function(){
  const set = new Set();
  const chips = document.getElementById('recChips');
  const input = document.getElementById('recInput');
  const hidden = document.getElementById('recipients');

  function paint(){
    chips.innerHTML = '';
    [...set].forEach(email => {
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.textContent = email + ' ';
      const x = document.createElement('button'); x.type='button'; x.textContent='×';
      x.onclick = ()=>{ set.delete(email); paint(); };
      chip.appendChild(x);
      chips.appendChild(chip);
    });
    hidden.value = [...set].join(',');
  }
  function add(raw){
    const email = (raw||'').trim();
    if (!email) return;
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { input.value=''; return; }
    set.add(email.toLowerCase()); input.value=''; paint();
  }
  input.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); add(input.value); } });
  document.querySelectorAll('.rec-suggest').forEach(btn => btn.addEventListener('click', ()=> add(btn.dataset.email||'')));
})();

// Signature pad (vanilla, touch & mouse)
(function(){
  const cvs = document.getElementById('sig');
  const dataField = document.getElementById('signature_data');
  const err = document.getElementById('sigErr');
  const clearBtn = document.getElementById('sigClear');
  let drawing = false, dirty = false, ctx;

  function size(){ const r=cvs.getBoundingClientRect(); cvs.width = Math.max(600, r.width*2); cvs.height = 300; ctx = cvs.getContext('2d'); ctx.lineWidth = 3; ctx.lineCap='round'; ctx.strokeStyle='#0284c7'; }
  function pos(e){ const rect=cvs.getBoundingClientRect(); const x=(e.touches?e.touches[0].clientX:e.clientX)-rect.left; const y=(e.touches?e.touches[0].clientY:e.clientY)-rect.top; const sx=cvs.width/rect.width; const sy=cvs.height/rect.height; return {x:x*sx, y:y*sy}; }
  function start(e){ drawing=true; dirty=true; const p=pos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); e.preventDefault(); }
  function move(e){ if(!drawing) return; const p=pos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); e.preventDefault(); }
  function end(){ drawing=false; if (dirty) { dataField.value = cvs.toDataURL('image/png'); } }

  size(); window.addEventListener('resize', size);
  cvs.addEventListener('mousedown', start); cvs.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
  cvs.addEventListener('touchstart', start, {passive:false}); cvs.addEventListener('touchmove', move, {passive:false}); cvs.addEventListener('touchend', end);

  clearBtn.addEventListener('click', ()=>{ size(); err.style.display='none'; });

  // Require signature (dataURL) OR uploaded file
  document.getElementById('tourForm').addEventListener('submit', function(e){
    const hasCanvas = (dataField.value && dataField.value.startsWith('data:image/'));
    const file = document.querySelector('input[name="signature_file"]').files[0];
    if (!hasCanvas && !file) { err.style.display='block'; e.preventDefault(); }
  });
})();
</script>
</body>
</html>
