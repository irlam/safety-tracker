<?php
/**
 * File: health.php
 * 
 * System Health Check and Diagnostics
 * 
 * This file provides a comprehensive health check for the Safety Tracker application.
 * It examines and reports on:
 * - PHP configuration and installed extensions
 * - Timezone settings and current time (in UK format)
 * - Session functionality
 * - File system permissions (upload directories)
 * - SMTP configuration status
 * - Database connectivity (if functions are available)
 * 
 * The output can be displayed as HTML for human viewing or JSON for API usage.
 * This is particularly useful for troubleshooting deployment issues and 
 * verifying that all system requirements are properly configured.
 */

declare(strict_types=1);

// Application root directory
$applicationRoot = __DIR__;

// Load core functions if available (graceful fallback if missing)
$functionsPath = $applicationRoot . '/includes/functions.php';
$hasCoreFunctions = file_exists($functionsPath);
if ($hasCoreFunctions) {
    require_once $functionsPath;
}

// Extract configuration values with sensible defaults
// Check both defined constants and environment variables
$smtpHost = defined('SMTP_HOST') 
    ? SMTP_HOST 
    : (getenv('SMTP_HOST') ?: 'mxe97d.netcup.net');
    
$smtpPort = defined('SMTP_PORT') 
    ? (int)SMTP_PORT 
    : (int)(getenv('SMTP_PORT') ?: 465);
    
$uploadDirectory = defined('UPLOAD_DIR') 
    ? UPLOAD_DIR 
    : ($applicationRoot . '/uploads');
    
$timezone = defined('TIMEZONE') 
    ? TIMEZONE 
    : 'Europe/London';

// Set UK timezone for all date/time operations
date_default_timezone_set($timezone);

/**
 * =============================================================================
 * SYSTEM HEALTH CHECKS
 * =============================================================================
 * Comprehensive system diagnostics for troubleshooting and verification
 */

$healthChecks = [
    // PHP Configuration and Environment
    'php' => [
        'version'            => PHP_VERSION,
        'sapi'              => PHP_SAPI,
        'memory_limit'      => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'extensions'        => get_loaded_extensions(),
    ],
    
    // Timezone and Time Configuration
    'timezone' => [
        'timezone'  => date_default_timezone_get(),
        'now_local' => date('d/m/Y H:i:s'),  // UK format display
        'now_utc'   => gmdate('Y-m-d H:i:s') . ' UTC',
    ],
    
    // Session Management
    'session' => [
        'status' => session_status(),
    ],
    
    // File System Paths
    'paths' => [
        'doc_root'    => $applicationRoot,
        'uploads_dir' => $uploadDirectory,
    ],
];

// Test session functionality
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['health_ping'] = ($_SESSION['health_ping'] ?? 0) + 1;
$healthChecks['session']['counter'] = $_SESSION['health_ping'];

// Upload Directory Tests
$healthChecks['uploads'] = [
    'exists'    => is_dir($uploadDirectory),
    'writable'  => is_dir($uploadDirectory) ? is_writable($uploadDirectory) : false,
    'test_file' => null,
    'error'     => null,
];

// Attempt to create uploads directory if it doesn't exist
if (!is_dir($uploadDirectory)) {
    @mkdir($uploadDirectory, 0775, true);
}

// Test write permissions by creating and removing a test file
if (is_dir($uploadDirectory) && is_writable($uploadDirectory)) {
    $testFileName = rtrim($uploadDirectory, '/') . '/health-' . bin2hex(random_bytes(3)) . '.txt';
    $writeTest = @file_put_contents($testFileName, "Health check test file created at " . date('c'));
    
    if ($writeTest !== false) {
        $healthChecks['uploads']['test_file'] = str_replace($applicationRoot . '/', '', $testFileName);
        // Clean up test file immediately to avoid littering
        @unlink($testFileName);
    } else {
        $healthChecks['uploads']['error'] = 'file_put_contents operation failed';
    }
} else {
    $healthChecks['uploads']['error'] = 'Upload directory is not writable';
}

// Database Connectivity Test (only if core functions are available)
$healthChecks['database'] = [
    'present'        => $hasCoreFunctions,
    'connected'      => false,
    'server_version' => null,
    'error'          => null,
];

if ($hasCoreFunctions && function_exists('db')) {
    try {
        $database = db();
        $healthChecks['database']['connected'] = true;
        $healthChecks['database']['server_version'] = $database->getAttribute(PDO::ATTR_SERVER_VERSION);
    } catch (Throwable $e) {
        $healthChecks['database']['error'] = $e->getMessage();
    }
}

// SMTP Server Connectivity Test
// Tests basic TCP connection without authentication
$healthChecks['smtp'] = [
    'host'      => $smtpHost,
    'port'      => $smtpPort,
    'connected' => false,
    'greeting'  => null,
    'error'     => null,
];

$socketError = 0;
$socketErrorMessage = '';
$connectionTimeout = 5;
$useSSL = ($smtpPort === 465) ? 'ssl://' : '';

$socketConnection = @stream_socket_client(
    $useSSL . $smtpHost . ':' . $smtpPort, 
    $socketError, 
    $socketErrorMessage, 
    $connectionTimeout, 
    STREAM_CLIENT_CONNECT
);

if ($socketConnection) {
    stream_set_timeout($socketConnection, 5);
    $serverGreeting = fgets($socketConnection, 512);
    $healthChecks['smtp']['connected'] = true;
    $healthChecks['smtp']['greeting'] = trim((string)$serverGreeting);
    fclose($socketConnection);
} else {
    $healthChecks['smtp']['error'] = "[$socketError] $socketErrorMessage";
}

