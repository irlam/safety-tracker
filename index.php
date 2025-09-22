<?php
declare(strict_types=1);
// optional: brings in is_admin() if you want to branch later
require_once __DIR__ . '/includes/auth.php';

header('Location: /dashboard.php', true, 302);
exit;
