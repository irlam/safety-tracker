<?php
// edit.php — Edit an existing Safety Tour with full checklist + saved responses
// Auth (optional-safe)
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
date_default_timezone_set('Europe/London');

//function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }

$pdo = db();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: dashboard.php'); exit; }

// --- Load tour
$st = $pdo->prepare('SELECT * FROM safety_tours WHERE id=?');
$st->execute([$id]);
$tour = $st->fetch();
if (!$tour) { http_response_code(404); echo 'Tour not found'; exit; }

// ==================== Canonical checklist (mirror of your form.php) ====================
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

// Build a flat list to index questions by an incremental index used in inputs
$flat = []; $secOf = [];
foreach ($CHECKLIST as $section => $items) {
  foreach ($items as $item) {
    $flat[] = ['section'=>$section, 'code'=>$item['code'], 'question'=>$item['q']];
    $secOf[$item['code']] = $section;
  }
}

// Decode saved responses (may be null on older records)
$saved = json_arr($tour['responses'] ?? null);
// Map by code for quick access
$byCode = [];
foreach ($saved as $r) {
  $c = (string)($r['code'] ?? '');
  if ($c !== '') $byCode[$c] = $r;
}

// ==================== Handle POST (save) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $site         = trim($_POST['site'] ?? '');
  $area         = trim($_POST['area'] ?? '');
  $lead_name    = trim($_POST['lead_name'] ?? '');
  $participants = trim($_POST['participants'] ?? '');
  $tour_date    = $_POST['tour_date'] ?? '';
  $status       = ($_POST['status'] ?? 'Open') === 'Closed' ? 'Closed' : 'Open';

  // Arrays aligned with $flat index
  $res  = $_POST['check_status'] ?? [];
  $pri  = $_POST['f_severity']   ?? [];
  $due  = $_POST['a_due']        ?? [];
  $act  = $_POST['a_action']     ?? [];
  $resp = $_POST['a_resp']       ?? [];
  $note = $_POST['f_note']       ?? [];

  // Save per-question newly uploaded files
  $qPhotos = [];
  if (!empty($_FILES['qphotos'])) {
    // Our helper expects the matrix structure and tour id
    $qPhotos = save_question_files($_FILES['qphotos'], $id); // returns [index => [paths...]]
  }

  // Build responses array
  $responses = [];
  $newActions = []; // to insert into safety_actions
  foreach ($flat as $i => $q) {
    $code = $q['code']; $question = $q['question']; $section = $q['section'];

    // start from saved (to keep existing images)
    $row = $byCode[$code] ?? [
      'code' => $code, 'question' => $question, 'section' => $section,
      'result' => '', 'priority' => '', 'note' => '', 'due' => '', 'action' => '', 'responsible' => '', 'images' => []
    ];

    $row['result']      = trim((string)($res[$i]  ?? $row['result']));
    $row['priority']    = trim((string)($pri[$i]  ?? $row['priority']));
    $row['due']         = trim((string)($due[$i]  ?? $row['due']));
    $row['action']      = trim((string)($act[$i]  ?? $row['action']));
    $row['responsible'] = trim((string)($resp[$i] ?? $row['responsible']));
    $row['note']        = trim((string)($note[$i] ?? $row['note']));

    // Merge newly uploaded images for this index
    if (!empty($qPhotos[$i])) {
      $imgs = is_array($row['images']) ? $row['images'] : [];
      $row['images'] = array_values(array_unique(array_merge($imgs, $qPhotos[$i])));
    }

    $responses[] = $row;

    // If a NEW action is provided here, queue it to safety_actions
    if ($row['action'] !== '') {
      $newActions[] = [
        'tour_id'     => $id,
        'action'      => $row['action'],
        'responsible' => $row['responsible'] ?: null,
        'due_date'    => $row['due'] ?: null,
        // map priority to our column (priority/severity)
        'priority'    => $row['priority'] ?: null,
        'status'      => 'Open',
      ];
    }
  }

  // Score auto-calc
  $sc = tally_score($responses);
  $scoreA = (int)($sc['counts']['pass'] ?? 0);
  $scoreT = (int)(($sc['counts']['pass'] ?? 0) + ($sc['counts']['fail'] ?? 0) + ($sc['counts']['improvement'] ?? 0));
  $scoreP = $sc['percent'];

  // Update safety_tours
  $stmt = $pdo->prepare("
    UPDATE safety_tours
       SET tour_date=?,
           site=?, area=?,
           lead_name=?, participants=?,
           responses=?,
           score_achieved=?, score_total=?, score_percent=?,
           updated_at=NOW(),
           status=?
     WHERE id=?
  ");
  $stmt->execute([
    date('Y-m-d H:i:s', strtotime($tour_date ?: ($tour['tour_date'] ?? 'now'))),
    $site ?: ($tour['site'] ?? ''),
    $area ?: null,
    $lead_name ?: ($tour['lead_name'] ?? ''),
    $participants ?: null,
    json_encode($responses, JSON_UNESCAPED_UNICODE),
    $scoreA, $scoreT, $scoreP,
    $status,
    $id
  ]);

  // Insert any new actions into safety_actions (so they appear in Actions Register)
  if ($newActions) {
    // Detect priority/severity column name present
    $prioCol = 'priority';
    try {
      $hasPriority = (bool)$pdo->query("SHOW COLUMNS FROM `safety_actions` LIKE 'priority'")->fetch();
      $hasSeverity = (bool)$pdo->query("SHOW COLUMNS FROM `safety_actions` LIKE 'severity'")->fetch();
      if (!$hasPriority && $hasSeverity) $prioCol = 'severity';
    } catch (Throwable $e) {}

    $sqlA = "INSERT INTO safety_actions (tour_id, action, responsible, due_date, $prioCol, status) VALUES (?,?,?,?,?,?)";
    $ins = $pdo->prepare($sqlA);
    foreach ($newActions as $a) {
      $ins->execute([
        (int)$a['tour_id'],
        $a['action'],
        $a['responsible'],
        $a['due_date'],
        $a['priority'],
        'Open'
      ]);
    }
  }

  // Rebuild PDF so edits + images appear
  try {
    $st = $pdo->prepare('SELECT * FROM safety_tours WHERE id=?'); $st->execute([$id]);
    $tour = $st->fetch();
    if ($tour) {
      $pdfPath = __DIR__ . '/uploads/tours/tour-'.$id.'.pdf';
      if (!is_dir(dirname($pdfPath))) mkdir(dirname($pdfPath), 0775, true);
      render_pdf($tour, $pdfPath);
    }
  } catch (Throwable $e) { error_log('PDF rebuild: '.$e->getMessage()); }

  header('Location: edit.php?id='.$id.'&saved=1'); exit;
}

