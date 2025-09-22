<?php
// login_admin.php — login screen for both user/admin
require_once __DIR__ . '/includes/auth.php';

$err = '';
$next = $_GET['next'] ?? '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  if ($email === '' || $pass === '') {
    $err = 'Enter your email and password.';
  } elseif (auth_login($email, $pass)) {
    header('Location: ' . $next); exit;
  } else {
    $err = 'Invalid login.';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Sign in</title>
<style>
:root{--bg:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#1f2937;--radius:16px}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:16px system-ui,Segoe UI,Roboto}
.wrap{max-width:380px;margin:0 auto;padding:24px}
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-top:18px}
label{display:block;margin:8px 0 6px;color:var(--muted)}
input{width:100%;padding:12px;border-radius:12px;border:1px solid var(--border);background:#0b1220;color:#e5e7eb}
.btn{margin-top:12px;width:100%;padding:12px;border-radius:12px;border:0;cursor:pointer;
     background:linear-gradient(180deg,#0ea5e9,#0284c7);color:#00131a;font-weight:800}
.err{margin:10px 0;padding:10px;border:1px solid #7f1d1d;background:#3b0a0a1a;border-radius:12px;color:#fecaca}
.muted{color:var(--muted);font-size:.9rem}
</style>
</head>
<body>
<div class="wrap">
  <h1>Sign in</h1>
  <form class="card" method="post" action="login_admin.php?next=<?= htmlspecialchars($next) ?>">
    <?php if ($err): ?><div class="err"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <label>Email</label>
    <input name="email" type="email" required>
    <label>Password</label>
    <input name="password" type="password" required>
    <button class="btn" type="submit">Sign in</button>
  </form>
</div>
</body>
</html>
