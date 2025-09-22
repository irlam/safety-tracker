<?php
// File: submit.php
// Description: Processes and saves a submitted Site Safety Tour form, handles file uploads (including per-question photos and signatures), generates a PDF report, sends notification emails, and redirects to the success page. All times/dates are handled in UK format. This file is modernised and commented for maintainability, clarity, and future-proofing. (For non-coders: each section is explained and the code is formatted for easy reading.)

declare(strict_types=1);

// ----------------------------
// 1. Optional authentication
// ----------------------------
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    if (function_exists('auth_check')) auth_check();
}

// ----------------------------
// 2. Includes and timezone
// ----------------------------
require_once __DIR__ . '/includes/functions.php';
// Set to UK timezone
date_default_timezone_set('Europe/London');

$pdo = db();

// ----------------------------
// 3. Helper functions
// ----------------------------

/**
 * Convert a string to a URL/file-friendly slug (letters, numbers, dashes)
 */
function hslug(string $s): string {
    $s = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', $s), '-'));
    return $s !== '' ? $s : 'tour';
}

/**
 * List columns in a database table
 */
function table_columns(PDO $pdo, string $table): array {
    try {
        $st = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?");
        $st->execute([$table]);
        return array_map(fn($r) => $r['COLUMN_NAME'], $st->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Check if a column exists in a table
 */
function col_exists(PDO $pdo, string $table, string $col): bool {
    static $cache = [];
    $key = $table;
    if (!isset($cache[$key])) $cache[$key] = table_columns($pdo, $table);
    return in_array($col, $cache[$key] ?? [], true);
}

/**
 * Save a data:URL PNG signature to /uploads/signatures and return a web-relative path
 */
function save_signature_data(string $dataUrl): ?string {
    if (!preg_match('~^data:image/(png|jpeg);base64,~', $dataUrl)) return null;
    $data = base64_decode(preg_replace('~^data:image/\w+;base64,~', '', $dataUrl), true);
    if ($data === false) return null;
    $dir = __DIR__ . '/uploads/signatures';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'sig_' . uniqid('', true) . '.png';
    $abs  = $dir . '/' . $name;
    if (@file_put_contents($abs, $data) === false) return null;
    return 'uploads/signatures/' . $name;
}

// ----------------------------
// 4. Checklist definition (as in form.php)
// ----------------------------

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
        ['code'=>'4.1','q'=>"Are all plant & equipment in good order, maintained, and with the required certification."],
        ['code'=>'4.2','q'=>"Is plant & equipment being used by trained/authorized/competent personnel."],
        ['code'=>'4.3','q'=>"Are daily/weekly plant checks being completed and recorded."],
        ['code'=>'4.4','q'=>"Are plant & equipment fitted with suitable safety devices and guards, and are these being correctly used."],
        ['code'=>'4.5','q'=>"Are lifting operations being planned and carried out by competent persons."],
    ],
    '5.0 Fire & Emergency' => [
        ['code'=>'5.1','q'=>"Are fire points, extinguishers, and alarms available, accessible, and in-service date."],
        ['code'=>'5.2','q'=>"Are emergency response procedures displayed and briefed to all personnel."],
        ['code'=>'5.3','q'=>"Are fire routes and assembly points clearly identified and free from obstruction."],
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

// Flatten the checklist for easy lookup
$FLAT = [];
foreach ($CHECKLIST as $section => $items) {
    foreach ($items as $i) {
        $FLAT[] = ['section' => $section, 'code' => $i['code'], 'q' => $i['q']];
    }
}

// ----------------------------
// 5. Gather POST data
// ----------------------------

$site         = trim($_POST['site']        ?? '');
$area         = trim($_POST['area']        ?? '');
$lead_name    = trim($_POST['lead_name']   ?? '');
$participants = trim($_POST['participants']?? '');
$tour_date_in = (string)($_POST['tour_date'] ?? '');
// Store in UK format (Y-m-d H:i:s)
$tour_date    = $tour_date_in ? date('Y-m-d H:i:s', strtotime($tour_date_in)) : date('Y-m-d H:i:s');

$check_status = $_POST['check_status'] ?? [];
$f_severity   = $_POST['f_severity']   ?? [];
$a_due        = $_POST['a_due']        ?? [];
$a_action     = $_POST['a_action']     ?? [];
$a_resp       = $_POST['a_resp']       ?? [];
$f_note       = $_POST['f_note']       ?? [];
$f_category   = $_POST['f_category']   ?? [];

$score_achieved = isset($_POST['score_achieved']) ? (int)$_POST['score_achieved'] : null;
$score_total    = isset($_POST['score_total'])    ? (int)$_POST['score_total']    : null;
$score_percent  = isset($_POST['score_percent']) && $_POST['score_percent']!=='' ? (float)str_replace('%','',$_POST['score_percent']) : null;

// Recipients (comma-separated email list)
$recipients_raw = trim((string)($_POST['recipients'] ?? ''));
$recipients = [];
if ($recipients_raw !== '') {
    foreach (explode(',', $recipients_raw) as $e) {
        $e = strtolower(trim($e));
        if ($e && preg_match('~^[^@\s]+@[^@\s]+\.[^@\s]+$~', $e)) $recipients[] = $e;
    }
    $recipients = array_values(array_unique($recipients));
}

// ----------------------------
// 6. Signature (required: file OR canvas dataURL)
// ----------------------------
$signature_path = null;
$has_file = !empty($_FILES['signature_file']['tmp_name']) && is_uploaded_file($_FILES['signature_file']['tmp_name']);
$has_data = isset($_POST['signature_data']) && strpos($_POST['signature_data'], 'data:image/') === 0;

if ($has_file) {
    $signature_path = save_file($_FILES['signature_file'], 'signatures');
} elseif ($has_data) {
    $signature_path = save_signature_data((string)$_POST['signature_data']);
}

if (!$signature_path) {
    http_response_code(400);
    echo "Signature required.";
    exit;
}

// ----------------------------
// 7. Save per-question photos and extra photos
// ----------------------------

// Per-question matrix (qphotos[index][]) 
$qImages = []; // index => [paths...]
if (!empty($_FILES['qphotos']['name']) && is_array($_FILES['qphotos']['name'])) {
    foreach ($_FILES['qphotos']['name'] as $idx => $names) {
        if (!is_array($names)) continue;
        foreach ($names as $k => $n) {
            $tmp = [
                'name'     => $_FILES['qphotos']['name'][$idx][$k] ?? '',
                'type'     => $_FILES['qphotos']['type'][$idx][$k] ?? '',
                'tmp_name' => $_FILES['qphotos']['tmp_name'][$idx][$k] ?? '',
                'error'    => $_FILES['qphotos']['error'][$idx][$k] ?? 0,
                'size'     => $_FILES['qphotos']['size'][$idx][$k] ?? 0,
            ];
            if (empty($tmp['tmp_name'])) continue;
            $rel = save_file($tmp, 'tours'); // save now; we'll not depend on tour id path
            if ($rel) $qImages[(int)$idx][] = $rel;
        }
    }
}

// Extra photos
$photos = [];
if (!empty($_FILES['photos']['name'][0] ?? '')) {
    foreach ($_FILES['photos']['name'] as $i => $n) {
        $tmp = [
            'name'     => $_FILES['photos']['name'][$i] ?? '',
            'type'     => $_FILES['photos']['type'][$i] ?? '',
            'tmp_name' => $_FILES['photos']['tmp_name'][$i] ?? '',
            'error'    => $_FILES['photos']['error'][$i] ?? 0,
            'size'     => $_FILES['photos']['size'][$i] ?? 0,
        ];
        if (empty($tmp['tmp_name'])) continue;
        $rel = save_file($tmp, 'tours');
        if ($rel) $photos[] = $rel;
    }
}

// ----------------------------
// 8. Build responses array (aligned to FLAT checklist)
// ----------------------------
$responses = [];
$N = max(
    count($FLAT),
    count($check_status), count($f_severity), count($a_due),
    count($a_action), count($a_resp), count($f_note), count($f_category)
);

for ($i = 0; $i < $N; $i++) {
    $meta = $FLAT[$i] ?? ['section'=>$f_category[$i] ?? '', 'code'=>'', 'q'=>''];
    $responses[] = [
        'section'    => (string)($meta['section'] ?? ($f_category[$i] ?? '')),
        'code'       => (string)($meta['code'] ?? ''),
        'question'   => (string)($meta['q'] ?? ''),
        'result'     => (string)($check_status[$i] ?? ''),
        'priority'   => (string)($f_severity[$i]   ?? ''),
        'note'       => (string)($f_note[$i]       ?? ''),
        'action'     => (string)($a_action[$i]     ?? ''),
        'responsible'=> (string)($a_resp[$i]       ?? ''),
        'due'        => (string)($a_due[$i]        ?? ''),
        'images'     => array_values($qImages[$i] ?? []),
    ];
}

// ----------------------------
// 9. Auto score calculation (if not supplied)
// ----------------------------
$score_auto = tally_score($responses);
if ($score_achieved === null || $score_total === null) {
    $score_total    = $score_auto['counts']['pass'] + $score_auto['counts']['fail'] + $score_auto['counts']['improvement'];
    $score_achieved = $score_auto['counts']['pass'];
}
if ($score_percent === null) {
    $score_percent = $score_total > 0 ? round(($score_achieved / $score_total) * 100, 2) : null;
}

// ----------------------------
// 10. Insert tour into database (only columns that exist)
// ----------------------------

$cols = table_columns($pdo, 'safety_tours');
$data = [
    'tour_date'      => $tour_date,
    'site'           => $site,
    'area'           => $area,
    'lead_name'      => $lead_name,
    'participants'   => $participants,
    'responses'      => json_encode($responses, JSON_UNESCAPED_UNICODE),
    'photos'         => json_encode($photos, JSON_UNESCAPED_UNICODE),
    'signature_path' => $signature_path,
    'status'         => 'Open',
    'score_achieved' => $score_achieved,
    'score_total'    => $score_total,
    'score_percent'  => $score_percent,
    'recipients'     => $recipients ? implode(',', $recipients) : null,
];

// Only insert columns that exist
$insCols = [];
$insMarks= [];
$insArgs = [];
foreach ($data as $k => $v) {
    if (in_array($k, $cols, true)) {
        $insCols[]  = "`$k`";
        $insMarks[] = "?";
        $insArgs[]  = $v;
    }
}
$sql = "INSERT INTO `safety_tours` (".implode(',', $insCols).") VALUES (".implode(',', $insMarks).")";
$st  = $pdo->prepare($sql);
$st->execute($insArgs);
$id = (int)$pdo->lastInsertId();

// ----------------------------
// 11. Save/refresh recipient directory
// ----------------------------
if ($recipients) {
    foreach ($recipients as $em) {
        try {
            $pdo->prepare("INSERT INTO safety_recipient_emails (email, label, use_count, last_used)
            VALUES (?, '', 1, NOW())
            ON DUPLICATE KEY UPDATE use_count = use_count+1, last_used = NOW()
            ")->execute([$em]);
        } catch (Throwable $e) {
            // non-fatal
        }
    }
}

// ----------------------------
// 12. Generate PDF and send notification email
// ----------------------------

$pdfOK = 0; $mailOK = 0;

// UK file name e.g. SafetyTour_rochdale-road_21-09-2025_14-30.pdf
$ukDate  = date('d-m-Y_H-i', strtotime($tour_date));
$slug    = hslug($site);
$dir     = __DIR__ . '/uploads/tours/' . $id;
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$outPath = $dir . '/SafetyTour_' . $slug . '_' . $ukDate . '.pdf';

try {
    // Fetch the row to feed the PDF renderer (ensures correct DB formatting)
    $tour = $pdo->prepare("SELECT * FROM safety_tours WHERE id=?");
    $tour->execute([$id]);
    $row = $tour->fetch();
    render_pdf($row, $outPath);
    $pdfOK = 1;
} catch (Throwable $e) {
    error_log('PDF: '.$e->getMessage());
}

$toSend = $recipients ?: [SMTP_USER]; // fallback so nothing is lost
try {
    $mailOK = send_mail_multi(
        $toSend,
        'Safety Tour #'.$id.' — '.$site,
        '<p>Safety Tour <strong>#'.$id.'</strong> for <strong>'.htmlspecialchars($site).'</strong> ('.$ukDate.').</p>'.
        '<p><a href="'.htmlspecialchars((($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.$_SERVER['HTTP_HOST']).'/pdf.php?id='.$id).'">Open PDF</a></p>',
        [['path'=>$outPath, 'name'=>basename($outPath)]]
    ) ? 1 : 0;
} catch (Throwable $e) {
    error_log('MAIL: '.$e->getMessage());
}

// ----------------------------
// 13. Done → redirect to success page
// ----------------------------
header('Location: success.php?id='.$id.'&pdf='.$pdfOK.'&mail='.$mailOK);
exit;
