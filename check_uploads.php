<?php
declare(strict_types=1);

// Adjust if you keep /includes/config.php
$config = __DIR__ . '/includes/config.php';
if (file_exists($config)) require_once $config;

// Resolve uploads dir (prefer constant from config)
$UPLOAD_DIR = defined('UPLOAD_DIR')
  ? UPLOAD_DIR
  : rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/').'/uploads';

$subdirs = ['tours','signatures','action_closeouts'];

function ensure_dir(string $path): array {
  $made = false; $err = null;
  if (!is_dir($path)) { @mkdir($path, 0775, true); $made = is_dir($path); }
  if (!is_writable($path)) { @chmod($path, 0775); }
  if (!is_writable($path)) { $err = 'not writable'; }
  return ['exists'=>is_dir($path), 'made'=>$made, 'writable'=>is_writable($path), 'err'=>$err];
}

$root = ensure_dir($UPLOAD_DIR);
$children = [];
foreach ($subdirs as $s) {
  $children[$s] = ensure_dir($UPLOAD_DIR.'/'.$s);
}

// write a tiny test file
$testFile = $UPLOAD_DIR.'/health-'.bin2hex(random_bytes(3)).'.txt';
$writeOk = @file_put_contents($testFile, 'ok '.date('c')) !== false;
if ($writeOk) @unlink($testFile);

// Render
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Uploads check</title>
<style>
  body{background:#0b1220;color:#e5e7eb;font:15px/1.6 system-ui;margin:0;padding:18px}
  code{background:#0f172a;border:1px solid #1f2937;border-radius:8px;padding:2px 6px}
  .ok{color:#22c55e}.bad{color:#ef4444}.warn{color:#f59e0b}
  .card{border:1px solid #1f2937;background:#0f172a;border-radius:14px;padding:12px;margin:10px 0}
</style>
<h1>Uploads check</h1>
<div class="card">
  <div>Base uploads dir: <code><?=htmlspecialchars($UPLOAD_DIR)?></code></div>
  <div>Status:
    <?php if($root['exists'] && $root['writable']): ?>
      <span class="ok">OK</span>
    <?php else: ?>
      <span class="bad">Problem</span>
    <?php endif; ?>
  </div>
  <ul>
    <li>exists: <?= $root['exists'] ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?></li>
    <li>writable: <?= $root['writable'] ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?></li>
    <?php if($root['made']) echo '<li class="warn">created just now</li>'; ?>
    <?php if($root['err']) echo '<li class="bad">'.htmlspecialchars($root['err']).'</li>'; ?>
  </ul>
</div>
<div class="card">
  <strong>Subfolders</strong>
  <ul>
    <?php foreach($children as $name=>$st): ?>
      <li>
        <code><?=htmlspecialchars($name)?></code> —
        exists: <?= $st['exists'] ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?>,
        writable: <?= $st['writable'] ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?>
        <?php if($st['made']) echo ' <span class="warn">(created)</span>'; ?>
        <?php if($st['err']) echo ' <span class="bad">'.htmlspecialchars($st['err']).'</span>'; ?>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<div class="card">
  Test write: <?= $writeOk ? '<span class="ok">success</span>' : '<span class="bad">failed</span>' ?>
</div>
<p style="color:#94a3b8">If writable is still “no”, set perms in Plesk (Files → uploads → Permissions → grant write to the PHP user) or run <code>chmod -R 775 uploads</code>.</p>
