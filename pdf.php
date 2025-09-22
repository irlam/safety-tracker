<?php
/**
 * File: pdf.php
 * 
 * Safety Tour PDF Generator and Viewer
 * 
 * This file handles PDF generation, viewing, and downloading for safety tours.
 * Features include:
 * - Generates PDF reports from tour data using render_pdf() function
 * - Caches PDFs to avoid regenerating on each request
 * - Supports forced rebuild via ?rebuild=1 parameter
 * - Provides download option with UK-formatted filename
 * - Handles inline viewing in browser
 * - Uses UK date format (dd-mm-yyyy) in filenames
 * - Automatic directory creation for PDF storage
 * 
 * The PDF contains the complete tour checklist, responses, photos, and signatures.
 */

declare(strict_types=1);

// Set UK timezone for consistent date/time handling
date_default_timezone_set('Europe/London');

// Load core functions for database access and PDF generation
require_once __DIR__ . '/includes/functions.php';

// Validate tour ID parameter
$tourId = (int)($_GET['id'] ?? 0);
if ($tourId <= 0) { 
    http_response_code(400); 
    echo 'Invalid tour ID provided'; 
    exit; 
}

// Fetch tour data from database
$statement = db()->prepare('SELECT * FROM safety_tours WHERE id = ?');
$statement->execute([$tourId]);
$tourData = $statement->fetch();

if (!$tourData) { 
    http_response_code(404); 
    echo 'Safety tour not found'; 
    exit; 
}

// Define PDF storage location
$pdfDirectory = __DIR__ . '/uploads/tours';
$pdfFilePath = $pdfDirectory . '/tour-' . $tourId . '.pdf';

// Create directory if it doesn't exist
if (!is_dir($pdfDirectory)) {
    @mkdir($pdfDirectory, 0775, true);
}

// Determine if PDF needs to be regenerated
$forceRebuild = isset($_GET['rebuild']) || !is_file($pdfFilePath);

if ($forceRebuild) {
    try {
        // Generate new PDF using the existing render_pdf function
        render_pdf($tourData, $pdfFilePath);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'PDF generation error: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// Prepare download filename with UK date format
$isDownload = isset($_GET['download']);
$ukFormattedDate = $tourData['tour_date'] 
    ? date('d-m-Y_H-i', strtotime($tourData['tour_date'])) 
    : date('d-m-Y_H-i');

// Create safe filename from site name
$siteSlug = preg_replace('~[^A-Za-z0-9\-]+~', '-', strtolower((string)($tourData['site'] ?? 'tour')));
$siteSlug = trim($siteSlug, '-');
$downloadFilename = 'SafetyTour_' . ($siteSlug ?: 'site') . '_' . $ukFormattedDate . '.pdf';

// Set appropriate headers for PDF delivery
header('Content-Type: application/pdf');

if ($isDownload) {
    // Force download with UK-formatted filename
    header('Content-Disposition: attachment; filename="' . $downloadFilename . '"');
} else {
    // Display inline in browser with UK-formatted filename
    header('Content-Disposition: inline; filename="' . $downloadFilename . '"');
}

// Stream the PDF file to the browser
readfile($pdfFilePath);