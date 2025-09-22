<?php
// form.php — New Site Safety Tour (with canvas signature, per-question photos, hidden q_code/q_text)
// Optional auth
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
render_nav('new');

/* Checklist copied from your PDF (unchanged) */
$CHECKLIST = [
  '1.0 Site Set Up' => [
    ['code'=>'1.1','q'=>"Is the Site perimeter in place with Temporary works applied for Fixed or Hera's fencing, and are gates secured with lockable devices."],
    ['code'=>'1.2','q'=>"Has Safe access and egress been provided for pedestrians and segregation applied away from plant, equipment and materials."],
    ['code'=>'1.3','q'=>"Is statutory signage for awareness, e.g., speed limits, crossing points, and emergency locations in place."],
    ['code'=>'1.4','q'=>"Have designated storage areas away from pedestrians been provided and barriered off."],
  ],
  '2.0 Statutory Information / First Aid' => [
    ['code'=>'2.1','q'=>"Are the statutory notice boards being displayed for the McGoff Construction Ltd (H&S Policy & Arrangements, F10, CPP, First Aid, Fire information and Permits issued)."],
    ['code'=>'2.2','q'=>"Has the Construction Phase Plan been reviewed for changes onsite."],
    ['code'=>'2.3','q'=>"Have the site files been developed and contain the required information under the correct appendixes."],
    ['code'=>'2.4','q'=>"Has a Temporary works file been developed (Site fencing, scaffold, propping, formwork, falsework, structural supports etc) with training in place for the appointed person."],
    ['code'=>'2.5','q'=>"Have site inductions been completed, competency details verified and PPE checked prior to commencing work."],
    ['code'=>'2.6','q'=>"Are first aiders and fire marshals trained, certificates displayed and provisions suitable e.g., First aid equipment / defibrillator charged."],
    ['code'=>'2.7','q'=>"Is the accident book in place and investigation procedure briefed and understood in the event of an incident onsite."],
  ],
  '3.0 Site Areas' => [
    ['code'=>'3.1','q'=>"Is the lighting in place which is suitable & sufficient internally and externally and task light for specific operations being used."],
    ['code'=>'3.2','q'=>"Are access routes defined and clear of work materials where required e.g., general walkways and fire routes."],
    ['code'=>'3.3','q'=>"Are emergency escape routes clear with fire fighting equipment accessible and in service date."],
    ['code'=>'3.4','q'=>"Are temporary electrical units in place with cables elevated to eliminate potential slip, trip and falling hazards."],
    ['code'=>'3.5','q'=>"Is housekeeping being managed for waste materials, and provisions in place to remove waste streams keeping work areas clear to eliminate potential slip, trip and falling hazards."],
    ['code'=>'3.6','q'=>"Are dust levels being managed onsite with suitable equipment in place, e.g., medium rated extraction, suppression systems, RPE etc."],
    ['code'=>'3.7','q'=>"Are COSHH assessments in place and designated storage areas for chemicals provided, with ventilation suitable & sufficient being provided with the correct PPE being used."],
  ],
  '4.0 Work Equipment & Plant' => [
    ['code'=>'4.1','q'=>"Is Plant certification with reference to LOLER (rotating plant & equipment, passenger/goods hoist, MEWP etc) in place with competency levels retained for site compliance."],
    ['code'=>'4.2','q'=>"Has the hierarchy for WAH equipment been considered, and equipment being used built to manufacturers’ instructions."],
    ['code'=>'4.3','q'=>"Are scaffold designs in place, with statutory inspections completed weekly and after adverse weather conditions with corrective measures required applied in line with relevant standards."],
    ['code'=>'4.4','q'=>"Are scaffold lifts clear from materials and being cleared daily to eliminate potential falling objects."],
  ],
  '5.0 High-Risk Operations' => [
    ['code'=>'5.1','q'=>"Temporary Works"],
    ['code'=>'5.2','q'=>"Asbestos Management"],
    ['code'=>'5.3','q'=>"Lifting Operations"],
    ['code'=>'5.4','q'=>"Excavations"],
    ['code'=>'5.5','q'=>"Confined Spaces"],
  ],
  '6.0 Permit Management' => [
    ['code'=>'6.1','q'=>"Are site specific permits being issued and closed out in line with site procedures."],
  ],
  '7.0 Occupational Health / Biological' => [
    ['code'=>'7.1','q'=>"Occupational Health"],
    ['code'=>'7.2','q'=>"COVID-19 Management"],
    ['code'=>'7.3','q'=>"Biological Hazards & Considerations"],
  ],
  '8.0 Communication' => [
    ['code'=>'8.1','q'=>"Has there been a requirement to conduct a safety intervention at the time of the inspection."],
    ['code'=>'8.2','q'=>"Is there a record of any site meetings where H&S areas have been discussed with action plans set."],
    ['code'=>'8.3','q'=>"Have any toolbox talks been delivered in the calendar month."],
    ['code'=>'8.4','q'=>"Have any accidents or incidents occurred in the calendar month."],
  ],
  '9.0 Environmental Management' => [
    ['code'=>'9.1','q'=>"Has an aspects and impacts assessment been completed."],
    ['code'=>'9.2','q'=>"Has there been an ecology report completed."],
    ['code'=>'9.3','q'=>"Is there a facility to store fuel & hazardous substances onsite."],
    ['code'=>'9.4','q'=>"Have pollution and prevention controls onsite been provided."],
  ],
  '10.0 Waste Management' => [
    ['code'=>'10.1','q'=>"Have waste licences and permits been obtained."],
  ],
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

  /* Signature pad */
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
    <!-- Score (optional manual; server will recompute from responses anyway) -->
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

    <!-- Sections / questions -->
    <?php $qIndex=0; foreach ($CHECKLIST as $section => $items): ?>
      <div class="card">
        <h2><?= htmlspecialchars($section) ?></h2>
        <?php foreach ($items as $i): ?>
          <div class="card" style="border-color:#253044">
            <div class="full" style="font-weight:600">
              <span style="opacity:.8;margin-right:8px"><?= htmlspecialchars($i['code']) ?></span>
              <?= htmlspecialchars($i['q']) ?>
            </div>

            <!-- Hidden fields so submit.php can build responses[] -->
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
// score %
(function(){
  const A = document.getElementById('scoreA'), T = document.getElementById('scoreT'), P = document.getElementById('scoreP');
  function upd(){ const a=+A.value||0, t=+T.value||0; P.value = t>0 ? (a/t*100).toFixed(2)+'%' : ''; }
  A.addEventListener('input',upd); T.addEventListener('input',upd);
})();

// recipients chips (single implementation)
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

// Signature pad (vanilla)
(function(){
  const cvs = document.getElementById('sig');
  const dataField = document.getElementById('signature_data');
  const err = document.getElementById('sigErr');
  const clearBtn = document.getElementById('sigClear');
  let drawing = false, dirty = false, ctx;

  function size(){ const r=cvs.getBoundingClientRect(); cvs.width = Math.max(600, r.width*2); cvs.height = 300; ctx = cvs.getContext('2d'); ctx.lineWidth = 3; ctx.lineCap='round'; ctx.strokeStyle='#e5e7eb'; ctx.clearRect(0,0,cvs.width,cvs.height); dirty=false; dataField.value=''; }
  function pos(e){ const rect=cvs.getBoundingClientRect(); const x=(e.touches?e.touches[0].clientX:e.clientX)-rect.left; const y=(e.touches?e.touches[0].clientY:e.clientY)-rect.top; const sx=cvs.width/rect.width; const sy=cvs.height/rect.height; return {x:x*sx,y:y*sy}; }
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
