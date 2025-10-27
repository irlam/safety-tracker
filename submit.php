<?php
// /submit.php — save tour, create actions, email PDF
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
@date_default_timezone_set('Europe/London');

function post_arr(string $k): array { return isset($_POST[$k]) && is_array($_POST[$k]) ? $_POST[$k] : []; }
function hstr(?string $v): string { return trim((string)$v); }

// -------- Gather basic fields --------
$site         = hstr($_POST['site']        ?? '');
$area         = hstr($_POST['area']        ?? '');
$lead_name    = hstr($_POST['lead_name']   ?? '');
$participants = hstr($_POST['participants']?? '');
$tour_date_in = hstr($_POST['tour_date']   ?? '');               // datetime-local
$tour_date    = $tour_date_in ? date('Y-m-d H:i:s', strtotime($tour_date_in)) : date('Y-m-d H:i:s');

// Optional manual score (we’ll also recompute % below)
$score_achieved = isset($_POST['score_achieved']) ? (int)$_POST['score_achieved'] : null;
$score_total    = isset($_POST['score_total'])    ? (int)$_POST['score_total']    : null;
$score_percent  = null;

// Recipients (CSV from chips)
$recipients_csv = hstr($_POST['recipients'] ?? '');
$recipient_list = array_values(array_filter(array_map(fn($e)=>strtolower(trim($e)), explode(',', $recipients_csv)), fn($e)=>$e!=='' && filter_var($e, FILTER_VALIDATE_EMAIL)));

// -------- Build responses (with per-question photos) --------
$q_codes   = post_arr('q_code');     // ["1.1", "1.2", ...]
$q_texts   = post_arr('q_text');     // question text
$results   = post_arr('check_status');  // Pass/Improvement/Fail/N/A
$prior     = post_arr('f_severity');    // Low/Medium/High (optional)
$notes     = post_arr('f_note');        // evidence/notes
$actions   = post_arr('a_action');      // action text (if any)
$respons   = post_arr('a_resp');        // responsible
$dues      = post_arr('a_due');         // YYYY-MM-DD
$cats      = post_arr('f_category');    // section name per q

// collect per-question images from $_FILES['qphotos'][qIndex][]
$resp = [];
$totalQs = count($q_codes);
for ($i=0; $i<$totalQs; $i++) {
    $imgs = [];
    if (!empty($_FILES['qphotos']['name'][$i]) && is_array($_FILES['qphotos']['name'][$i])) {
        // Unroll the nested arrays for index $i
        $names = $_FILES['qphotos']['name'][$i];
        $types = $_FILES['qphotos']['type'][$i];
        $tmps  = $_FILES['qphotos']['tmp_name'][$i];
        $errs  = $_FILES['qphotos']['error'][$i];
        $sizes = $_FILES['qphotos']['size'][$i];
        foreach ($names as $k => $n) {
            if (!isset($tmps[$k]) || !is_uploaded_file($tmps[$k])) continue;
            $rel = save_file([
                'name'     => $names[$k],
                'type'     => $types[$k],
                'tmp_name' => $tmps[$k],
                'error'    => $errs[$k],
                'size'     => $sizes[$k],
            ], 'tours');
            if ($rel) $imgs[] = $rel;
        }
    }

    $resp[] = [
        'section'    => $cats[$i]    ?? '',
        'code'       => $q_codes[$i] ?? '',
        'question'   => $q_texts[$i] ?? '',
        'result'     => $results[$i] ?? '',
        'priority'   => $prior[$i]   ?? '',
        'note'       => $notes[$i]   ?? '',
        'action'     => $actions[$i] ?? '',
        'responsible'=> $respons[$i] ?? '',
        'due'        => $dues[$i]    ?? '',
        'images'     => $imgs,
    ];
}

// -------- Extra photos (not tied to a specific question) --------
$extra_photos = [];
if (!empty($_FILES['photos']['name'][0])) {
    foreach ($_FILES['photos']['name'] as $i => $name) {
        if (!is_uploaded_file($_FILES['photos']['tmp_name'][$i] ?? '')) continue;
        $rel = save_file([
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ], 'tours');
        if ($rel) $extra_photos[] = $rel;
    }
}

// -------- Signature (required: canvas OR file) --------
$signature_path = null;
$canvas_b64 = $_POST['signature_data'] ?? '';
if ($canvas_b64 && str_starts_with($canvas_b64, 'data:image/')) {
    $bin = explode(',', $canvas_b64, 2)[1] ?? '';
    $raw = base64_decode($bin, true);
    if ($raw !== false) {
        $dir = __DIR__ . '/uploads/signatures';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $name = 'sig_' . uniqid('', true) . '.png';
        file_put_contents($dir . '/' . $name, $raw);
        $signature_path = 'uploads/signatures/' . $name;
    }
} elseif (!empty($_FILES['signature_file']['name'])) {
    $rel = save_file($_FILES['signature_file'], 'signatures');
    if ($rel) $signature_path = $rel;
}
if (!$signature_path) {
    // Simple guard; front-end should prevent this, but keep it server-side too
    http_response_code(422);
    echo "Signature is required."; exit;
}