// ==================== VIEW ====================
render_nav('edit');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Tour #<?= (int)$id ?></title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{--bg:#0b1220;--card:#0f172a;--border:#1f2937;--text:#e5e7eb;--muted:#94a3b8;--radius:16px;
        --low:#064e3b;--low-b:#10b981;--med:#4b3b06;--med-b:#f59e0b;--high:#5b0b0b;--high-b:#ef4444}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font:15.5px/1.6 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:1024px;margin:0 auto;padding:18px}
  h1{margin:0 0 10px;font-size:1.35rem}
  h2{font-size:1.05rem;margin:16px 0 8px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin:12px 0}
  label{display:block;margin:6px 0 4px;color:var(--muted);font-weight:600}
  input,select,textarea{width:100%;background:#0b1220;color:#e5e7eb;border:1px solid var(--border);border-radius:10px;padding:10px}
  textarea{min-height:80px;resize:vertical}
  .row2{display:grid;gap:12px;grid-template-columns:1fr 1fr}
  .row3{display:grid;gap:12px;grid-template-columns:1fr 1fr 1fr}
  @media (max-width:820px){.row2,.row3{grid-template-columns:1fr}}
  .muted{color:var(--muted)}
  .btn{background:linear-gradient(180deg,#0ea5e9,#0284c7);border:0;border-radius:12px;color:#00131a;font-weight:800;padding:12px 16px;cursor:pointer}
  .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#0f172a}
  .imgs{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-top:6px}
  .imgs img{width:100%;border-radius:10px;border:1px solid var(--border)}
  .prio.low{ background:var(--low); border-color:var(--low-b) }
  .prio.medium{ background:var(--med); border-color:var(--med-b) }
  .prio.high{ background:var(--high); border-color:var(--high-b) }
</style>
</head>
<body>
<div class="wrap">
  <h1>Edit Safety Tour #<?= (int)$id ?> <?= !empty($_GET['saved']) ? '<span class="pill">Saved</span>' : '' ?></h1>

  <form method="post" enctype="multipart/form-data" class="card">
    <div class="row2">
      <div>
        <label>Project Name</label>
        <input name="site" value="<?= h($tour['site'] ?? '') ?>">
      </div>
      <div>
        <label>Location</label>
        <input name="area" value="<?= h($tour['area'] ?? '') ?>">
      </div>
    </div>
    <div class="row3">
      <div>
        <label>Site Manager</label>
        <input name="lead_name" value="<?= h($tour['lead_name'] ?? '') ?>">
      </div>
      <div>
        <label>Inspection Date &amp; Time</label>
        <input type="datetime-local" name="tour_date" value="<?= h(date('Y-m-d\TH:i', strtotime($tour['tour_date'] ?? 'now'))) ?>">
      </div>
      <div>
        <label>Status</label>
        <select name="status">
          <option <?= (($tour['status'] ?? 'Open')==='Open'?'selected':'') ?>>Open</option>
          <option <?= (($tour['status'] ?? 'Open')==='Closed'?'selected':'') ?>>Closed</option>
        </select>
      </div>
    </div>

    <?php
    // Render each section/row with saved data
    $idx = 0;
    foreach ($CHECKLIST as $section => $items):
    ?>
      <div class="card">
        <h2><?= h($section) ?></h2>
        <?php foreach ($items as $it):
          $code = $it['code']; $question = $it['q'];
          $r = $byCode[$code] ?? ['result'=>'','priority'=>'','note'=>'','due'=>'','action'=>'','responsible'=>'','images'=>[]];
          $imgs = is_array($r['images'] ?? null) ? $r['images'] : [];
        ?>
          <div class="card" style="border-color:#253044">
            <div style="font-weight:700;margin-bottom:8px">
              <span style="opacity:.75;margin-right:8px"><?= h($code) ?></span><?= h($question) ?>
            </div>

            <div class="row3">
              <div>
                <label>Result</label>
                <select name="check_status[]">
                  <?php
                    $opts = [''=>'Select','Pass'=>'Pass','Improvement'=>'Improvement','Fail'=>'Fail','N/A'=>'N/A'];
                    foreach ($opts as $v=>$t) {
                      $sel = ($r['result']??'')===$t ? 'selected' : '';
                      echo '<option value="'.h($t).'" '.$sel.'>'.h($t).'</option>';
                    }
                  ?>
                </select>
              </div>
              <div>
                <label>Priority</label>
                <?php $pv = (string)($r['priority'] ?? ''); $cls = $pv==='High'?'high':($pv==='Medium'?'medium':($pv==='Low'?'low':'')); ?>
                <select name="f_severity[]" class="prio <?= h($cls) ?>">
                  <option value="">Priority</option>
                  <option value="Low"   <?= $pv==='Low'?'selected':'' ?>>Low</option>
                  <option value="Medium"<?= $pv==='Medium'?'selected':'' ?>>Medium</option>
                  <option value="High"  <?= $pv==='High'?'selected':'' ?>>High</option>
                </select>
              </div>
              <div>
                <label>Due (if action)</label>
                <input type="date" name="a_due[]" value="<?= h($r['due'] ?? '') ?>">
              </div>
            </div>

            <div class="row2">
              <div>
                <label>Action / To do (if required)</label>
                <input name="a_action[]" value="<?= h($r['action'] ?? '') ?>">
              </div>
              <div>
                <label>Responsible</label>
                <input name="a_resp[]" value="<?= h($r['responsible'] ?? '') ?>">
              </div>
            </div>

            <label>Notes / Evidence</label>
            <textarea name="f_note[]"><?= h($r['note'] ?? '') ?></textarea>

            <?php if ($imgs): ?>
              <div class="imgs">
                <?php foreach ($imgs as $p): ?>
                  <a href="<?= h($p) ?>" target="_blank"><img src="<?= h($p) ?>" alt=""></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div style="margin-top:6px">
              <div class="muted">Add images for this question (optional)</div>
              <!-- IMPORTANT: index-based multi-file input for this question -->
              <input type="file" name="qphotos[<?= $idx ?>][]" accept="image/*" multiple>
            </div>
          </div>
        <?php $idx++; endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:10px;justify-content:flex-end">
      <a class="pill" href="dashboard.php">← Back</a>
      <button class="btn" type="submit">Save changes</button>
      <a class="pill" href="pdf.php?id=<?= (int)$id ?>" target="_blank">Open PDF</a>
    </div>
  </form>
</div>

<script>
// color-coded Priority selects
document.querySelectorAll('select.prio').forEach(sel=>{
  const apply=()=>{ sel.classList.remove('low','medium','high'); const v=sel.value.toLowerCase(); if(v==='low') sel.classList.add('low'); if(v==='medium') sel.classList.add('medium'); if(v==='high') sel.classList.add('high'); };
  sel.addEventListener('change',apply); apply();
});
</script>
</body>
</html>
