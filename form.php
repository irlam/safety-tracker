<?php
// File: form.php
// Description: New Site Safety Tour submission form. Includes checklist/questions, per-question file uploads, recipient selection, signature pad, and full UK date/time formatting. Designed for clarity and maintainability by non-coders.

auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
rend...