<?php
// File: includes/functions.php
// Description: Core helper functions for the Safety Tour app. Includes DB access, file uploads, mail, PDF rendering, and other utilities. All times in UK format. Fully commented for maintainability by non-coders.

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------- Timezone (UK) ----------
date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Europe/London');

// ---------- PHPMailer bootstrap (self-hosted, no Composer required) ----------
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
  $PHPMailerSrc = __DIR__ . '/../lib/phpmailer/src';
  if (is_dir($PHPMailerSrc)) {
    foreach (['PHPMailer','SMTP','Exception'] as $c) {
      $p = $PHPMailerSrc . '/' . $c . '.php';
      if (is_file($p)) require_once $p;
    }
  }
}

// ---------- Small helpers ----------
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Shows PHP’s effective upload/post limits.
 */
function upload_limits_info(): array {
  $toBytes = function(string $v): int {
    $v = trim($v);
    if ($v === '') return 0;
    $unit = strtolower(substr($v, -1));
    $num  = (float)$v;
    return match ($unit) {
      'g' => (int)($num * 1024 * 1024 * 1024),
      'm' => (int)($num * 1024 * 1024),
      'k' => (int)($num * 1024),
      default => (int)$num
    };
  };
  $upload_max = $toBytes(ini_get('upload_max_filesize') ?: '0');
  $post_max   = $toBytes(ini_get('post_max_size') ?: '0');
  $mem_limit  = $toBytes(ini_get('memory_limit') ?: '0');
  return [
    'upload_max_filesize' => $upload_max,
    'post_max_size'       => $post_max,
    'memory_limit'        => $mem_limit,
    'max_file_uploads'    => (int)(ini_get('max_file_uploads') ?: 20),
    'effective_cap'       => min(array_filter([$upload_max, $post_max]) ?: [0]),
  ];
}

// ============================================================================
// DB
// ============================================================================
function db(): PDO {
  static $pdo;
  if ($pdo instanceof PDO) return $pdo;

  $dsn = 'mysql:host=' . DB_HOST
       . (defined('DB_PORT') && DB_PORT ? ';port='.DB_PORT : '')
       . ';dbname=' . DB_NAME . ';charset=utf8mb4';

  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  try {
    // Ensure MySQL session timezone aligns with UK
    $pdo->query("SET time_zone = 'Europe/London'");
  } catch (Throwable $e) {}

  return $pdo;
}

// ============================================================================
// FILES
// ============================================================================
/**
 * Save a single uploaded file to /uploads/<folder>.
 * Returns a web-relative path (so browser & FPDF can resolve it).
 */
function save_file(array $file, string $folder): ?string {
  if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;

  $base = rtrim(__DIR__ . '/../uploads', '/');
  $dir  = $base . '/' . trim($folder, '/');
  if (!is_dir($dir)) mkdir($dir, 0775, true);

  $ext  = strtolower(pathinfo($file['name'] ?? 'bin', PATHINFO_EXTENSION) ?: 'bin');
  $name = uniqid('f_', true) . '.' . $ext;
  $dest = $dir . '/' . $name;

  if (!@move_uploaded_file($file['tmp_name'], $dest)) return null;

  // Return web-relative path from project root
  return str_replace(__DIR__ . '/../', '', $dest);
}

/**
 * Save a matrix of per-question images
 * expects inputs like: <input type="file" name="qphotos[INDEX][]" multiple>
 * Returns: [ index => ['uploads/tours/q-<tourId>/file1.jpg', ...], ... ]
 */
