<?php
/**
 * File: form.php
 * 
 * Safety Tour Data Entry Form
 * 
 * This file provides the main form for creating new site safety tours. It presents
 * a comprehensive checklist covering all aspects of construction site safety including
 * site setup, statutory information, work equipment, high-risk operations, and more.
 * Features include:
 * - Interactive form with dynamic scoring
 * - Canvas signature capture functionality  
 * - Photo upload capability per question
 * - Email recipient management
 * - Responsive design for mobile and desktop use
 * 
 * The form submits to submit.php for processing, PDF generation, and email distribution.
 * All dates and times are displayed in UK format (dd/mm/yyyy).
 */

declare(strict_types=1);

// Set UK timezone for consistent date/time handling
date_default_timezone_set('Europe/London');

// Optional authentication - load auth system if available
$authPath = __DIR__ . '/includes/auth.php';
if (is_file($authPath)) {
    require_once $authPath;
    if (function_exists('auth_check')) {
        auth_check(); // Verify user access permissions
    }
}

// Load core functions and navigation components
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nav.php';

// Render the navigation menu with 'new' section highlighted
render_nav('new');

/**
 * Safety Tour Checklist
 * 
 * Comprehensive safety checklist covering all major aspects of construction site safety.
 * This checklist is structured into 10 main categories, each containing specific 
 * questions that must be evaluated during the site tour.
 * 
 * Each checklist item contains:
 * - 'code': Unique identifier for the question (e.g., "1.1", "2.3")  
 * - 'q': The actual question text to be evaluated
 * 
 * The checklist covers: Site Setup, Statutory Information, Site Areas, Equipment,
 * High-Risk Operations, Permits, Health, Communication, Environmental, and Waste Management.
 */
