<?php
require_once __DIR__ . '/includes/functions.php';
$ok = send_mail_multi(
  ['safety@defecttracker.uk'],
  'Mail test from Safety Tours',
  '<p>Hello from Safety Tours mail test.</p>'
);
echo $ok ? 'Mail OK' : 'Mail failed (check error log)';