function save_question_files(array $filesMatrix, int $tourId): array {
  if (!$filesMatrix || empty($filesMatrix['name'])) return [];
  $saved = [];

  // Normalized: name[index][k], type[index][k], tmp_name[index][k], error[index][k], size[index][k]
  foreach ($filesMatrix['name'] as $idx => $names) {
    if (!is_array($names)) continue;
    foreach ($names as $k => $n) {
      $tmp = [
        'name'     => $filesMatrix['name'][$idx][$k] ?? '',
        'type'     => $filesMatrix['type'][$idx][$k] ?? '',
        'tmp_name' => $filesMatrix['tmp_name'][$idx][$k] ?? '',
        'error'    => $filesMatrix['error'][$idx][$k] ?? 0,
        'size'     => $filesMatrix['size'][$idx][$k] ?? 0,
      ];
      if (empty($tmp['tmp_name'])) continue;
      $rel = save_file($tmp, 'tours/q-' . (int)$tourId);
      if ($rel) $saved[$idx][] = $rel;
    }
  }
  return $saved;
}

// ============================================================================
// JSON helpers
// ============================================================================
function json_arr(null|string $json): array {
  if (!$json) return [];
  $x = json_decode($json, true);
  return is_array($x) ? $x : [];
}

// ============================================================================
// MAIL (safe if PHPMailer absent)
// ============================================================================
function send_mail_multi(array $recipients, string $subject, string $html, array $attachments = []): bool {
  if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    error_log('MAIL: PHPMailer not found; skipping send.');
    return false;
  }
  $ok = true;
  foreach ($recipients as $to) {
    if (!$to) continue;
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : '';
      $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 465;
      $mail->SMTPAuth   = true;
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; // 465 SSL/TLS
      $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
      $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';

      $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : (defined('SMTP_USER') ? SMTP_USER : 'noreply@localhost');
      $fromName  = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Safety Tours';
      $mail->setFrom($fromEmail, $fromName);
      $mail->addAddress($to);

      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body    = $html;

      foreach ($attachments as $a) {
        if (!empty($a['path']) && is_file($a['path'])) {
          $mail->addAttachment($a['path'], $a['name'] ?? basename($a['path']));
        }
      }
      $mail->send();
    } catch (Throwable $e) {
      error_log('MAIL: '.$e->getMessage());
      $ok = false;
    }
  }
  return $ok;
}

// ============================================================================
// SCORING
// ============================================================================
/**
 * responses: array of { code, question, result, priority, note, images[] }
 * Score % = Pass / (Pass + Fail + Improvement) * 100  (N/A ignored)
 */
function tally_score(array $responses): array {
  $counts = ['pass'=>0,'fail'=>0,'improvement'=>0,'na'=>0];
  foreach ($responses as $r) {
    $k = strtolower(trim((string)($r['result'] ?? '')));
    if (!isset($counts[$k])) $k = 'na';
    $counts[$k]++;
  }
  $den = $counts['pass'] + $counts['fail'] + $counts['improvement'];
  $percent = $den > 0 ? round(($counts['pass'] / $den) * 100, 2) : null;
  return ['percent'=>$percent, 'counts'=>$counts];
}

// ============================================================================
// FPDF helpers + renderer (with per-question thumbnails)
// ============================================================================
if (!class_exists('FPDF')) {
  $fpdfLocal = __DIR__ . '/../lib/fpdf/fpdf.php';
  if (is_file($fpdfLocal)) require_once $fpdfLocal;
}

if (!function_exists('pdf_text')) {
  function pdf_text(string $txt): string { return iconv('UTF-8', 'windows-1252//TRANSLIT', $txt); }
}

/**
 * Estimate wrapped text lines for MultiCell using current font metrics.
 */
function pdf_nb_lines(FPDF $pdf, float $w, string $txt): int {
  $txt = (string)$txt; if ($txt==='') return 1;
  $maxW = max(1.0,$w); $lines=1; $lineW=0.0;
  foreach (preg_split('/(\s+)/u', $txt, -1, PREG_SPLIT_DELIM_CAPTURE) as $chunk) {
    $cw = $pdf->GetStringWidth($chunk);
    if ($lineW + $cw <= $maxW) { $lineW += $cw; continue; }
    if ($cw > $maxW) { // split long word
      $acc=''; $accW=0.0; $len=mb_strlen($chunk,'UTF-8');
      for($i=0;$i<$len;$i++){
        $ch=mb_substr($chunk,$i,1,'UTF-8'); $chW=$pdf->GetStringWidth($ch);
        if ($accW+$chW <= ($maxW-$lineW)) { $acc.=$ch; $accW+=$chW; }
        else { $lines++; $lineW=0.0; $acc=$ch; $accW=$chW; }
      }
      $lineW += $accW;
    } else { $lines++; $lineW=$cw; }
  }
  return $lines;
}

