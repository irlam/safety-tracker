<?php
// /action_close.php — close or reopen an action, optional email to tour recipients
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';       // safe if present
require_once __DIR__ . '/includes/functions.php';

date_default_timezone_set('Europe/London');

// Optional auth gate (uncomment if you want to restrict to logged-in users)
// if (function_exists('auth_check')) auth_check(true);

$pdo  = db();
$back = trim((string)($_REQUEST['back'] ?? 'actions.php'));
if ($back === '') $back = 'actions.php';

// tiny helpers
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_admin_safe(): bool {
  if (function_exists('auth_is_admin')) return (bool)auth_is_admin();
  if (function_exists('is_admin'))      return (bool)is_admin();
  return true; // if no auth system, allow
}
function actor_email(): ?string {
  if (function_exists('auth_user')) { $u = auth_user(); return $u['email'] ?? null; }
  return null;
}

/* -------------------------------------------
 * REOPEN (GET /action_close.php?id=..&reopen=1)
 * -----------------------------------------*/
if (isset($_GET['reopen'])) {
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) { header('Location: '.$back); exit; }
  if (!is_admin_safe()) { http_response_code(403); echo 'Forbidden'; exit; }

  try {
    $st = $pdo->prepare("UPDATE safety_actions SET status='Open', closed_by=NULL, closed_at=NULL WHERE id=?");
    $st->execute([$id]);

    // audit (best effort)
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS safety_audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        event ENUM('create','update','close_action','reopen_action','delete_tour') NOT NULL,
        tour_id INT NULL, action_id INT NULL,
        actor VARCHAR(120) NULL, action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NULL, entity_id INT NULL,
        details TEXT NULL, ip VARCHAR(64) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $ins = $pdo->prepare("INSERT INTO safety_audit_log (event, action_id, actor, action, entity_type, entity_id, ip, details)
                            VALUES ('reopen_action',?,?,?,?,?, ?,?)");
      $ins->execute([$id, actor_email(), 'reopen', 'action', $id, $_SERVER['REMOTE_ADDR'] ?? null, null]);
    } catch (Throwable $e) { /* ignore */ }

  } catch (Throwable $e) {
    error_log('REOPEN ERR: '.$e->getMessage());
  }
  header('Location: '.$back);
  exit;
}

