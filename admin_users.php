<?php
// admin_users.php — manage users & admins
require_once __DIR__ . '/includes/auth.php';
auth_check(true); // admin only
$pdo = db();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $act = $_POST['act'] ?? '';
  try {
    if ($act === 'create') {
      $email = trim($_POST['email'] ?? '');
      $name  = trim($_POST['name'] ?? '');
      $role  = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
      $pass  = (string)($_POST['password'] ?? '');
      if ($email === '' || $pass === '') throw new Exception('Email and password are required.');
      $st = $pdo->prepare("INSERT INTO safety_users (email,name,role,pass_hash) VALUES (?,?,?,?)");
      $st->execute([$email,$name,$role,password_hash($pass,PASSWORD_DEFAULT)]);
      $msg = 'User created.';
    }
    elseif ($act === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $email = trim($_POST['email'] ?? '');
      $name  = trim($_POST['name'] ?? '');
      $role  = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
      if ($id <= 0 || $email === '') throw new Exception('Missing fields.');
      $pdo->prepare("UPDATE safety_users SET email=?, name=?, role=? WHERE id=?")->execute([$email,$name,$role,$id]);
      if (!empty($_POST['password'])) {
        $pdo->prepare("UPDATE safety_users SET pass_hash=? WHERE id=?")->execute([password_hash((string)$_POST['password'],PASSWORD_DEFAULT),$id]);
      }
      $msg = 'User updated.';
    }
    elseif ($act === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) throw new Exception('Invalid id.');
      // prevent deleting last admin
      $admins = (int)$pdo->query("SELECT COUNT(*) FROM safety_users WHERE role='admin'")->fetchColumn();
      $isAdminRow = (int)$pdo->query("SELECT COUNT(*) FROM safety_users WHERE id={$id} AND role='admin'")->fetchColumn();
      if ($isAdminRow && $admins <= 1) throw new Exception('Cannot delete the last admin.');
      $pdo->prepare("DELETE FROM safety_users WHERE id=?")->execute([$id]);
      $msg = 'User deleted.';
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$users = [];
try {
  $users = $pdo->query("SELECT id,email,name,role,created_at,updated_at FROM safety_users ORDER BY role DESC, id DESC")->fetchAll();
} catch (Throwable $e) {}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — Users</title>
<style>
:root{--bg:#0b1220;--card:#0f172a;--text:#e5e7eb;--muted:#94a3b8;--border:#1f2937;--radius:18px;--g1:#0ea5e9;--g2:#0284c7}
*{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font:16px system-ui,Segoe UI,Roboto}
.wrap{max-width:1100px;margin:0 auto;padding:18px}
.nav{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-bottom:14px}
.navbtns{display:flex;gap:14px}
.navbtn{padding:10px 16px;border-radius:14px;border:1px solid var(--border);
        background:linear-gradient(180deg, rgba(14,165,233,.25), rgba(2,132,199,.12));color:#dbeafe;font-weight:800;text-decoration:none}
.card{background:linear-gradient(180deg, rgba(255,255,255,.03), rgba(255,255,255,.015));border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin:12px 0}
h1{font-size:1.4rem;margin:0 0 10px}
.grid{display:grid;grid-template-columns:1fr 1fr 1fr 120px 160px;gap:8px}
@media (max-width:980px){.grid{grid-template-columns:1fr}}
label{display:block;margin:4px 0;color:var(--muted)}
input,select{width:100%;padding:10px;border-radius:12px;border:1px solid var(--border);background:#0b1220;color:#e5e7eb}
.btn{padding:10px 14px;border-radius:12px;border:0;cursor:pointer;background:linear-gradient(180deg,var(--g1),var(--g2));color:#00131a;font-weight:800}
.btn-ghost{padding:10px 14px;border-radius:12px;border:1px solid var(--border);background:#0b1220;color:#e5e7eb}
table{width:100%;border-collapse:collapse;margin-top:8px}
th,td{border:1px solid var(--border);padding:10px}
th{background:#0f172a;text-align:left}
.toast{padding:10px;border-radius:10px;margin:10px 0}
.ok{background:#0e1f15;border:1px solid #1d3e2b;color:#eaffec}
.err{background:#201011;border:1px solid #402020;color:#fff0f0}
</style>
</head>
<body>
<div class="wrap">
  <nav class="nav">
    <div style="display:flex;align-items:center;gap:10px">
      <img src="/assets/img/logo.png" alt="" style="height:30px" onerror="this.style.display='none'">
      <strong>Admin — Users</strong>
    </div>
    <div class="navbtns">
      <a class="navbtn" href="/dashboard.php">Dashboard</a>
      <a class="navbtn" href="/actions.php">Actions</a>
      <a class="navbtn" href="/logout.php">Logout</a>
    </div>
  </nav>

  <h1>Manage users</h1>

  <?php if ($msg): ?><div class="toast ok"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="toast err"><?= htmlspecialchars($err) ?></div><?php endif; ?>

  <div class="card">
    <form method="post">
      <input type="hidden" name="act" value="create">
      <div class="grid">
        <div><label>Name</label><input name="name" placeholder="Full name"></div>
        <div><label>Email</label><input name="email" type="email" placeholder="name@company.com" required></div>
        <div>
          <label>Role</label>
          <select name="role">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div><label>Password</label><input name="password" type="password" placeholder="Set password" required></div>
        <div style="display:flex;align-items:end"><button class="btn" type="submit">+ Add user</button></div>
      </div>
    </form>
  </div>

  <div class="card">
    <table>
      <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Set new password</th><th>Actions</th></tr>
      <?php if (!$users): ?>
        <tr><td colspan="6" style="color:#94a3b8">No users yet.</td></tr>
      <?php else: foreach ($users as $u): ?>
        <tr>
          <form method="post">
            <input type="hidden" name="act" value="update">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <td><?= (int)$u['id'] ?></td>
            <td><input name="name" value="<?= htmlspecialchars($u['name'] ?? '') ?>"></td>
            <td><input name="email" type="email" value="<?= htmlspecialchars($u['email']) ?>"></td>
            <td>
              <select name="role">
                <option value="user"  <?= $u['role']==='user'  ? 'selected':'' ?>>User</option>
                <option value="admin" <?= $u['role']==='admin' ? 'selected':'' ?>>Admin</option>
              </select>
            </td>
            <td><input name="password" type="password" placeholder="Leave blank to keep"></td>
            <td style="display:flex;gap:8px">
              <button class="btn" type="submit">Save</button>
          </form>
              <form method="post" onsubmit="return confirm('Delete this user?')">
                <input type="hidden" name="act" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn-ghost" type="submit">Delete</button>
              </form>
            </td>
        </tr>
      <?php endforeach; endif; ?>
    </table>
  </div>
</div>
</body>
</html>