/** Draw a row (with borders) using MultiCell columns. */
function pdf_row(FPDF $pdf, array $cols, float $lineH=6.0): void {
  $maxLines=1; foreach($cols as [$w,$t]) $maxLines=max($maxLines, pdf_nb_lines($pdf,(float)$w,(string)$t));
  $rowH = $lineH * $maxLines;
  $x0=$pdf->GetX(); $y0=$pdf->GetY();
  foreach($cols as $c){
    [$w,$t,$a]=[$c[0],(string)($c[1]??''),$c[2]??'L'];
    $x=$pdf->GetX(); $y=$pdf->GetY();
    $pdf->Rect($x,$y,$w,$rowH);
    $pdf->MultiCell($w,$lineH,pdf_text($t),0,$a);
    $pdf->SetXY($x+$w,$y);
  }
  $pdf->SetXY($x0,$y0+$rowH);
}

function pdf_badge(FPDF $pdf, string $text, array $rgb, float $w=22.0, float $h=6.0): void {
  [$r,$g,$b]=$rgb; $x=$pdf->GetX(); $y=$pdf->GetY();
  $pdf->SetFillColor($r,$g,$b); $pdf->SetTextColor(255,255,255);
  $pdf->Cell($w,$h,pdf_text($text),0,0,'C',true);
  $pdf->SetTextColor(20,20,20); $pdf->SetXY($x+$w,$y);
}
function pdf_result_colour(string $result): array {
  $k=strtolower(trim($result));
  return match($k){
    'pass' => [34,197,94],
    'fail' => [239,68,68],
    'improvement' => [245,158,11],
    'n/a','na' => [100,116,139],
    default => [100,116,139],
  };
}
function pdf_priority_colour(string $prio): array {
  $k=strtolower(trim($prio));
  return match($k){
    'low' => [34,197,94],
    'medium' => [245,158,11],
    'high' => [239,68,68],
    default => [100,116,139],
  };
}

function find_logo_path(): ?string {
  foreach ([
    __DIR__.'/../assets/img/mcgoff.png',
    $_SERVER['DOCUMENT_ROOT'].'/assets/img/mcgoff.png',
    __DIR__.'/../assets/img/logo.png',
    $_SERVER['DOCUMENT_ROOT'].'/assets/img/logo.png',
  ] as $p) if (@is_file($p)) return $p;
  return null;
}

/**
 * Renders a complete PDF for a tour.
 * Expects $tour to contain:
 *  - responses (JSON): [{code, question, result, priority, note, images[]}, ...]
 *  - photos (JSON): ["uploads/.../x.jpg", ...]  (tour-level misc photos)
 */