/* -------------------------------------------
 * CLOSE (POST from action_view.php)
 * -----------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!is_admin_safe()) { http_response_code(403); echo 'Forbidden'; exit; }

  $id           = (int)($_POST['id'] ?? 0);
  $close_note   = trim((string)($_POST['close_note'] ?? ''));
  $email_opt_in = isset($_POST['email_recipients']) && $_POST['email_recipients'] == '1';

  if ($id <= 0) { header('Location: '.$back); exit; }

  // fetch action + tour (recipients, site, etc.)
  $action = null; $tour = null;
  try {
    $st = $pdo->prepare("SELECT * FROM safety_actions WHERE id=?");
    $st->execute([$id]);
    $action = $st->fetch();

    if ($action) {
      $st2 = $pdo->prepare("SELECT id, site, area, lead_name, recipients FROM safety_tours WHERE id=?");
      $st2->execute([(int)$action['tour_id']]);
      $tour = $st2->fetch();
    }
  } catch (Throwable $e) {
    error_log('FETCH ERR: '.$e->getMessage());
  }

  // collect closure photos
  $newPhotos = [];
  if (!empty($_FILES['close_photos']['name'][0])) {
    foreach ($_FILES['close_photos']['name'] as $i => $nm) {
      $tmp = [
        'name'     => $_FILES['close_photos']['name'][$i] ?? '',
        'type'     => $_FILES['close_photos']['type'][$i] ?? '',
        'tmp_name' => $_FILES['close_photos']['tmp_name'][$i] ?? '',
        'error'    => $_FILES['close_photos']['error'][$i] ?? 4,
        'size'     => $_FILES['close_photos']['size'][$i] ?? 0,
      ];
      $rel = save_file($tmp, 'closures');
      if ($rel) $newPhotos[] = $rel;
    }
  }
  // merge with existing close_photos (JSON)
  $allPhotos = $newPhotos;
  try {
    if (!empty($action['close_photos'])) {
      $old = json_decode((string)$action['close_photos'], true);
      if (is_array($old) && $old) $allPhotos = array_values(array_merge($old, $newPhotos));
    }
  } catch (Throwable $e) {}

  // update to Closed
  try {
    $st = $pdo->prepare("UPDATE safety_actions
      SET status='Closed',
          close_note=:note,
          close_photos=:photos,
          closed_by=:by,
          closed_at=NOW()
      WHERE id=:id");
    $st->execute([
      ':note'   => $close_note !== '' ? $close_note : null,
      ':photos' => $allPhotos ? json_encode($allPhotos, JSON_UNESCAPED_UNICODE) : null,
      ':by'     => actor_email(),
      ':id'     => $id,
    ]);

    // audit (best effort)
    try {
      $pdo->exec("CREATE TABLE IF NOT EXISTS safety_audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        event ENUM('create','update','close_action','reopen_action','delete_tour') NOT NULL,
        tour_id INT NULL, action_id INT NULL,
        actor VARCHAR(120) NULL, action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NULL, entity_id INT NULL,
        details TEXT NULL, ip VARCHAR(64) NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
      $ins = $pdo->prepare("INSERT INTO safety_audit_log (event, tour_id, action_id, actor, action, entity_type, entity_id, ip, details)
                            VALUES ('close_action',?,?,?,?,?, ?,?,?)");
      $ins->execute([
        (int)($action['tour_id'] ?? 0),
        $id,
        actor_email(),
        'close',
        'action',
        $id,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $close_note ?: null
      ]);
    } catch (Throwable $e) { /* ignore */ }

  } catch (Throwable $e) {
    error_log('CLOSE ERR: '.$e->getMessage());
    header('Location: '.$back);
    exit;
  }

  /* -------------------------------------------
   * Optional email to tour recipients
   * -----------------------------------------*/
  if ($email_opt_in && $tour) {
    $listRaw = (string)($tour['recipients'] ?? '');
    // split by comma/semicolon/newline
    $addresses = array_values(array_filter(array_map(
      fn($x)=>trim(strtolower($x)),
      preg_split('/[,\;\n]+/', $listRaw) ?: []
    )));
    // De-dupe basic
    $addresses = array_values(array_unique($addresses));

    if ($addresses) {
      // compose email
      $siteArea = trim(($tour['site'] ?? '').(empty($tour['area']) ? '' : ' / '.$tour['area']));
      $subject  = 'Action closed — Tour #'.(int)$tour['id'].' ('.$siteArea.')';
      $linkView = (isset($_SERVER['HTTP_HOST']) ? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.$_SERVER['HTTP_HOST']) : '').'/action_view.php?id='.$id;
      $who      = actor_email() ?: 'System';
      $bodyHtml = '<p>Hello,</p>'
        .'<p>An action from Safety Tour <strong>#'.(int)$tour['id'].'</strong> ('.h($siteArea).') has been <strong>closed</strong>.</p>'
        .'<p><strong>Action:</strong> '.h((string)($action['action'] ?? '')).'<br>'
        .'<strong>Responsible:</strong> '.h((string)($action['responsible'] ?? '')).'<br>'
        .'<strong>Due:</strong> '.h((string)($action['due_date'] ?? '')).'</p>'
        .($close_note !== '' ? '<p><strong>Closure note:</strong><br>'.nl2br(h($close_note)).'</p>' : '')
        .'<p>Closed by: '.h($who).' at '.h(date('d/m/Y H:i')).'</p>'
        .'<p><a href="'.h($linkView).'">View action</a></p>';

      // send one by one (PHPMailer will be set up in send_mail)
      foreach ($addresses as $to) {
        // minimal email sanity
        if (!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $to)) continue;
        try { send_mail($to, $subject, $bodyHtml, []); } catch (Throwable $e) { /* ignore */ }
      }
    }
  }

  header('Location: '.$back);
  exit;
}

// Fallback: if someone GETs here without params, bounce back.
header('Location: '.$back);
