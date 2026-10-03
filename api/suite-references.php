<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/functions.php';

$expected = defined('CONSTRUCTION_SUITE_API_KEY')
    ? trim((string) CONSTRUCTION_SUITE_API_KEY)
    : trim((string) (getenv('CONSTRUCTION_SUITE_API_KEY') ?: ''));
$provided = trim((string) ($_SERVER['HTTP_X_CONSTRUCTION_SUITE_KEY'] ?? ''));

if ($expected === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'suite_integration_not_configured']);
    exit;
}
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

try {
    $stmt = db()->query(
        "SELECT DISTINCT TRIM(site) AS site
         FROM safety_tours
         WHERE site IS NOT NULL AND TRIM(site) <> ''
         ORDER BY site ASC"
    );

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $site = (string) ($row['site'] ?? '');
        if ($site === '') continue;
        $items[] = ['value' => $site, 'label' => $site];
    }

    echo json_encode([
        'ok' => true,
        'module' => 'safety',
        'reference_type' => 'site',
        'items' => $items,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Construction Suite safety reference lookup failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'references_unavailable']);
}
