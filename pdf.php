<?php
// /pdf.php — view / rebuild / download a tour PDF
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo 'Bad id'; exit; }

// fetch tour
$st = db()->prepare('SELECT * FROM safety_tours WHERE id = ?');
$st->execute([$id]);
$tour = $st->fetch();
if (!$tour) { http_response_code(404); echo 'Tour not found'; exit; }

// where we store the file
$pdfDir  = __DIR__ . '/uploads/tours';
$pdfPath = $pdfDir . '/tour-' . $id . '.pdf';
if (!is_dir($pdfDir)) @mkdir($pdfDir, 0775, true);

// force rebuild if asked, or if file missing
$mustRebuild = isset($_GET['rebuild']) || !is_file($pdfPath);
if ($mustRebuild) {
  try {
    render_pdf($tour, $pdfPath); // uses your existing renderer
  } catch (Throwable $e) {
    http_response_code(500);
    echo 'PDF render error: ' . htmlspecialchars($e->getMessage());
    exit;
  }
}

// Optional “download with UK filename”
$download = isset($_GET['download']);
$ukTs     = $tour['tour_date'] ? date('d-m-Y_H-i', strtotime($tour['tour_date'])) : date('d-m-Y_H-i');
$siteSlug = preg_replace('~[^A-Za-z0-9\-]+~', '-', strtolower((string)($tour['site'] ?? 'tour')));
$siteSlug = trim($siteSlug, '-');
$filename = 'SafetyTour_' . ($siteSlug ?: 'site') . '_' . $ukTs . '.pdf';

header('Content-Type: application/pdf');
if ($download) {
  header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
  header('Content-Disposition: inline; filename="' . $filename . '"');
}
readfile($pdfPath);
