<?php
/**
 * File: check.php
 * 
 * Safety Tour Submission Results Page
 * 
 * This file displays the outcome of a safety tour form submission, showing:
 * - Success/failure status for database save operation
 * - Success/failure status for PDF generation
 * - Success/failure status for email delivery
 * - Tour details and information
 * - Quick action links for next steps
 * 
 * The page receives status parameters via GET request from the submission process
 * and displays them in an easy-to-understand visual format using UK date formatting.
 */

declare(strict_types=1);

// Set UK timezone for consistent date/time display
date_default_timezone_set('Europe/London');

// Optional authentication - load auth system if available
$authPath = __DIR__ . '/includes/auth.php';
if (file_exists($authPath)) {
    require_once $authPath;
    auth_check(); // Verify user access permissions
}

// Load core functions for database access and utilities
require_once __DIR__ . '/includes/functions.php';

// Extract status parameters from submission process
$tourId = (int)($_GET['id'] ?? 0);                           // Tour record ID
$databaseSaveSuccess = ((int)($_GET['db'] ?? 0)) === 1;      // Database save status
$pdfGenerationSuccess = ((int)($_GET['pdf'] ?? 0)) === 1;    // PDF creation status
$emailSentSuccess = ((int)($_GET['mail'] ?? 0)) === 1;       // Email delivery status
$hadSubmissionError = ((int)($_GET['error'] ?? 0)) === 1;    // General error flag

// Load tour details if ID provided
$tourDetails = null;
if ($tourId > 0) {
    $statement = db()->prepare(
        'SELECT id, site, area, lead_name, tour_date 
         FROM safety_tours 
         WHERE id = ?'
    );
    $statement->execute([$tourId]);
    $tourDetails = $statement->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Submission Result — Safety Tour</title>
<style>
  /* CSS Custom Properties for consistent theming */
  :root {
    --bg: #0b1220;
    --card: #111827;
    --muted: #94a3b8;
    --text: #e5e7eb;
    --ok: #22c55e;
    --warn: #f59e0b;
    --err: #ef4444;
    --border: #1f2937;
    --radius: 18px;
  }
  
  /* Base styling */
  * {
    box-sizing: border-box;
  }
  
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font: 16px/1.6 system-ui, 'Segoe UI', Roboto, sans-serif;
  }
  
  /* Layout components */
  .wrap {
    max-width: 860px;
    margin: 0 auto;
    padding: 18px;
  }
  
  h1 {
    margin: 0 0 10px;
  }
  
  .card {
    background: #0f172a;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px;
    margin: 10px 0;
  }
  
  /* Responsive grid layout */
  .row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  
  @media (max-width: 720px) {
    .row {
      grid-template-columns: 1fr;
    }
  }
  
  /* Status indicators */
  .pill {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 8px 12px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #0f172a;
  }
  
  .ok {
    color: var(--ok);
    border-color: #1f5130;
  }
  
  .no {
    color: var(--err);
    border-color: #5b1f1f;
  }
  
  /* Action buttons */
  a.btn {
    display: inline-block;
    background: #0ea5e9;
    color: #00131a;
    text-decoration: none;
    font-weight: 700;
    padding: 10px 14px;
    border-radius: 12px;
  }
  
  .muted {
    color: var(--muted);
  }
</style>
</head>
<body>
<div class="wrap">
  <h1>Submission Result</h1>

  <!-- Tour Information Display -->
  <?php if ($tourDetails): ?>
    <div class="card">
      <div class="muted">Tour Information</div>
      <div>
        <strong>#<?= (int)$tourDetails['id'] ?></strong> — 
        <?= htmlspecialchars($tourDetails['site']) ?> / <?= htmlspecialchars($tourDetails['area'] ?? '') ?> —
        <?= htmlspecialchars(date('d/m/Y H:i', strtotime($tourDetails['tour_date']))) ?> 
        (Lead: <?= htmlspecialchars($tourDetails['lead_name']) ?>)
      </div>
    </div>
  <?php endif; ?>

  <!-- Operation Status Grid -->
  <div class="row">
    <!-- Database Save Status -->
    <div class="card">
      <div class="muted">Database Save</div>
      <div class="pill <?= $databaseSaveSuccess ? 'ok' : 'no' ?>">
        <?= $databaseSaveSuccess ? '✓ Successfully Saved' : '✕ Save Failed' ?>
      </div>
    </div>
    
    <!-- PDF Generation Status -->
    <div class="card">
      <div class="muted">PDF Generation</div>
      <div class="pill <?= $pdfGenerationSuccess ? 'ok' : 'no' ?>">
        <?= $pdfGenerationSuccess ? '✓ PDF Created' : '✕ PDF Failed' ?>
      </div>
    </div>
  </div>

  <div class="row">
    <!-- Email Delivery Status -->
    <div class="card">
      <div class="muted">Email Delivery</div>
      <div class="pill <?= $emailSentSuccess ? 'ok' : 'no' ?>">
        <?= $emailSentSuccess ? '✓ Email Sent (to at least one recipient)' : '✕ Email Not Sent' ?>
      </div>
    </div>
    
    <!-- Next Actions -->
    <div class="card">
      <div class="muted">Next Actions</div>
      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:6px">
        <?php if ($tourId): ?>
          <a class="btn" href="pdf.php?id=<?= (int)$tourId ?>" target="_blank">Open PDF</a>
        <?php endif; ?>
        <a class="btn" href="dashboard.php">Go to Dashboard</a>
        <a class="btn" href="form.php">Create Another Tour</a>
      </div>
    </div>
  </div>

  <!-- Error Information -->
  <?php if ($hadSubmissionError): ?>
    <div class="card">
      <div class="muted">
        There was an error during submission. Please check your PHP error log (via hosting control panel) for detailed error information.
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
