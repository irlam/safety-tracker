<?php
// /upload_diag.php — self-contained upload tester/inspector
// Drop in the web root. Visit: https://<your-domain>/upload_diag.php

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Try to use your app’s helpers if present
$haveFns = false;
$uploadBase = __DIR__ . '/uploads';
if (is_file(__DIR__ . '/includes/functions.php')) {
    require_once __DIR__ . '/includes/functions.php';
    if (function_exists('save_file')) $haveFns = true;
    // If your config defines a base uploads dir, honour it
    if (defined('UPLOAD_DIR')) $uploadBase = UPLOAD_DIR;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
function iniBytes($val) {
    $val = trim((string)$val);
    $last = strtolower(substr($val, -1));
    $num = (float)$val;
    return match($last){
        'g' => (int)($num * 1024 * 1024 * 1024),
        'm' => (int)($num * 1024 * 1024),
        'k' => (int)($num * 1024),
        default => (int)$num
    };
}

$info = [
    'PHP Version'         => PHP_VERSION,
    'SAPI'                => PHP_SAPI,
    'file_uploads'        => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size'       => ini_get('post_max_size'),
    'max_file_uploads'    => ini_get('max_file_uploads'),
    'memory_limit'        => ini_get('memory_limit'),
    'sys_temp_dir'        => sys_get_temp_dir(),
    'sys_temp_writable'   => is_writable(sys_get_temp_dir()) ? 'yes' : 'no',
];

// ensure subfolders we actually use
$folders = [
    'uploads base' => $uploadBase,
    'tours'        => rtrim($uploadBase, '/').'/tours',
    'signatures'   => rtrim($uploadBase, '/').'/signatures',
];

// Attempt to create folders (best effort)
foreach ($folders as $label => $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

$result = null; $err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['testfile']['name'])) {
        $file = $_FILES['testfile'];

        // Prefer your app’s save_file() to mimic production behaviour
        if (function_exists('save_file')) {
            $rel = save_file($file, 'tours');
            if ($rel) {
                $result = [
                    'saved'        => true,
                    'relativePath' => $rel,
                    'absolutePath' => realpath(__DIR__ . '/' . ltrim($rel, '/')),
                ];
            } else {
                $err = 'save_file() returned null (move failed or not an uploaded file). Check folder perms and error log.';
            }
        } else {
            // Fallback bare move
            $ext  = strtolower(pathinfo($file['name'] ?? 'bin', PATHINFO_EXTENSION) ?: 'bin');
            $name = uniqid('diag_', true) . '.' . $ext;
            $dest = rtrim($folders['tours'], '/').'/'.$name;

            if (!is_uploaded_file($file['tmp_name'] ?? '')) {
                $err = 'is_uploaded_file() is false — PHP did not receive the upload (usually post_max_size / upload_max_filesize / 413).';
            } else {
                if (@move_uploaded_file($file['tmp_name'], $dest)) {
                    $result = [
                        'saved'        => true,
                        'relativePath' => str_replace(__DIR__ . '/', '', $dest),
                        'absolutePath' => realpath($dest),
                    ];
                } else {
                    $err = 'move_uploaded_file() failed — check permissions on '.$folders['tours'];
                }
            }
        }

        // Append detailed context
        if ($err) {
            $codeMap = [
                UPLOAD_ERR_OK         => 'OK',
                UPLOAD_ERR_INI_SIZE   => 'UPLOAD_ERR_INI_SIZE (over upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE  => 'UPLOAD_ERR_FORM_SIZE (over HTML MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL    => 'UPLOAD_ERR_PARTIAL',
                UPLOAD_ERR_NO_FILE    => 'UPLOAD_ERR_NO_FILE',
                UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
                UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
                UPLOAD_ERR_EXTENSION  => 'UPLOAD_ERR_EXTENSION',
            ];
            $err .= ' | $_FILES error: '.($codeMap[$file['error']] ?? $file['error']);
        }
    } else {
        $err = 'No file selected.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Upload Diagnostics</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{--bg:#0b1220;--card:#111827;--border:#1f2937;--text:#e5e7eb;--muted:#94a3b8;--ok:#16a34a;--bad:#b91c1c}
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:15px/1.6 system-ui,Segoe UI,Roboto}
  .wrap{max-width:920px;margin:0 auto;padding:18px}
  h1{margin:0 0 10px;font-size:1.3rem}
  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px;margin:12px 0}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--border);padding:8px;vertical-align:top}
  th{background:#0f172a;text-align:left}
  code,pre{color:#e2e8f0}
  .ok{color:var(--ok)} .bad{color:var(--bad)}
  .muted{color:var(--muted)}
  input[type=file]{width:100%;padding:10px;border:1px solid var(--border);border-radius:10px;background:#0b1220;color:#e5e7eb}
  button{padding:10px 14px;border:0;border-radius:10px;background:#0ea5e9;color:#00131a;font-weight:800;cursor:pointer}
  .pill{display:inline-block;padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#0b1220}
</style>
</head>
<body>
<div class="wrap">
  <h1>Upload Diagnostics</h1>

  <div class="card">
    <h3>PHP & limits</h3>
    <table>
      <tr><th>Setting</th><th>Value</th><th>Bytes</th></tr>
      <?php foreach ($info as $k=>$v): ?>
        <tr>
          <td><?= h($k) ?></td>
          <td><?= h(is_bool($v) ? ($v?'1':'0') : (string)$v) ?></td>
          <td><?= in_array($k, ['upload_max_filesize','post_max_size','memory_limit'], true) ? number_format(iniBytes($v)) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="card">
    <h3>Upload directories</h3>
    <table>
      <tr><th>Folder</th><th>Path</th><th>Exists</th><th>Writable</th></tr>
      <?php foreach ($folders as $label=>$dir): ?>
        <tr>
          <td><?= h($label) ?></td>
          <td><code><?= h($dir) ?></code></td>
          <td><?= is_dir($dir) ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?></td>
          <td><?= is_writable($dir) ? '<span class="ok">yes</span>' : '<span class="bad">no</span>' ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <form class="card" method="post" enctype="multipart/form-data">
    <h3>Try a real upload</h3>
    <p class="muted">Pick a small JPG/PNG. This uses your app’s <code>save_file()</code> if available (folder: <code>/uploads/tours</code>).</p>
    <input type="file" name="testfile" accept="image/*">
    <div style="margin-top:10px"><button type="submit">Upload test image</button></div>
  </form>

  <?php if ($result): ?>
    <div class="card">
      <h3>Result</h3>
      <p class="ok">Saved successfully.</p>
      <p>Relative: <code><?= h($result['relativePath']) ?></code></p>
      <p>Absolute: <code><?= h((string)$result['absolutePath']) ?></code></p>
      <?php
        $web = '/' . ltrim($result['relativePath'], '/');
        echo '<p><a class="pill" href="'.h($web).'" target="_blank">Open image</a></p>';
      ?>
    </div>
  <?php elseif ($err): ?>
    <div class="card">
      <h3>Result</h3>
      <p class="bad"><?= h($err) ?></p>
      <details style="margin-top:8px"><summary class="pill">$_FILES dump</summary><pre><?php print_r($_FILES); ?></pre></details>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