function render_pdf(array $tour, string $outPath): void {
  if (!class_exists('FPDF')) {
    $fpdfLocal = __DIR__ . '/../lib/fpdf/fpdf.php';
    if (is_file($fpdfLocal)) require_once $fpdfLocal;
  }
  if (!class_exists('FPDF')) throw new RuntimeException('FPDF not found.');

  $BOTTOM_MARGIN = 14;
  $ukDateTime = fn(?string $ts)=>($ts?date('d/m/Y H:i', strtotime($ts)):'');
  $guard = function(FPDF $pdf,float $need) use($BOTTOM_MARGIN){
    if($pdf->GetY()+$need > ($pdf->GetPageHeight()-$BOTTOM_MARGIN)) $pdf->AddPage();
  };
  $thead = function(FPDF $pdf,array $cells,array $w){
    $pdf->SetFillColor(240,243,247);
    $pdf->SetFont('Arial','B',11);
    foreach($cells as $i=>$t){ $pdf->Cell($w[$i],7,pdf_text($t),1,0,'L',true); }
    $pdf->Ln(7);
    $pdf->SetFont('Arial','',11);
  };
  $section = function(FPDF $pdf,string $title) use($guard){
    $guard($pdf,18);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,8,pdf_text($title),0,1);
    $pdf->SetFont('Arial','',11);
  };

  // ---- Page setup
  $pdf = new FPDF('P','mm','A4');
  $pdf->SetMargins(12,12,12);
  $pdf->SetAutoPageBreak(true,$BOTTOM_MARGIN);
  $pdf->AddPage();
  $pdf->SetDrawColor(180,180,180);
  $pdf->SetTextColor(20,20,20);

  // ---- Header
  if ($logo=find_logo_path()) $pdf->Image($logo,12,10,28);
  $pdf->SetFont('Arial','B',16);
  $pdf->Cell(0,10,pdf_text('Site Safety Tour — Report'),0,1,'R');

  $pdf->SetFont('Arial','',10);
  $scoreTxt = '';
  $responses = json_arr($tour['responses'] ?? null);
  if ($responses) {
    $sc = tally_score($responses);
    if ($sc['percent'] !== null) {
      $scoreTxt = ' — Score '.number_format($sc['percent'],2).'%';
    }
  }
  $pdf->Cell(0,6,pdf_text('Generated '.date('d/m/Y H:i').' (UK)'.$scoreTxt),0,1,'R');
  $pdf->Ln(3);

  // ---- Details
  $section($pdf,'Details');
  $labelW=45; $valW=190-$labelW;
  foreach ([
    ['Date',$ukDateTime($tour['tour_date']??'')],
    ['Site / Area', trim(($tour['site']??'').' / '.($tour['area']??''),' /')],
    ['Lead / Participants', trim(($tour['lead_name']??'').' / '.($tour['participants']??''),' /')],
    ['Status',$tour['status']??'Open'],
  ] as [$k,$v]){
    $guard($pdf,10);
    pdf_row($pdf,[[$labelW,$k,'L'],[$valW,(string)$v,'L']],6.0);
  }
  $pdf->Ln(2.5);

  // ---- Questions (+ per-question images directly under each row)
  if ($responses) {
    $section($pdf,'Questions');
    $w=[18,90,24,24,34];
    $thead($pdf,['Ref','Question','Result','Priority','Notes'],$w);

    foreach ($responses as $row) {
      $guard($pdf,12);
      $code=(string)($row['code']??'');
      $qTxt=(string)($row['question']??'');
      $res =(string)($row['result']??'');
      $pri =(string)($row['priority']??'');
      $note=(string)($row['note']??'');
      $images = is_array($row['images'] ?? null) ? $row['images'] : [];

      // Main row
      $xRow=$pdf->GetX(); $yRow=$pdf->GetY();

      // code
      $pdf->Rect($xRow,$yRow,$w[0],6.0);
      $pdf->Cell($w[0],6.0,pdf_text($code),0,0,'L');

      // question (wrap)
      $pdf->SetXY($xRow+$w[0],$yRow);
      $pdf->Rect($xRow+$w[0],$yRow,$w[1],6.0);
      $pdf->MultiCell($w[1],6.0,pdf_text($qTxt),0,'L');
      $qLines=pdf_nb_lines($pdf,$w[1],$qTxt);
      $rowH=max(6.0,6.0*$qLines);

      // result badge
      $pdf->SetXY($xRow+$w[0]+$w[1],$yRow);
      $pdf->Rect($xRow+$w[0]+$w[1],$yRow,$w[2],$rowH);
      $pdf->SetFont('Arial','B',10);
      $pdf->SetXY($xRow+$w[0]+$w[1]+1.0,$yRow+1.0);
      pdf_badge($pdf, strtoupper($res ?: 'N/A'), pdf_result_colour($res), $w[2]-2.0, 6.0);

      // priority badge
      $pdf->SetXY($xRow+$w[0]+$w[1]+$w[2],$yRow);
      $pdf->Rect($xRow+$w[0]+$w[1]+$w[2],$yRow,$w[3],$rowH);
      $pdf->SetXY($xRow+$w[0]+$w[1]+$w[2]+1.0,$yRow+1.0);
      pdf_badge($pdf, ucfirst($pri ?: '—'), pdf_priority_colour($pri), $w[3]-2.0, 6.0);
      $pdf->SetFont('Arial','',11);

      // notes (wrap)
      $pdf->SetXY($xRow+$w[0]+$w[1]+$w[2]+$w[3],$yRow);
      $pdf->Rect($xRow+$w[0]+$w[1]+$w[2]+$w[3],$yRow,$w[4],$rowH);
      if ($note!=='') $pdf->MultiCell($w[4],6.0,pdf_text($note),0,'L'); else $pdf->Cell($w[4],6.0,'',0,0,'L');

      $pdf->SetXY($xRow,$yRow+$rowH);

      // Thumbnails under the row (if any)
      if ($images) {
        $thumbW=32; $thumbH=24; $gap=3;

        // Thumbs start under the Question column, spanning remaining width
        $xStart = $xRow + $w[0];
        $usable = $w[1] + $w[2] + $w[3] + $w[4];
        $perRow = max(1, (int)floor(($usable + $gap) / ($thumbW + $gap)));

        $guard($pdf, $thumbH+8);
        $yThumb = $pdf->GetY()+2;

        $i=0;
        foreach ($images as $rel) {
          $abs = __DIR__ . '/../' . ltrim((string)$rel,'/');
          if (!is_file($abs)) continue;
          $col = $i % $perRow;
          if ($col===0 && $i>0) {
            $yThumb += $thumbH + $gap;
            $guard($pdf, $thumbH+8);
          }
          $x = $xStart + $col * ($thumbW + $gap);
          $pdf->Image($abs,$x,$yThumb,$thumbW,$thumbH);
          $pdf->Rect($x,$yThumb,$thumbW,$thumbH);
          $i++;
        }
        $pdf->SetY($yThumb + $thumbH + 3);
      }
    }

    $pdf->Ln(2.5);
  }

  // ---- Other photos (tour-level)
  $photos = json_arr($tour['photos'] ?? null);
  if ($photos) {
    $section($pdf,'Other Photos');
    $thumbW=40; $thumbH=30; $gap=4; $cols=4;
    $xStart=$pdf->GetX(); $y=$pdf->GetY(); $i=0;
    foreach ($photos as $rel) {
      $abs = __DIR__ . '/../' . ltrim((string)$rel,'/');
      if (!is_file($abs)) continue;
      $guard($pdf,$thumbH+12);
      $col = $i % $cols; $x=$xStart + $col*($thumbW+$gap);
      if ($col===0 && $i>0) $y += $thumbH + $gap;
      $pdf->Image($abs,$x,$y,$thumbW,$thumbH); $pdf->Rect($x,$y,$thumbW,$thumbH); $i++;
    }
    $pdf->SetXY($xStart,$y+$thumbH+2);
  }

  // ---- Signature
  if (!empty($tour['signature_path'])) {
    $absSig = __DIR__ . '/../' . ltrim((string)$tour['signature_path'],'/');
    if (is_file($absSig)) {
      $section($pdf,'Signature');
      $guard($pdf,40);
      $y=$pdf->GetY();
      $pdf->Image($absSig,12,$y,60);
      $pdf->Ln(34);
    }
  }

  // ---- Output
  $pdf->SetTitle(pdf_text('Safety Tour — '.($tour['site']??'')));
  $pdf->Output('F',$outPath);
}