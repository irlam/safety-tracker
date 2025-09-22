<?php require_once __DIR__ . '/includes/auth.php'; auth_check(); require_once __DIR__ . '/includes/functions.php';
$rows = db()->query('SELECT id, tour_date, site, area, lead_name, status, created_at FROM safety_tours ORDER BY id DESC LIMIT 200')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Safety Tours — List</title>
<style>body{background:#111827;color:#e5e7eb;font-family:system-ui,sans-serif;margin:0;padding:18px} table{border-collapse:collapse;width:100%} th,td{border:1px solid #1f2937;padding:8px} th{background:#0f172a}</style>
</head><body>
<h1>Safety Tours — Latest</h1>
<p><a href="form.php">+ New Tour</a></p>
<table>
  <tr><th>#</th><th>Date</th><th>Site</th><th>Area</th><th>Lead</th><th>Status</th><th>PDF</th></tr>
  <?php foreach ($rows as $r): ?>
  <tr>
    <td><?= (int)$r['id'] ?></td>
    <td><?= htmlspecialchars($r['tour_date']) ?></td>
    <td><?= htmlspecialchars($r['site']) ?></td>
    <td><?= htmlspecialchars($r['area']) ?></td>
    <td><?= htmlspecialchars($r['lead_name']) ?></td>
    <td><?= htmlspecialchars($r['status']) ?></td>
    <td><a href="pdf.php?id=<?= (int)$r['id'] ?>" target="_blank">PDF</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</body></html>
