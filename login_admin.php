<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/branding.php';

$brand = safety_branding();
$err = '';
$next = (string) ($_POST['next'] ?? $_GET['next'] ?? '/dashboard.php');
// Local redirects only. Prevent host-switching and header injection.
if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')
    || preg_match('/[\\\r\n]/', $next) || str_contains($next, '://')) {
    $next = '/dashboard.php';
}
if (!empty($_SESSION['auth'])) {
    header('Location: ' . $next);
    exit;
}
if (empty($_SESSION['safety_login_csrf'])) {
    $_SESSION['safety_login_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['safety_login_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null)
        || !hash_equals($csrf, (string) $_POST['csrf'])) {
        $err = 'Your session expired. Refresh and try again.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($email === '' || $password === '') {
            $err = 'Enter your email and password.';
        } elseif (auth_login($email, $password)) {
            session_regenerate_id(true);
            unset($_SESSION['safety_login_csrf']);
            header('Location: ' . $next);
            exit;
        } else {
            $err = 'Incorrect email or password.';
        }
    }
}
$escape = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0b1220">
  <title>Sign in · <?= $escape($brand['company_name']) ?> Safety Tours</title>
  <meta name="description" content="Sign in to manage safety tours, observations, and close-out actions.">
  <link rel="icon" href="/assets/img/favicon.png">
  <link rel="stylesheet" href="/assets/safety-ui.css">
</head>
<body class="safety-page">
<main class="safety-login">
  <div class="safety-login-card">
    <section class="safety-login-hero">
      <div class="safety-login-brand">
        <img src="<?= $escape(safety_branding_logo_url()) ?>" alt="">
        <div><strong><?= $escape($brand['company_name']) ?></strong>
          <span><?= $escape($brand['subtitle']) ?></span></div>
      </div>
      <span class="safety-eyebrow">SITE SAFETY MANAGEMENT</span>
      <h1>Safer sites.<br>Clearer actions.</h1>
      <p>Capture site observations, track actions, and build professional safety tour reports on site or back at the office.</p>
      <div class="safety-login-chips">
        <span>Safety tours</span><span>Photos &amp; evidence</span><span>Action close-out</span>
      </div>
    </section>
    <section class="safety-login-form">
      <h2>Welcome back</h2>
      <p>Sign in with your Site Safety account to continue.</p>
      <?php if ($err): ?><div class="safety-alert error" role="alert"><?= $escape($err) ?></div><?php endif; ?>
      <form method="post" action="/login_admin.php">
        <input type="hidden" name="next" value="<?= $escape($next) ?>">
        <input type="hidden" name="csrf" value="<?= $escape($csrf) ?>">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="username" required autofocus maxlength="190"
               value="<?= $escape($_POST['email'] ?? '') ?>">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Sign in →</button>
      </form>
      <p class="safety-help">Need an account or a password reset? Contact your site administrator.</p>
    </section>
  </div>
</main>
</body>
</html>
