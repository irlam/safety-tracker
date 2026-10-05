<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$auth = (string) file_get_contents($root . '/includes/auth.php');
$summary = (string) file_get_contents($root . '/api/suite-summary.php');
$recovery = (string) file_get_contents($root . '/database/safety_schema_reconstructed.sql');
$migration = (string) file_get_contents($root . '/database/migrations/001_audit_nullable_action.sql');

$checks = [
    [!str_contains($auth, 'const ADMIN_BOOTSTRAP_PASS'), 'Bootstrap default password returned'],
    [str_contains($auth, 'strlen($password) < 16'), 'Secure bootstrap requirement missing'],
    [str_contains($summary, 'AS ' . chr(96) . 'high_priority' . chr(96)), 'MySQL keyword alias not quoted'],
    [str_contains($recovery, 'CREATE TABLE IF NOT EXISTS ' . chr(96) . 'safety_tours' . chr(96)), 'Tours recovery missing'],
    [str_contains($recovery, 'CREATE TABLE IF NOT EXISTS ' . chr(96) . 'safety_actions' . chr(96)), 'Actions recovery missing'],
    [str_contains($migration, 'MODIFY COLUMN ' . chr(96) . 'action' . chr(96)), 'Audit migration missing'],
];
foreach ($checks as [$pass, $message]) {
    if (!$pass) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }
}
echo "PASS: Safety Suite keyword, bootstrap hardening and reconstruction checks.\n";
