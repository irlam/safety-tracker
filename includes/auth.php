<?php
// includes/auth.php — simple session auth with roles (user/admin)
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';


function auth_bootstrap_admin(): void {
  // Never seed a known administrator password from public source code.
  // Existing users are unaffected. New installations must supply private,
  // per-installation credentials or create an admin through secure setup.
  $email = trim((string) (defined('SAFETY_BOOTSTRAP_ADMIN_EMAIL')
    ? SAFETY_BOOTSTRAP_ADMIN_EMAIL
    : (getenv('SAFETY_BOOTSTRAP_ADMIN_EMAIL') ?: '')));
  $password = (string) (defined('SAFETY_BOOTSTRAP_ADMIN_PASSWORD')
    ? SAFETY_BOOTSTRAP_ADMIN_PASSWORD
    : (getenv('SAFETY_BOOTSTRAP_ADMIN_PASSWORD') ?: ''));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 16) {
    return;
  }

  $pdo = db();
  try {
    // table may not exist yet; ignore errors
    $pdo->query("CREATE TABLE IF NOT EXISTS safety_users (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      email VARCHAR(190) NOT NULL UNIQUE,
      name  VARCHAR(120) DEFAULT NULL,
      role  ENUM('user','admin') NOT NULL DEFAULT 'user',
      pass_hash VARCHAR(255) NOT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  } catch (Throwable $e) {}

  try {
    $c = (int)$pdo->query("SELECT COUNT(*) FROM safety_users")->fetchColumn();
    if ($c === 0) {
      $st = $pdo->prepare("INSERT INTO safety_users (email,name,role,pass_hash) VALUES (?,?,?,?)");
      $st->execute([
        $email,
        'Admin',
        'admin',
        password_hash($password, PASSWORD_DEFAULT)
      ]);
    }
  } catch (Throwable $e) {}
}
auth_bootstrap_admin();

function auth_user(): ?array {
  return $_SESSION['auth'] ?? null;
}
function is_admin(): bool {
  return isset($_SESSION['auth']['role']) && $_SESSION['auth']['role'] === 'admin';
}
function auth_check(bool $requireAdmin = false): void {
  if (!auth_user()) {
    header('Location: /login_admin.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/')); exit;
  }
  if ($requireAdmin && !is_admin()) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><style>body{background:#0b1220;color:#e5e7eb;font:16px system-ui;padding:24px}</style>';
    echo '<h1>Forbidden</h1><p>Admin only.</p>';
    exit;
  }
}
function auth_login(string $email, string $password): bool {
  $pdo = db();
  $st = $pdo->prepare("SELECT * FROM safety_users WHERE email=?");
  $st->execute([trim($email)]);
  $u = $st->fetch();
  if ($u && password_verify($password, $u['pass_hash'])) {
    $_SESSION['auth'] = [
      'id' => (int)$u['id'],
      'email' => $u['email'],
      'name' => $u['name'],
      'role' => $u['role'],
    ];
    return true;
  }
  return false;
}
function auth_logout(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"] ?? '', $params["secure"], $params["httponly"]);
  }
  session_destroy();
}