// -------- Recompute score from responses (Pass = 1, Improve/Fail/N/A = 0 by default) --------
$score_json = ['per_q' => []];
$autoA = 0; $autoT = 0;
foreach ($resp as $r) {
    if (!empty($r['result'])) {
        $autoT++;
        if ($r['result'] === 'Pass') $autoA++;
    }
    $score_json['per_q'][] = ['code'=>$r['code'], 'result'=>$r['result'] ?? null];
}
if ($score_total === null)    $score_total    = $autoT ?: null;
if ($score_achieved === null) $score_achieved = $autoA ?: null;
if ($score_total && $score_achieved !== null) {
    $score_percent = round(($score_achieved / max(1,$score_total)) * 100, 2);
}

// -------- Insert tour --------
$pdo = db();
$sql = "INSERT INTO safety_tours
  (tour_date, site, area, lead_name, participants,
   recipients, responses, score_achieved, score_total, score_percent, score_json,
   photos, signature_name, signature_path, status)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Open')";
$st = $pdo->prepare($sql);
$st->execute([
    $tour_date,
    $site,
    $area ?: null,
    $lead_name,
    $participants ?: null,
    $recipient_list ? implode(',', $recipient_list) : null,
    json_encode($resp, JSON_UNESCAPED_UNICODE),
    $score_achieved,
    $score_total,
    $score_percent,
    json_encode($score_json, JSON_UNESCAPED_UNICODE),
    json_encode($extra_photos, JSON_UNESCAPED_UNICODE),
    $lead_name ?: null,
    $signature_path
]);
$tour_id = (int)$pdo->lastInsertId();

// -------- Create safety_actions rows from responses that need them --------
$insAct = $pdo->prepare("
  INSERT INTO safety_actions (tour_id, action, responsible, due_date, priority, status, created_at)
  VALUES (?,?,?,?,?, 'Open', NOW())
");
foreach ($resp as $r) {
    $needsAction = ($r['action'] ?? '') !== '' || in_array(($r['result'] ?? ''), ['Fail','Improvement'], true);
    if (!$needsAction) continue;
    $due = hstr($r['due'] ?? '');
    $dueDate = $due ? date('Y-m-d', strtotime($due)) : null;
    $prio = hstr($r['priority'] ?? '');
    if ($prio === '') $prio = null;
    $insAct->execute([
        $tour_id,
        hstr($r['action'] ?? '') ?: ($r['question'] ?? ''),   // if no action text, keep question as reminder
        hstr($r['responsible'] ?? '') ?: null,
        $dueDate,
        $prio,
    ]);
}

// -------- Upsert recipient emails into safety_recipient_emails --------
if ($recipient_list) {
    // Make sure unique index exists on (email) for ON DUPLICATE KEY to work.
    // ALTER TABLE safety_recipient_emails ADD UNIQUE KEY uq_email (email);
    $insRec = $pdo->prepare("
      INSERT INTO safety_recipient_emails (email, use_count, last_used, created_at, updated_at)
      VALUES (?, 1, NOW(), NOW(), NOW())
      ON DUPLICATE KEY UPDATE use_count = use_count + 1, last_used = NOW(), updated_at = NOW()
    ");
    foreach ($recipient_list as $em) {
        try { $insRec->execute([$em]); } catch (Throwable $e) { /* ignore */ }
    }
}

// -------- PDF (UK filename) --------
$ukStamp = date('Y-m-d_H-i', strtotime($tour_date));       // 2025-09-23_14-06
$baseName = preg_replace('~[^a-z0-9]+~i', '-', $site . ($area ? '-'.$area : ''));
$baseName = trim($baseName, '-');
$pdfDir   = __DIR__ . '/uploads/tours';
if (!is_dir($pdfDir)) mkdir($pdfDir, 0775, true);
$pdfPath  = $pdfDir . '/SafetyTour_' . $baseName . '_' . $ukStamp . '.pdf';

// Render using your existing pdf.php logic if file missing later, but also create now:
try {
    // Fetch fresh row for render
    $st2 = $pdo->prepare("SELECT * FROM safety_tours WHERE id=?");
    $st2->execute([$tour_id]);
    $tourRow = $st2->fetch();
    if ($tourRow) {
        // You said render_pdf() exists in includes/functions.php in your build
        if (function_exists('render_pdf')) {
            render_pdf($tourRow, $pdfPath);
        }
    }
} catch (Throwable $e) { /* non-fatal */ }

// -------- Email PDF --------
$mailOK = false;
if ($recipient_list && file_exists($pdfPath)) {
    $subj = 'Safety Tour #'.$tour_id.' — '.$site.($area?' / '.$area:'');
    $body = '<p>Safety Tour <strong>#'.$tour_id.'</strong> recorded for <strong>'.htmlspecialchars($site).'</strong>.</p>'.
            '<p>Date: '.htmlspecialchars(date('d/m/Y H:i', strtotime($tour_date))).'</p>'.
            '<p>Open PDF: <a href="/pdf.php?id='.$tour_id.'">Report</a></p>';
    foreach ($recipient_list as $em) {
        $ok = send_mail($em, $subj, $body, [['path'=>$pdfPath, 'name'=>basename($pdfPath)]]);
        if ($ok) $mailOK = true;
    }
}

// -------- Redirect --------
$success = __DIR__ . '/success.php';
if (is_file($success)) {
    header('Location: success.php?id='.$tour_id.'&pdf='.(int)file_exists($pdfPath).'&mail='.(int)$mailOK);
} else {
    header('Location: pdf.php?id='.$tour_id);
}
exit;