// JSON API Response (if requested)
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => true,
        'timestamp' => date('c'),
        'checks' => $healthChecks
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---- HTML Display (responsive, dark theme) ----
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Health Check</title>
<style>
  :root {
    --bg: #0b1220;
    --card: #0f172a;
    --muted: #94a3b8;
    --text: #e5e7eb;
    --border: #1f2937;
    --radius: 16px;
    --ok: #16a34a;
    --bad: #ef4444;
    --warn: #f59e0b;
  }
  
  * {
    box-sizing: border-box;
  }
  
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font: 15px/1.6 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  }
  
  .wrap {
    max-width: 900px;
    margin: 0 auto;
    padding: 18px;
  }
  
  h1 {
    font-size: 1.2rem;
    margin: 0 0 10px;
  }
  
  .grid {
    display: grid;
    gap: 12px;
    grid-template-columns: 1fr 1fr;
  }
  
  @media (max-width: 800px) {
    .grid {
      grid-template-columns: 1fr;
    }
  }
  
  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
  }
  
  .muted { color: var(--muted); }
  .ok { color: var(--ok); }
  .bad { color: var(--bad); }
  .warn { color: var(--warn); }
  
  code {
    background: #0b1220;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 2px 6px;
  }
  
  .pill {
    display: inline-block;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 6px 10px;
    background: #0b1220;
  }
  
  a {
    color: #93c5fd;
    text-decoration: none;
  }
  
  ul {
    margin: 6px 0 0 18px;
  }
</style>
</head>
<body>
<div class="wrap">
  <h1>System Health Check</h1>
  <div class="muted">Local time: <?= htmlspecialchars($healthChecks['timezone']['now_local']) ?> (<?= htmlspecialchars($healthChecks['timezone']['timezone']) ?>)</div>
  <div class="muted">UTC: <?= htmlspecialchars($healthChecks['timezone']['now_utc']) ?></div>
  <p><a class="pill" href="?format=json">View as JSON</a></p>

  <div class="grid">
    <!-- PHP Configuration -->
    <div class="card">
      <strong>PHP Configuration</strong><br>
      Version: <code><?= htmlspecialchars($healthChecks['php']['version']) ?></code><br>
      SAPI: <code><?= htmlspecialchars($healthChecks['php']['sapi']) ?></code><br>
      Memory: <code><?= htmlspecialchars($healthChecks['php']['memory_limit']) ?></code><br>
      Max Exec: <code><?= htmlspecialchars($healthChecks['php']['max_execution_time']) ?></code><br>
      <div class="muted" style="margin-top:6px">Extensions (first 10):</div>
      <ul>
        <?php foreach (array_slice($healthChecks['php']['extensions'], 0, 10) as $extension): ?>
          <li><?= htmlspecialchars($extension) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <!-- Upload Directory Status -->
    <div class="card">
      <strong>Upload Directory</strong><br>
      Directory: <code><?= htmlspecialchars(str_replace($applicationRoot.'/', '', $healthChecks['paths']['uploads_dir'])) ?></code><br>
      Exists: <span class="<?= $healthChecks['uploads']['exists'] ? 'ok' : 'bad' ?>"><?= $healthChecks['uploads']['exists'] ? '✓ Yes' : '✕ No' ?></span><br>
      Writable: <span class="<?= $healthChecks['uploads']['writable'] ? 'ok' : 'bad' ?>"><?= $healthChecks['uploads']['writable'] ? '✓ Yes' : '✕ No' ?></span><br>
      <?php if ($healthChecks['uploads']['test_file']): ?>
        Test file: <span class="ok">✓ Success</span>
      <?php elseif ($healthChecks['uploads']['error']): ?>
        Error: <span class="bad"><?= htmlspecialchars($healthChecks['uploads']['error']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid">
    <!-- Database Connection -->
    <div class="card">
      <strong>Database</strong><br>
      Functions: <span class="<?= $healthChecks['database']['present'] ? 'ok' : 'warn' ?>"><?= $healthChecks['database']['present'] ? '✓ Available' : '⚠ Missing' ?></span><br>
      <?php if ($healthChecks['database']['present']): ?>
        Connected: <span class="<?= $healthChecks['database']['connected'] ? 'ok' : 'bad' ?>"><?= $healthChecks['database']['connected'] ? '✓ Yes' : '✕ No' ?></span><br>
        <?php if ($healthChecks['database']['server_version']): ?>
          Version: <code><?= htmlspecialchars($healthChecks['database']['server_version']) ?></code>
        <?php endif; ?>
        <?php if ($healthChecks['database']['error']): ?>
          Error: <span class="bad"><?= htmlspecialchars($healthChecks['database']['error']) ?></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- SMTP Server Connection -->
    <div class="card">
      <strong>SMTP Server</strong><br>
      Host: <code><?= htmlspecialchars($healthChecks['smtp']['host']) ?></code><br>
      Port: <code><?= htmlspecialchars((string)$healthChecks['smtp']['port']) ?></code><br>
      Connected: <span class="<?= $healthChecks['smtp']['connected'] ? 'ok' : 'bad' ?>"><?= $healthChecks['smtp']['connected'] ? '✓ Yes' : '✕ No' ?></span><br>
      <?php if ($healthChecks['smtp']['greeting']): ?>
        Greeting: <code><?= htmlspecialchars($healthChecks['smtp']['greeting']) ?></code>
      <?php endif; ?>
      <?php if ($healthChecks['smtp']['error']): ?>
        Error: <span class="bad"><?= htmlspecialchars($healthChecks['smtp']['error']) ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Session Information -->
  <div class="card">
    <strong>Session Status</strong><br>
    Status: <code><?= htmlspecialchars((string)$healthChecks['session']['status']) ?></code><br>
    Counter: <code><?= htmlspecialchars((string)$healthChecks['session']['counter']) ?></code> (increments on each visit)
  </div>
</div>
</body>
</html>