$CHECKLIST = [
  '1.0 Site Set Up' => [
    ['code'=>'1.1','q'=>"Is the Site perimeter in place with Temporary works applied for Fixed or Hera's fencing, and are gates secured with lockable devices."],
    ['code'=>'1.2','q'=>"Has Safe access and egress been provided for pedestrians and segregation applied away from plant, equipment and materials."],
    ['code'=>'1.3','q'=>"Is statutory signage for awareness, e.g., speed limits, crossing points, and emergency locations in place."],
    ['code'=>'1.4','q'=>"Have designated storage areas away from pedestrians been provided and barriered off."],
  ],
  '2.0 Statutory Information / First Aid' => [
    ['code'=>'2.1','q'=>"Are the statutory notice boards being displayed for the McGoff Construction Ltd (H&S Policy & Arrangements, F10, CPP, First Aid, Fire information and Permits issued)."],
    ['code'=>'2.2','q'=>"Has the Construction Phase Plan been reviewed for changes onsite."],
    ['code'=>'2.3','q'=>"Have the site files been developed and contain the required information under the correct appendixes."],
    ['code'=>'2.4','q'=>"Has a Temporary works file been developed (Site fencing, scaffold, propping, formwork, falsework, structural supports etc) with training in place for the appointed person."],
    ['code'=>'2.5','q'=>"Have site inductions been completed, competency details verified and PPE checked prior to commencing work."],
    ['code'=>'2.6','q'=>"Are first aiders and fire marshals trained, certificates displayed and provisions suitable e.g., First aid equipment / defibrillator charged."],
    ['code'=>'2.7','q'=>"Is the accident book in place and investigation procedure briefed and understood in the event of an incident onsite."],
  ],
  '3.0 Site Areas' => [
    ['code'=>'3.1','q'=>"Is the lighting in place which is suitable & sufficient internally and externally and task light for specific operations being used."],
    ['code'=>'3.2','q'=>"Are access routes defined and clear of work materials where required e.g., general walkways and fire routes."],
    ['code'=>'3.3','q'=>"Are emergency escape routes clear with fire fighting equipment accessible and in service date."],
    ['code'=>'3.4','q'=>"Are temporary electrical units in place with cables elevated to eliminate potential slip, trip and falling hazards."],
    ['code'=>'3.5','q'=>"Is housekeeping being managed for waste materials, and provisions in place to remove waste streams keeping work areas clear to eliminate potential slip, trip and falling hazards."],
    ['code'=>'3.6','q'=>"Are dust levels being managed onsite with suitable equipment in place, e.g., medium rated extraction, suppression systems, RPE etc."],
    ['code'=>'3.7','q'=>"Are COSHH assessments in place and designated storage areas for chemicals provided, with ventilation suitable & sufficient being provided with the correct PPE being used."],
  ],
  '4.0 Work Equipment & Plant' => [
    ['code'=>'4.1','q'=>"Is Plant certification with reference to LOLER (rotating plant & equipment, passenger/goods hoist, MEWP etc) in place with competency levels retained for site compliance."],
    ['code'=>'4.2','q'=>"Has the hierarchy for WAH equipment been considered, and equipment being used built to manufacturers’ instructions."],
    ['code'=>'4.3','q'=>"Are scaffold designs in place, with statutory inspections completed weekly and after adverse weather conditions with corrective measures required applied in line with relevant standards."],
    ['code'=>'4.4','q'=>"Are scaffold lifts clear from materials and being cleared daily to eliminate potential falling objects."],
  ],
  '5.0 High-Risk Operations' => [
    ['code'=>'5.1','q'=>"Temporary Works"],
    ['code'=>'5.2','q'=>"Asbestos Management"],
    ['code'=>'5.3','q'=>"Lifting Operations"],
    ['code'=>'5.4','q'=>"Excavations"],
    ['code'=>'5.5','q'=>"Confined Spaces"],
  ],
  '6.0 Permit Management' => [
    ['code'=>'6.1','q'=>"Are site specific permits being issued and closed out in line with site procedures."],
  ],
  '7.0 Occupational Health / Biological' => [
    ['code'=>'7.1','q'=>"Occupational Health"],
    ['code'=>'7.2','q'=>"COVID-19 Management"],
    ['code'=>'7.3','q'=>"Biological Hazards & Considerations"],
  ],
  '8.0 Communication' => [
    ['code'=>'8.1','q'=>"Has there been a requirement to conduct a safety intervention at the time of the inspection."],
    ['code'=>'8.2','q'=>"Is there a record of any site meetings where H&S areas have been discussed with action plans set."],
    ['code'=>'8.3','q'=>"Have any toolbox talks been delivered in the calendar month."],
    ['code'=>'8.4','q'=>"Have any accidents or incidents occurred in the calendar month."],
  ],
  '9.0 Environmental Management' => [
    ['code'=>'9.1','q'=>"Has an aspects and impacts assessment been completed."],
    ['code'=>'9.2','q'=>"Has there been an ecology report completed."],
    ['code'=>'9.3','q'=>"Is there a facility to store fuel & hazardous substances onsite."],
    ['code'=>'9.4','q'=>"Have pollution and prevention controls onsite been provided."],
  ],
  '10.0 Waste Management' => [
    ['code'=>'10.1','q'=>"Have waste licences and permits been obtained."],
  ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Site Safety Tour</title>
<link rel="icon" href="/assets/img/favicon.png" type="image/png">
<style>
  :root{--bg:#111827;--card:#0f172a;--border:#1f2937;--text:#f9fafb;--muted:#94a3b8;--input:#0b1220;--radius:14px}
  *{box-sizing:border-box}
  body{background:var(--bg);color:var(--text);font-family:system-ui, sans-serif;margin:0}
  .wrap{max-width:1024px;margin:0 auto;padding:18px}
  h1{font-size:1.9rem;margin:0 0 12px}
  h2{font-size:1.15rem;margin:18px 0 8px}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin:12px 0}
  label{display:block;margin:8px 0 6px;color:var(--muted);font-weight:600}
  input,select,textarea{width:100%;background:var(--input);color:#e5e7eb;border:1px solid var(--border);border-radius:10px;padding:10px}
  textarea{min-height:90px;resize:vertical}
  .row2{display:grid;gap:12px;grid-template-columns:1fr 1fr;align-items:start}
  .row3{display:grid;gap:12px;grid-template-columns:1.4fr 1fr 0.8fr;align-items:start}
  .full{grid-column:1 / -1}
  @media (max-width:820px){ .row2,.row3{grid-template-columns:1fr} }
  .muted{color:var(--muted)}
  button{background:#0ea5e9;color:#00131a;font-weight:700;border:0;border-radius:10px;padding:12px 14px;cursor:pointer}
  .chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px}
  .chip{display:inline-flex;gap:6px;align-items:center;padding:6px 10px;border-radius:999px;background:var(--input);border:1px solid var(--border)}
  .chip button{background:transparent;border:0;color:var(--muted);cursor:pointer}

  /* Signature pad */
  .sig-wrap{background:#0b1220;border:1px dashed #334155;border-radius:12px;padding:10px}
  canvas#sig{display:block;width:100%;height:200px;background:#0000;border-radius:8px;touch-action:none}
  .sig-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:8px}
  .err{color:#fecaca;background:#3b0a0a33;border:1px solid #7f1d1d;padding:8px;border-radius:10px;margin-bottom:8px;display:none}
</style>
</head>
<body>
<div class="wrap">
  <h1>New Site Safety Tour</h1>

  <form action="submit.php" method="post" enctype="multipart/form-data" class="card" id="tourForm">
    <!-- Tour Scoring Section -->
    <!-- Allows manual score entry, though server will recalculate from responses -->
    <div class="row3" style="margin-bottom:10px">
      <div>
        <label>Score — Achieved</label>
        <input type="number" step="1" min="0" name="score_achieved" id="scoreA" placeholder="0">
      </div>
      <div>
        <label>Score — Total</label>
        <input type="number" step="1" min="0" name="score_total" id="scoreT" placeholder="0">
      </div>
      <div>
        <label>Percent</label>
        <input type="text" id="scoreP" name="score_percent" placeholder="Auto" readonly>
      </div>
    </div>

    <!-- Basic Tour Information -->
    <div class="row2">
      <div>
        <label>Project Name *</label>
        <input type="text" name="site" required placeholder="e.g., Rochdale Road">
      </div>
      <div>
        <label>Location *</label>
        <input type="text" name="area" required placeholder="e.g., Block 1">
      </div>
    </div>
    <div class="row2">
      <div>
        <label>Site Manager *</label>
        <input type="text" name="lead_name" required placeholder="e.g., Darrell Cullen">
      </div>
      <div>
        <label>Inspection Date &amp; Time *</label>
        <!-- Using datetime-local for consistent input, but displays will use UK format -->
        <input type="datetime-local" name="tour_date" required value="<?= date('Y-m-d\\TH:i') ?>">
      </div>
    </div>
    <label>Prepared by / Participants</label>
    <input type="text" name="participants" placeholder="e.g., Anthony Tetlow; others">

    <!-- Safety Checklist Questions -->
    <!-- Each section contains multiple questions that must be evaluated -->
    <?php 
    $questionIndex = 0; // Track question index for photo uploads
    foreach ($CHECKLIST as $section => $items): 
    ?>
      <div class="card">
        <h2><?= htmlspecialchars($section) ?></h2>
        <?php foreach ($items as $item): ?>
          <div class="card" style="border-color:#253044">
            <!-- Question Display -->
            <div class="full" style="font-weight:600">
              <span style="opacity:.8;margin-right:8px"><?= htmlspecialchars($item['code']) ?></span>
              <?= htmlspecialchars($item['q']) ?>
            </div>

            <!-- Hidden fields for server-side processing -->
            <!-- These allow submit.php to reconstruct the question details -->
            <input type="hidden" name="q_code[]" value="<?= htmlspecialchars($item['code']) ?>">
            <input type="hidden" name="q_text[]" value="<?= htmlspecialchars($item['q']) ?>">
            <input type="hidden" name="f_category[]" value="<?= htmlspecialchars($section) ?>">

            <!-- Question Response Fields -->
            <div class="row3" style="margin-top:10px">
              <div>
                <label>Result</label>
                <select name="check_status[]">
                  <option value="" disabled selected>Select</option>
                  <option>Pass</option>
                  <option>Improvement</option>
                  <option>Fail</option>
                  <option>N/A</option>
                </select>
              </div>
              <div>
                <label>Priority</label>
                <select name="f_severity[]">
                  <option value="" disabled selected>Priority</option>
                  <option value="Low">Low</option>
                  <option value="Medium">Medium</option>
                  <option value="High">High</option>
                </select>
              </div>
              <div>
                <label>Due (if action)</label>
                <input type="date" name="a_due[]">
              </div>
            </div>

            <!-- Action Planning Fields -->
            <div class="row2">
              <div>
                <label>Action / To do (if required)</label>
                <input type="text" name="a_action[]" placeholder="Describe the action required">
              </div>
              <div>
                <label>Responsible</label>
                <input type="text" name="a_resp[]" placeholder="Person(s) responsible">
              </div>
            </div>

            <!-- Evidence and Documentation -->
            <div class="full">
              <label>Notes / Evidence</label>
              <textarea name="f_note[]" placeholder="Observation, evidence, instruction…"></textarea>
              <div style="color:#9fb3c8;margin-top:6px">Attach photos for this question (optional)</div>
              <input type="file" name="qphotos[<?= $questionIndex ?>][]" accept="image/*" multiple>
            </div>
          </div>
        <?php 
        $questionIndex++; 
        endforeach; 
        ?>
      </div>
    <?php endforeach; ?>

    <!-- Recipients -->
    <?php
      $known = [];
      try {
        $st = db()->query("SELECT email, COALESCE(label,'') AS label, use_count FROM recipient_emails ORDER BY use_count DESC, last_used DESC LIMIT 50");
        $known = $st->fetchAll();
      } catch (Throwable $e) { $known = []; }
    ?>
    <div class="card">
      <strong>Recipients</strong>
      <div class="muted">Type an email and press Enter. Tap a chip to remove. Tap a “Previously used” address to add.</div>
      <div id="recChips" class="chips"></div>
      <input type="email" id="recInput" placeholder="name@company.com">
      <input type="hidden" name="recipients" id="recipients">
      <?php if ($known): ?>
        <div style="margin-top:10px">
          <div class="muted" style="margin-bottom:6px">Previously used</div>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($known as $k): ?>
              <button type="button" class="rec-suggest"
                      data-email="<?= h($k['email']) ?>"
                      style="border:1px solid var(--border);background:#0b1220;color:#e5e7eb;border-radius:999px;padding:6px 10px;cursor:pointer">
                <?= h($k['label'] ?: $k['email']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Extra photos (any section) -->
    <div class="row2">
      <div><label>Extra photos (optional, any section)</label><input type="file" name="photos[]" accept="image/*" multiple></div>
    </div>

    <!-- Signature (required) -->
    <div class="card">
      <strong>Signature (required)</strong>
      <div id="sigErr" class="err">Please sign in the box below (or upload a signature image).</div>
      <div class="sig-wrap">
        <canvas id="sig"></canvas>
      </div>
      <div class="sig-actions">
        <input type="file" name="signature_file" accept="image/*">
        <button type="button" id="sigClear">Clear</button>
      </div>
      <input type="hidden" name="signature_data" id="signature_data">
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:12px">
      <button type="submit">Save &amp; Email PDF</button>
    </div>
  </form>
</div>

<script>
/**
 * Form JavaScript Functionality
 * 
 * This script provides interactive features for the safety tour form:
 * 1. Automatic score percentage calculation
 * 2. Email recipient management with chips
 * 3. Canvas-based signature capture
 */

// Score Percentage Calculator
// Automatically calculates and displays percentage when achieved/total scores are entered
(function() {
  const achievedInput = document.getElementById('scoreA');
  const totalInput = document.getElementById('scoreT'); 
  const percentInput = document.getElementById('scoreP');
  
  function updatePercentage() {
    const achieved = parseInt(achievedInput.value) || 0;
    const total = parseInt(totalInput.value) || 0;
    percentInput.value = total > 0 ? (achieved / total * 100).toFixed(2) + '%' : '';
  }
  
  achievedInput.addEventListener('input', updatePercentage);
  totalInput.addEventListener('input', updatePercentage);
})();

// Email Recipients Management
// Handles adding/removing email addresses as chips
(function() {
  const emailSet = new Set();
  const chipsContainer = document.getElementById('recChips');
  const emailInput = document.getElementById('recInput');
  const hiddenField = document.getElementById('recipients');

  function renderChips() {
    chipsContainer.innerHTML = '';
    [...emailSet].forEach(email => {
      const chip = document.createElement('span');
      chip.className = 'chip';
      chip.textContent = email + ' ';
      
      const removeButton = document.createElement('button');
      removeButton.type = 'button';
      removeButton.textContent = '×';
      removeButton.onclick = () => {
        emailSet.delete(email);
        renderChips();
      };
      
      chip.appendChild(removeButton);
      chipsContainer.appendChild(chip);
    });
    hiddenField.value = [...emailSet].join(',');
  }
  
  function addEmail(emailText) {
    const email = (emailText || '').trim();
    if (!email) return;
    
    // Basic email validation
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      emailInput.value = '';
      return;
    }
    
    emailSet.add(email.toLowerCase());
    emailInput.value = '';
    renderChips();
  }
  
  // Add email on Enter key press
  emailInput.addEventListener('keydown', e => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addEmail(emailInput.value);
    }
  });
  
  // Handle previously used email suggestions
  document.querySelectorAll('.rec-suggest').forEach(button => {
    button.addEventListener('click', () => addEmail(button.dataset.email || ''));
  });
})();

// Digital Signature Capture System
// Provides canvas-based signature drawing with touch/mouse support
(function() {
  const canvas = document.getElementById('sig');
  const dataField = document.getElementById('signature_data');
  const errorDiv = document.getElementById('sigErr');
  const clearButton = document.getElementById('sigClear');
  
  let isDrawing = false;
  let hasDrawn = false;
  let context;

  // Initialize canvas with proper sizing and styling
  function initializeCanvas() {
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(600, rect.width * 2); // High resolution for crisp output
    canvas.height = 300;
    
    context = canvas.getContext('2d');
    context.lineWidth = 3;
    context.lineCap = 'round';
    context.strokeStyle = '#e5e7eb'; // Light color for visibility
    context.clearRect(0, 0, canvas.width, canvas.height);
    
    hasDrawn = false;
    dataField.value = '';
  }
  
  // Convert mouse/touch coordinates to canvas coordinates
  function getCanvasPosition(event) {
    const rect = canvas.getBoundingClientRect();
    const clientX = event.touches ? event.touches[0].clientX : event.clientX;
    const clientY = event.touches ? event.touches[0].clientY : event.clientY;
    
    const x = (clientX - rect.left);
    const y = (clientY - rect.top);
    
    // Scale to canvas resolution
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    
    return { x: x * scaleX, y: y * scaleY };
  }
  
  // Start drawing
  function startDrawing(event) {
    isDrawing = true;
    hasDrawn = true;
    
    const pos = getCanvasPosition(event);
    context.beginPath();
    context.moveTo(pos.x, pos.y);
    
    event.preventDefault();
  }
  
  // Continue drawing
  function continueDrawing(event) {
    if (!isDrawing) return;
    
    const pos = getCanvasPosition(event);
    context.lineTo(pos.x, pos.y);
    context.stroke();
    
    event.preventDefault();
  }
  
  // Stop drawing and save signature data
  function stopDrawing() {
    isDrawing = false;
    
    if (hasDrawn) {
      // Convert canvas to PNG data URL for form submission
      dataField.value = canvas.toDataURL('image/png');
    }
  }

  // Initialize canvas on page load and window resize
  initializeCanvas();
  window.addEventListener('resize', initializeCanvas);
  
  // Mouse event handlers
  canvas.addEventListener('mousedown', startDrawing);
  canvas.addEventListener('mousemove', continueDrawing);
  window.addEventListener('mouseup', stopDrawing);
  
  // Touch event handlers (for mobile devices)
  canvas.addEventListener('touchstart', startDrawing, { passive: false });
  canvas.addEventListener('touchmove', continueDrawing, { passive: false });
  canvas.addEventListener('touchend', stopDrawing);

  // Clear signature button
  clearButton.addEventListener('click', () => {
    initializeCanvas();
    errorDiv.style.display = 'none';
  });

  // Form validation - require signature before submission
  document.getElementById('tourForm').addEventListener('submit', function(event) {
    const hasCanvasSignature = dataField.value && dataField.value.startsWith('data:image/');
    const hasUploadedSignature = document.querySelector('input[name="signature_file"]').files[0];
    
    if (!hasCanvasSignature && !hasUploadedSignature) {
      errorDiv.style.display = 'block';
      event.preventDefault(); // Prevent form submission
    }
  });
})();
</script>
</body>
</html>
