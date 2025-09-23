<?php
// File: form.php
// Description: New Site Safety Tour submission form. Lets users complete a full checklist, upload per-question photos, select recipients, and add a signature. All times are UK format. Fully commented for clarity and non-coders.

$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) { require_once $auth; if (function_exists('auth_check')) auth_check(); }

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';
require_once __DIR__ . '/includes/checklist.php';
date_default_timezone_set('Europe/London');

$pdo = db();

// Get recipient directory
$st = $pdo->prepare("SELECT DISTINCT recipients FROM safety_tours WHERE recipients IS NOT NULL AND recipients != '' ORDER BY recipients");
$st->execute();
$recipientOptions = [];
foreach ($st->fetchAll() as $row) {
  $emails = array_filter(array_map('trim', explode(',', $row['recipients'])));
  foreach ($emails as $email) {
    if ($email && !in_array($email, $recipientOptions)) {
      $recipientOptions[] = $email;
    }
  }
}
sort($recipientOptions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php require_once __DIR__ . '/includes/pwa_head.php'; ?>
  <title>New Safety Tour</title>
  <style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; margin: 0; background: #f8fafc; }
    .container { max-width: 800px; margin: 0 auto; padding: 20px; }
    .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .form-group { margin: 15px 0; }
    .form-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #374151; }
    .form-control { width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
    .form-control:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .btn-primary { background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
    .btn-primary:hover { background: #2563eb; }
    .checklist-section { border-top: 2px solid #e5e7eb; padding-top: 20px; margin-top: 20px; }
    .checklist-item { border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; margin: 10px 0; }
    .question-header { font-weight: 600; margin-bottom: 10px; }
    .radio-group { display: flex; gap: 15px; margin: 10px 0; }
    .radio-group label { font-weight: normal; display: flex; align-items: center; gap: 5px; }
    .signature-pad { border: 2px dashed #d1d5db; border-radius: 6px; height: 200px; margin: 10px 0; display: flex; align-items: center; justify-content: center; cursor: pointer; }
    .file-upload { margin: 10px 0; }
  </style>
</head>
<body>
  <?php render_nav('new'); ?>
  
  <div class="container">
    <div class="card">
      <h1>New Site Safety Tour</h1>
      <form method="post" action="submit.php" enctype="multipart/form-data" id="tourForm">
        
        <!-- Basic Details -->
        <div class="form-group">
          <label for="site">Site *</label>
          <input type="text" id="site" name="site" class="form-control" required>
        </div>
        
        <div class="form-group">
          <label for="area">Area</label>
          <input type="text" id="area" name="area" class="form-control">
        </div>
        
        <div class="form-group">
          <label for="lead_name">Lead Name *</label>
          <input type="text" id="lead_name" name="lead_name" class="form-control" required>
        </div>
        
        <div class="form-group">
          <label for="participants">Participants</label>
          <input type="text" id="participants" name="participants" class="form-control">
        </div>
        
        <div class="form-group">
          <label for="tour_date">Tour Date & Time *</label>
          <input type="datetime-local" id="tour_date" name="tour_date" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
        </div>
        
        <div class="form-group">
          <label for="recipients">Email Recipients (comma-separated)</label>
          <input type="text" id="recipients" name="recipients" class="form-control" placeholder="email1@example.com, email2@example.com">
          <?php if ($recipientOptions): ?>
            <small>Recent recipients: <?= implode(', ', array_slice($recipientOptions, 0, 5)) ?></small>
          <?php endif; ?>
        </div>
        
      </form>
    </div>
    
    <!-- Checklist -->
    <div class="card">
      <h2>Safety Checklist</h2>
      <?php 
      $index = 0;
      foreach ($CHECKLIST as $section => $items):
      ?>
        <div class="checklist-section">
          <h3><?= h($section) ?></h3>
          <?php foreach ($items as $item): ?>
            <div class="checklist-item">
              <div class="question-header"><?= h($item['code']) ?>: <?= h($item['q']) ?></div>
              
              <div class="radio-group">
                <label><input type="radio" name="check_status[<?= $index ?>]" value="pass"> Pass</label>
                <label><input type="radio" name="check_status[<?= $index ?>]" value="fail"> Fail</label>
                <label><input type="radio" name="check_status[<?= $index ?>]" value="improvement"> Improvement</label>
                <label><input type="radio" name="check_status[<?= $index ?>]" value="na"> N/A</label>
              </div>
              
              <div class="form-group">
                <label>Priority</label>
                <select name="f_severity[<?= $index ?>]" class="form-control">
                  <option value="">Select Priority</option>
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
              
              <div class="form-group">
                <label>Notes</label>
                <textarea name="f_note[<?= $index ?>]" class="form-control" rows="2"></textarea>
              </div>
              
              <div class="form-group">
                <label>Action Required</label>
                <textarea name="a_action[<?= $index ?>]" class="form-control" rows="2"></textarea>
              </div>
              
              <div class="form-group">
                <label>Responsible Person</label>
                <input type="text" name="a_resp[<?= $index ?>]" class="form-control">
              </div>
              
              <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="a_due[<?= $index ?>]" class="form-control">
              </div>
              
              <div class="file-upload">
                <label>Question Photos</label>
                <input type="file" name="qphotos[<?= $index ?>][]" multiple accept="image/*" class="form-control">
              </div>
              
              <input type="hidden" name="f_category[<?= $index ?>]" value="<?= h($section) ?>">
            </div>
            <?php $index++; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
    
    <!-- Additional Photos -->
    <div class="card">
      <h2>Additional Photos</h2>
      <div class="file-upload">
        <label>Extra Photos (not tied to specific questions)</label>
        <input type="file" name="photos[]" multiple accept="image/*" class="form-control">
      </div>
    </div>
    
    <!-- Signature -->
    <div class="card">
      <h2>Signature *</h2>
      <div class="form-group">
        <label>Upload Signature File</label>
        <input type="file" name="signature_file" accept="image/*" class="form-control">
      </div>
      <p style="text-align: center; color: #6b7280;">OR</p>
      <div class="signature-pad" onclick="alert('Canvas signature feature would be implemented here')">
        <span>Click to add signature (feature to be implemented)</span>
      </div>
      <input type="hidden" name="signature_data" id="signature_data">
    </div>
    
    <!-- Submit -->
    <div class="card">
      <button type="submit" form="tourForm" class="btn-primary">Submit Safety Tour</button>
    </div>
  </div>

  <script>
    // Basic form validation
    document.getElementById('tourForm').addEventListener('submit', function(e) {
      const required = ['site', 'lead_name', 'tour_date'];
      let valid = true;
      
      required.forEach(name => {
        const field = document.querySelector(`[name="${name}"]`);
        if (!field.value.trim()) {
          field.style.borderColor = '#ef4444';
          valid = false;
        } else {
          field.style.borderColor = '#d1d5db';
        }
      });
      
      // Check signature
      const sigFile = document.querySelector('[name="signature_file"]');
      const sigData = document.querySelector('[name="signature_data"]');
      if (!sigFile.files.length && !sigData.value) {
        alert('Please provide a signature by uploading a file or using the signature pad.');
        valid = false;
      }
      
      if (!valid) {
        e.preventDefault();
        alert('Please fill in all required fields marked with *');
      }
    });
  </script>
</body>
</html>