<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
auth_check(true);
require_once __DIR__ . '/includes/branding.php';

if (empty($_SESSION['safety_brand_csrf'])) {
    $_SESSION['safety_brand_csrf'] = bin2hex(random_bytes(32));
}
$token = (string) $_SESSION['safety_brand_csrf'];
$error = '';
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) ||
        !hash_equals($token, (string) $_POST['csrf'])) {
        http_response_code(419);
        $error = 'Session expired. Refresh the page and try again.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'save');
            if ($action === 'remove-logo') {
                safety_branding_remove_logo();
                $notice = 'Logo reset to the Safety Tours default.';
            } elseif ($action === 'save') {
                if (isset($_FILES['logo']) &&
                    (int) ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    safety_branding_upload_logo($_FILES['logo']);
                }
                safety_branding_save($_POST);
                $notice = 'Branding saved. New reports will use your settings.';
            } else {
                throw new InvalidArgumentException('Unknown action.');
            }
        } catch (Throwable $e) {
            error_log('Safety branding update: ' . $e->getMessage());
            $error = $e instanceof InvalidArgumentException
                ? $e->getMessage() : 'Could not save branding. Check file permissions or PHP GD.';
        }
    }
}
$brand = safety_branding();
$logo = safety_branding_logo_url();
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#0b1220">
  <title>Branding &amp; reports · Safety Tours</title>
  <link rel="icon" href="/assets/img/favicon.png">
  <link rel="stylesheet" href="/assets/safety-ui.css">
</head>
<body class="safety-page">
<?php require_once __DIR__ . '/includes/nav.php'; render_nav('admin_branding'); ?>
<main class="safety-admin-layout">
  <div class="safety-page-heading">
    <div><span class="safety-eyebrow">ADMINISTRATION</span>
    <h1>Branding &amp; reports</h1>
    <p>Set the company identity once. The header, login screen and newly generated PDFs use the same logo.</p></div>
    <a class="safety-btn secondary" href="/admin_users.php">Manage users</a>
  </div>
  <?php if ($notice): ?><p role="status" class="safety-alert success"><?= $escape($notice) ?></p><?php endif; ?>
  <?php if ($error): ?><p role="alert" class="safety-alert error"><?= $escape($error) ?></p><?php endif; ?>
  <div class="safety-admin-grid">
    <section class="safety-panel">
      <h2>Company identity</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= $escape($token) ?>">
        <input type="hidden" name="action" value="save">
        <div class="safety-form-field">
          <label for="company_name">Company name</label>
          <input id="company_name" name="company_name" maxlength="85" required value="<?= $escape($brand['company_name']) ?>" autocomplete="organization">
        </div>
        <div class="safety-form-field">
          <label for="subtitle">Website subtitle</label>
          <input id="subtitle" name="subtitle" maxlength="85" required value="<?= $escape($brand['subtitle']) ?>">
        </div>
        <div class="safety-form-field">
          <label for="report_title">PDF report heading</label>
          <input id="report_title" name="report_title" maxlength="85" required value="<?= $escape($brand['report_title']) ?>">
        </div>
        <div class="safety-form-field">
          <label for="logo">Company logo</label>
          <input id="logo" name="logo" type="file" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
          <small>PNG, JPG or WebP, up to 2 MB. Transparent logos are placed on white for readable PDFs. A wide or square logo works best.</small>
        </div>
        <button class="safety-btn primary" type="submit">Save branding</button>
      </form>
      <form method="post" onsubmit="return confirm('Use the default logo again?')" class="safety-secondary-form">
        <input type="hidden" name="csrf" value="<?= $escape($token) ?>">
        <input type="hidden" name="action" value="remove-logo">
        <button class="safety-btn secondary" type="submit">Restore default logo</button>
      </form>
    </section>
    <aside class="safety-panel safety-preview">
      <h2>Live preview</h2>
      <div class="safety-brand-preview">
        <img src="<?= $escape($logo) ?>" alt="<?= $escape($brand['company_name']) ?> logo" loading="lazy">
        <div><strong><?= $escape($brand['company_name']) ?></strong>
          <span><?= $escape($brand['subtitle']) ?></span></div>
      </div>
      <div class="safety-paper-preview">
        <div class="safety-paper-heading">
          <img src="<?= $escape($logo) ?>" alt="">
          <div><strong><?= $escape($brand['company_name']) ?></strong>
          <span><?= $escape($brand['report_title']) ?></span></div>
        </div>
        <div class="safety-skeleton"></div><div class="safety-skeleton shorter"></div>
        <div class="safety-skeleton"></div><div class="safety-skeleton short"></div>
      </div>
      <p class="safety-note">Existing saved PDFs refresh their branding when viewed through the PDF page. Your tour records and action history are unaffected.</p>
    </aside>
  </div>
</main>
</body>
</html>
