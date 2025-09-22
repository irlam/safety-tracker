<?php
// File: form.php
// Description: New Site Safety Tour submission form. Lets users complete a full checklist, upload per-question photos, select recipients, and add a signature. All times are UK format. Fully commented for clarity and non-coders.

auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
rend...