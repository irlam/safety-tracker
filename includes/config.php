<?php
declare(strict_types=1);
// Credentials are deployment-owned and never supplied by public source.
define('SAFETY_APP_ROOT', dirname(__DIR__));
$privateConfig = getenv('SAFETY_CONFIG_FILE') ?: (is_file(__DIR__ . '/runtime.private.php') ? __DIR__ . '/runtime.private.php' : dirname(SAFETY_APP_ROOT) . '/private/config.php');
if (!@is_file($privateConfig)) throw new RuntimeException('Safety private configuration is missing.');
require_once $privateConfig;
foreach (['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'TIMEZONE', 'UPLOAD_DIR', 'AUTH_MODE', 'AUTH_PASSWORD'] as $required) {
    if (!defined($required)) throw new RuntimeException('Safety private configuration is invalid.');
}
