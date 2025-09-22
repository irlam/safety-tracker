<?php
/**
 * File: success.php
 * 
 * Safety Tour Success Page
 * 
 * This page displays the successful completion of a safety tour submission.
 * It provides:
 * - Confirmation that the tour has been saved with the assigned ID
 * - Tour details including site, area, and date/time in UK format
 * - Status indicators for PDF and email operations
 * - Quick action links to view PDF, create new tours, or go to dashboard
 * 
 * The page gracefully handles missing data and provides a simple, clean interface
 * for users to understand what happened after their form submission.
 * All dates are displayed in UK format (dd/mm/yyyy HH:MM).
 */

declare(strict_types=1);

// Set UK timezone for consistent date/time display
@date_default_timezone_set('Europe/London');

// Extract parameters from successful submission
$tourId = (int)($_GET['id'] ?? 0);           // Tour record ID
$pdfGenerated = (int)($_GET['pdf'] ?? 0);    // PDF creation status (1 or 0)
$emailSent = (int)($_GET['mail'] ?? 0);      // Email delivery status (1 or 0)

// Initialize tour details
$tourSite = null;
$tourArea = null; 
$tourDateTime = null;

// Attempt to load tour details from database
try {
    $functionsPath = __DIR__ . '/includes/functions.php';
    if ($tourId > 0 && is_file($functionsPath)) {
        require_once $functionsPath;
        
        $statement = db()->prepare(
            'SELECT site, area, tour_date 
             FROM safety_tours 
             WHERE id = ?'
        );
        $statement->execute([$tourId]);
        
        if ($tourData = $statement->fetch()) {
            $tourSite = $tourData['site'] ?? null;
            $tourArea = $tourData['area'] ?? null;
            
            // Format date/time in UK format if available
            $tourDateTime = !empty($tourData['tour_date']) 
                ? date('d/m/Y H:i', strtotime($tourData['tour_date'])) 
                : null;
        }
    }
} catch (Throwable $e) {
    // Non-fatal error - page still renders without tour details
    // This ensures the success page always displays even if database issues occur
}

// Utility function to safely escape HTML output
// Avoid conflicts with includes/functions.php if it exists
if (!function_exists('h_safe')) {
    function h_safe(string $text): string { 
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tour Saved — #<?= $tourId ?: 0 ?></title>
<style>
  /* CSS Custom Properties for consistent theming */
  :root {
    --bg: #0b1220;
    --card: #111827;
    --border: #1f2937;
    --text: #e5e7eb;
    --muted: #94a3b8;
    --ok: #22c55e;
    --bad: #ef4444;
    --radius: 16px;
  }
  
  /* Base styling */
  * {
    box-sizing: border-box;
  }
  
  body {
    margin: 0;
    background: var(--bg);
    color: var(--text);
    font: 16px/1.55 system-ui, 'Segoe UI', Roboto, sans-serif;
  }
  
  /* Layout components */
  .wrap {
    max-width: 920px;
    margin: 0 auto;
    padding: 20px;
  }
  
  h1 {
    margin: 0 0 12px;
    font-size: 1.4rem;
  }
  
  .card {
    background: #0f172a;
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px;
    margin: 12px 0;
  }
  
  /* Grid layouts */
  .row {
    display: grid;
    gap: 12px;
    grid-template-columns: 1fr 1fr;
  }
  
  .grid2 {
    display: grid;
    gap: 10px;
    grid-template-columns: 1fr 1fr;
  }
  
  @media (max-width: 760px) {
    .row, .grid2 {
      grid-template-columns: 1fr;
    }
  }
  
  /* Status indicators */
  .tag {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: #0f172a;
    color: var(--muted);
    font-weight: 700;
  }
  
  .ok {
    color: #eaffec;
    border-color: #1d3e2b;
    background: #0e1f15;
  }
  
  .bad {
    color: #fff0f0;
    border-color: #402020;
    background: #201011;
  }
  
  /* Action buttons */
  .btn {
    display: inline-block;
    padding: 10px 14px;
    border-radius: 12px;
    background: #0ea5e9;
    color: #00131a;
    font-weight: 700;
    text-decoration: none;
  }
  
  .btn.ghost {
    background: #0b1220;
    color: #e5e7eb;
    border: 1px solid var(--border);
  }
  
  .muted {
    color: var(--muted);
  }
</style>
</head>
<body>
<div class="wrap">
  <!-- Success Header -->
  <h1>Safety Tour Saved <?= $tourId ? '— #' . (int)$tourId : '' ?></h1>

  <!-- Tour Details Card -->
  <?php if ($tourId && ($tourSite || $tourArea || $tourDateTime)): ?>
    <div class="card">
      <div class="muted">Tour Information</div>
      <div>
        <?= $tourSite ? '<strong>' . h_safe($tourSite) . '</strong>' : '' ?>
        <?= $tourArea ? ' · ' . h_safe($tourArea) : '' ?>
        <?= $tourDateTime ? ' · ' . h_safe($tourDateTime) : '' ?>
      </div>
    </div>
  <?php elseif (!$tourId): ?>
    <!-- Fallback when no tour ID provided -->
    <div class="card">
      <div class="muted">No tour ID provided in the request.</div>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="card">
      <div class="muted">PDF generation</div>
      <div class="tag <?= $pdfGenerated ? 'ok' : 'bad' ?>"><?= $pdfGenerated ? '✓ Created' : '✕ Failed' ?></div>
      <?php if ($tourId): ?>
        <div style="margin-top:10px">
          <a class="btn" href="pdf.php?id=<?= (int)$tourId ?>" target="_blank">Open PDF</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="muted">Emails sent</div>
      <div class="tag <?= $emailSent ? 'ok' : 'bad' ?>"><?= $emailSent ? '✓ OK' : '✕ Not sent' ?></div>
      <?php if (!$emailSent): ?>
        <div class="muted" style="margin-top:8px">Check SMTP creds or recipient list, then resend from Edit.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="grid2">
      <div>
        <div class="muted">Next steps</div>
        <ul style="margin:8px 0 0 18px; padding:0 0 0 4px; line-height:1.5">
          <?php if ($tourId): ?>
            <li>Review answers & attachments in <a href="edit.php?id=<?= (int)$tourId ?>">Edit</a>.</li>
            <li>Rebuild or download the PDF if needed.</li>
          <?php endif; ?>
          <li>Log any follow-up actions in the register.</li>
        </ul>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;justify-content:flex-end">
        <?php if ($tourId): ?>
          <a class="btn" href="edit.php?id=<?= (int)$tourId ?>">Edit this tour</a>
        <?php endif; ?>
        <a class="btn ghost" href="dashboard.php">Dashboard</a>
        <a class="btn ghost" href="actions.php">Actions</a>
        <a class="btn" href="form.php">Create another</a>
      </div>
    </div>
  </div>

  <p class="muted">Time: <?= h_safe(date('d/m/Y H:i')) ?> (Europe/London)</p>
</div>
</body>
</html>
