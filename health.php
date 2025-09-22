<?php
// health.php — quick sanity check for safety.defecttracker.uk
declare(strict_types=1);

// ---- config-ish helpers (all optional) ----
$root = __DIR__;
$includes = $root . '/includes/functions.php';
$hasIncludes = file_exists($includes);
if ($hasIncludes) require_once $includes;

// Attempt to pull SMTP/UPLOAD constants if defined by your config
$smtpHost = defined('SMTP_HOST') ? SMTP_HOST : (getenv('SMTP_HOST') ?: 'mxe97d.netcup.net');
$smtpPort = defined('SMTP_PORT') ? (int)SMTP_PORT : (int)(getenv('SMTP_PORT') ?: 465);
$uploadDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : ($root . '/uploads');
$tz = defined('TIMEZONE') ? TIMEZONE : 'Europe/London';
date_default_timezone_set($tz);

// ---- checks ----
$checks = [
  'php' => [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'extensions' => get_loaded_extensions(),
  ],
  'timezone' => [
    'timezone' => date_default_timezone_get(),
    'now_local' => date('Y-m-d H:i:s'),
    'now_utc' => gmdate('Y-m-d H:i:s') . ' UTC',
  ],
  'session' => [
    'status' => session_status(),
  ],
  'paths' => [
    'doc_root' => $root,
    'uploads_dir' => $uploadDir,
  ],
];

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['health_ping'] = ($_SESSION['health_ping'] ?? 0) + 1;
$checks['session']['counter'] = $_SESSION['health_ping'];

// uploads dir write test
$checks['uploads'] = [
  'exists' => is_dir($uploadDir),
  'writable' => is_dir($uploadDir) ? is_writable($uploadDir) : false,
  'test_file' => null,
  'error' => null,
];
if (!is_dir($uploadDir)) {
  @mkdir($uploadDir, 0775, true);
}
if (is_dir($uploadDir) && is_writable($uploadDir)) {
  $testFile = rtrim($uploadDir,'/').'/health-'.bin2hex(random_bytes(3)).'.txt';
  $ok = @file_put_contents($testFile, "ok ".date('c'));
  if ($ok !== false) {
    $checks['uploads']['test_file'] = str_replace($root.'/', '', $testFile);
    // Clean up to avoid littering
    @unlink($testFile);
  } else {
    $checks['uploads']['error'] = 'file_put_contents failed';
  }
}

// DB check (only if includes/functions.php exists with db())
$checks['database'] = [
  'present' => $hasIncludes,
  'connected' => false,
  'server_version' => null,
  'error' => null,
];
if ($hasIncludes && function_exists('db')) {
  try {
    $pdo = db();
    $checks['database']['connected'] = true;
    $checks['database']['server_version'] = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
  } catch (Throwable $e) {
    $checks['database']['error'] = $e->getMessage();
  }
}

// SMTP TCP check (no auth; just open socket)
$checks['smtp'] = [
  'host' => $smtpHost,
  'port' => $smtpPort,
  'connected' => false,
  'greeting' => null,
  'error' => null,
];
$errno = 0; $errstr = '';
$timeout = 5;
$scheme = ($smtpPort === 465) ? 'ssl://' : '';
$fp = @stream_socket_client($scheme.$smtpHost.':'.$smtpPort, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
if ($fp) {
  stream_set_timeout($fp, 5);
  $greeting = fgets($fp, 512);
  $checks['smtp']['connected'] = true;
  $checks['smtp']['greeting'] = trim((string)$greeting);
  fclose($fp);
} else {
  $checks['smtp']['error'] = "[$errno] $errstr";
}

// Optional JSON mode
if (isset($_GET['format']) && $_GET['format'] === 'json') {
  header('Content-Type: application/json');
  echo json_encode([
    'ok' => true,
    'summary' => [
      'php_ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
      'uploads_ok' => $checks['uploads']['writable'],
      'db_ok' => $checks['database']['present'] && $checks['database']['connected'],
      'smtp_ok' => $checks['smtp']['connected'],
    ],
    'checks' => $checks,
  ], JSON_PRETTY_PRINT);
  exit;
}

// ---- HTML (tiny, dark, mobile-first) ----
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Health Check</title>
<style>
  :root{--bg:#0b1220;--card:#0f172a;--muted:#94a3b8;--text:#e5e7eb;--border:#1f2937;--radius:16px;--ok:#16a34a;--bad:#ef4444;--warn:#f59e0b}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 system-ui,-apple-system,Segoe UI,Roboto}
  .wrap{max-width:900px;margin:0 auto;padding:18px}
  h1{font-size:1.2rem;margin:0 0 10px}
  .grid{display:grid;gap:12px;grid-template-columns:1fr 1fr} @media (max-width:800px){.grid{grid-template-columns:1fr}}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px}
  .muted{color:var(--muted)} .ok{color:var(--ok)} .bad{color:var(--bad)} .warn{color:var(--warn)}
  code{background:#0b1220;border:1px solid var(--border);border-radius:10px;padding:2px 6px}
  .pill{display:inline-block;border:1px solid var(--border);border-radius:999px;padding:6px 10px;background:#0b1220}
  a{color:#93c5fd;text-decoration:none}
  ul{margin:6px 0 0 18px}
</style>
</head>
<body>
<div class="wrap">
  <h1>Health Check</h1>
  <div class="muted">Local time: <?= htmlspecialchars($checks['timezone']['now_local']) ?> (<?= htmlspecialchars($checks['timezone']['timezone']) ?>)</div>
  <div class="muted">UTC: <?= htmlspecialchars($checks['timezone']['now_utc']) ?></div>
  <p><a class="pill" href="?format=json">JSON</a></p>

  <div class="grid">
    <div class="card">
      <strong>PHP</strong><br>
      Version: <code><?= htmlspecialchars($checks['php']['version']) ?></code><br>
      SAPI: <code><?= htmlspecialchars($checks['php']['sapi']) ?></code><br>
      Memory: <code><?= htmlspecialchars($checks['php']['memory_limit']) ?></code><br>
      Max Exec: <code><?= htmlspecialchars($checks['php']['max_execution_time']) ?></code><br>
      <div class="muted" style="margin-top:6px">Extensions (first 10):</div>
      <ul>
        <?php foreach (array_slice($checks['php']['extensions'], 0, 10) as $ext): ?>
          <li><?= htmlspecialchars($ext) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <strong>Uploads</strong><br>
      Directory: <code><?= htmlspecialchars(str_replace($root.'/', '', $checks['paths']['uploads_dir'])) ?></code><br>
      Exists:
