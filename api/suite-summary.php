<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/includes/functions.php';

$expected = '';
if (defined('CONSTRUCTION_SUITE_API_KEY')) {
    $expected = trim((string) CONSTRUCTION_SUITE_API_KEY);
} else {
    $expected = trim((string) (getenv('CONSTRUCTION_SUITE_API_KEY') ?: ''));
}
if ($expected === '') {
    $envPath = dirname(__DIR__) . '/.env';
    if (is_file($envPath) && is_readable($envPath)) {
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = array_map('trim', explode('=', $line, 2));
            if ($name !== 'CONSTRUCTION_SUITE_API_KEY') {
                continue;
            }
            $expected = trim($value, " \t\n\r\0\x0B\"'");
            break;
        }
    }
}

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

$site = trim((string) ($_GET['site'] ?? ''));

try {
    $pdo = db();

    $where = ['1=1'];
    $params = [];
    if ($site !== '') {
        $where[] = 't.site = :site';
        $params[':site'] = $site;
    }

    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN a.status = 'Open' THEN 1 ELSE 0 END),0) AS open_actions,
                COALESCE(SUM(CASE WHEN a.status = 'Closed' THEN 1 ELSE 0 END),0) AS closed_actions,
                COALESCE(SUM(CASE WHEN a.status = 'Open' AND a.due_date IS NOT NULL AND a.due_date < CURDATE() THEN 1 ELSE 0 END),0) AS overdue,
                COALESCE(SUM(CASE WHEN a.status = 'Open' AND LOWER(a.priority) = 'high' THEN 1 ELSE 0 END),0) AS high_priority,
                MAX(a.created_at) AS last_updated
            FROM safety_actions a
            LEFT JOIN safety_tours t ON t.id = a.tour_id
            WHERE " . implode(' AND ', $where);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $tourWhere = $site !== '' ? 'WHERE site = :site AND DATE(tour_date) = CURDATE()' : 'WHERE DATE(tour_date) = CURDATE()';
    $tourStmt = $pdo->prepare("SELECT COUNT(*) FROM safety_tours {$tourWhere}");
    $tourStmt->execute($site !== '' ? [':site' => $site] : []);
    $toursToday = (int) $tourStmt->fetchColumn();

    echo json_encode([
        'ok' => true,
        'module' => 'safety',
        'scope' => ['site' => $site !== '' ? $site : null],
        'metrics' => [
            'open_actions' => (int) ($row['open_actions'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'high_priority' => (int) ($row['high_priority'] ?? 0),
            'closed_actions' => (int) ($row['closed_actions'] ?? 0),
            'tours_today' => $toursToday,
        ],
        'last_updated' => $row['last_updated'] ?: null,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Construction Suite summary failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'summary_unavailable']);
}
