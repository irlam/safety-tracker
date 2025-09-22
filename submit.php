<?php
/**
 * File: submit.php
 * 
 * Safety Tour Form Submission Handler
 * 
 * This file processes form submissions from the safety tour entry form (form.php).
 * It handles the complete workflow of:
 * - Validating and saving form data to the database
 * - Processing file uploads (photos, signatures)
 * - Generating PDF reports
 * - Sending email notifications to recipients
 * - Redirecting to success page with status indicators
 * 
 * All data validation, file handling, and email distribution happens here.
 * Dates and times are processed and stored in UK format (Europe/London timezone).
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

// Load core functions and establish database connection
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

/**
 * =============================================================================
 * UTILITY FUNCTIONS
 * =============================================================================
 * Helper functions for data processing, file handling, and database operations
 */

/**
 * Create a URL-friendly slug from a string
 * Converts text to lowercase, replaces special characters with hyphens
 * 
 * @param string $text The input text to convert
 * @return string The slugified text, defaults to 'tour' if empty
 */
function createSlug(string $text): string 
{
    $slug = strtolower(trim(preg_replace('~[^a-z0-9]+~i', '-', $text), '-'));
    return $slug !== '' ? $slug : 'tour';
}

/**
 * Get column names for a database table
 * Uses information_schema to retrieve all column names safely
 * 
 * @param PDO $pdo Database connection
 * @param string $tableName Name of the table to examine
 * @return array List of column names, empty array if query fails
 */
function getTableColumns(PDO $pdo, string $tableName): array 
{
    try {
        $statement = $pdo->prepare(
            "SELECT COLUMN_NAME FROM information_schema.columns 
             WHERE table_schema = DATABASE() AND table_name = ?"
        );
        $statement->execute([$tableName]);
        return array_map(fn($row) => $row['COLUMN_NAME'], $statement->fetchAll());
    } catch (Throwable $e) { 
        return []; 
    }
}

/**
 * Check if a specific column exists in a database table
 * Uses caching to avoid repeated database queries
 * 
 * @param PDO $pdo Database connection
 * @param string $tableName Name of the table
 * @param string $columnName Name of the column to check
 * @return bool True if column exists, false otherwise
 */
function columnExists(PDO $pdo, string $tableName, string $columnName): bool 
{
    static $columnCache = [];
    
    $cacheKey = $tableName;
    if (!isset($columnCache[$cacheKey])) {
        $columnCache[$cacheKey] = getTableColumns($pdo, $tableName);
    }
    
    return in_array($columnName, $columnCache[$cacheKey] ?? [], true);
}

/**
 * Save signature data from canvas to file system
 * Processes base64-encoded image data and saves as PNG file
 * 
 * @param string $dataUrl Base64-encoded image data URL from canvas
 * @return string|null Web-relative path to saved file, null if failed
 */
function saveSignatureData(string $dataUrl): ?string 
{
    // Validate data URL format (PNG or JPEG)
    if (!preg_match('~^data:image/(png|jpeg);base64,~', $dataUrl)) {
        return null;
    }
    
    // Extract and decode base64 data
    $imageData = base64_decode(
        preg_replace('~^data:image/\w+;base64,~', '', $dataUrl), 
        true
    );
    
    if ($imageData === false) {
        return null;
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDirectory = __DIR__ . '/uploads/signatures';
    if (!is_dir($uploadDirectory)) {
        @mkdir($uploadDirectory, 0775, true);
    }
    
    // Generate unique filename and save file
    $fileName = 'sig_' . uniqid('', true) . '.png';
    $filePath = $uploadDirectory . '/' . $fileName;
    
    if (@file_put_contents($filePath, $imageData) === false) {
        return null;
    }
    
    // Return web-relative path for database storage
    return 'uploads/signatures/' . $fileName;
}

/**
 * =============================================================================
 * SAFETY CHECKLIST REFERENCE
 * =============================================================================
 * 
 * Complete safety checklist matching form.php structure exactly.
 * Used for server-side reconstruction of question codes and text during processing.
 * This ensures data integrity and allows for proper PDF generation and validation.
 * 
 * Note: This must be kept in sync with the checklist in form.php
 */
$SAFETY_CHECKLIST = [
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

// Flatten checklist structure to align with posted form arrays
// This creates a simple array that matches the order of form inputs
$flattenedChecklist = [];
foreach ($SAFETY_CHECKLIST as $section => $items) {
    foreach ($items as $item) {
        $flattenedChecklist[] = [
            'section' => $section, 
            'code' => $item['code'], 
            'q' => $item['q']
        ];
    }
}

/**
 * =============================================================================
 * FORM DATA PROCESSING
 * =============================================================================
 * Extract and validate all form data submitted from the safety tour form
 */

// Basic tour information
$siteName = trim($_POST['site'] ?? '');
$areaLocation = trim($_POST['area'] ?? '');
$leadName = trim($_POST['lead_name'] ?? '');
$participantsList = trim($_POST['participants'] ?? '');

// Process tour date - convert to UK timezone and proper format
$tourDateInput = (string)($_POST['tour_date'] ?? '');
$tourDate = $tourDateInput 
    ? date('Y-m-d H:i:s', strtotime($tourDateInput)) 
    : date('Y-m-d H:i:s'); // Default to current UK time

// Question response arrays (from form checkboxes/dropdowns/inputs)
$checkStatuses = $_POST['check_status'] ?? []; // Pass/Fail/Improvement/N/A
$severityLevels = $_POST['f_severity'] ?? [];   // Low/Medium/High
$actionDueDates = $_POST['a_due'] ?? [];        // Due dates for actions
$actionDescriptions = $_POST['a_action'] ?? []; // What needs to be done
$responsiblePersons = $_POST['a_resp'] ?? [];   // Who is responsible
$observationNotes = $_POST['f_note'] ?? [];     // Notes and evidence
$questionCategories = $_POST['f_category'] ?? []; // Section categories

// Manual scoring (server will recalculate anyway)
$scoreAchieved = isset($_POST['score_achieved']) ? (int)$_POST['score_achieved'] : null;
$scoreTotal = isset($_POST['score_total']) ? (int)$_POST['score_total'] : null;
$scorePercent = null;
if (isset($_POST['score_percent']) && $_POST['score_percent'] !== '') {
    $scorePercent = (float)str_replace('%', '', $_POST['score_percent']);
}

// Email recipients processing
$recipientsRaw = trim((string)($_POST['recipients'] ?? ''));
$emailRecipients = [];
if ($recipientsRaw !== '') {
    foreach (explode(',', $recipientsRaw) as $email) {
        $email = strtolower(trim($email));
        if ($email && preg_match('~^[^@\s]+@[^@\s]+\.[^@\s]+$~', $email)) {
            $emailRecipients[] = $email;
        }
    }
    $emailRecipients = array_values(array_unique($emailRecipients));
}

/**
 * =============================================================================
 * SIGNATURE PROCESSING
 * =============================================================================
 * Handle signature validation and file saving (required field)
 */
$signaturePath = null;
$hasUploadedFile = !empty($_FILES['signature_file']['tmp_name']) && 
                   is_uploaded_file($_FILES['signature_file']['tmp_name']);
$hasCanvasData = isset($_POST['signature_data']) && 
                 strpos($_POST['signature_data'], 'data:image/') === 0;

if ($hasUploadedFile) {
    // Process uploaded signature file
    $signaturePath = save_file($_FILES['signature_file'], 'signatures');
} elseif ($hasCanvasData) {
    // Process canvas-drawn signature
    $signaturePath = saveSignatureData((string)$_POST['signature_data']);
}

// Signature is required - fail if neither method provided valid signature
if (!$signaturePath) {
    http_response_code(400);
    echo "Signature required - please sign the form or upload a signature image.";
    exit;
}

/**
 * =============================================================================
 * FILE UPLOAD PROCESSING
 * =============================================================================
 * Handle photo uploads for individual questions and general tour photos
 */

// Process per-question photo uploads
// These are organized as qphotos[questionIndex][fileIndex]
$questionPhotos = []; // Will store: questionIndex => [file_paths...]

if (!empty($_FILES['qphotos']['name']) && is_array($_FILES['qphotos']['name'])) {
    foreach ($_FILES['qphotos']['name'] as $questionIndex => $fileNames) {
        if (!is_array($fileNames)) continue;
        
        foreach ($fileNames as $fileIndex => $fileName) {
            // Reconstruct individual file upload array for processing
            $fileUpload = [
                'name'     => $_FILES['qphotos']['name'][$questionIndex][$fileIndex] ?? '',
                'type'     => $_FILES['qphotos']['type'][$questionIndex][$fileIndex] ?? '',
                'tmp_name' => $_FILES['qphotos']['tmp_name'][$questionIndex][$fileIndex] ?? '',
                'error'    => $_FILES['qphotos']['error'][$questionIndex][$fileIndex] ?? 0,
                'size'     => $_FILES['qphotos']['size'][$questionIndex][$fileIndex] ?? 0,
            ];
            
            // Skip if no file was uploaded
            if (empty($fileUpload['tmp_name'])) continue;
            
            // Save file and store relative path
            $savedPath = save_file($fileUpload, 'tours');
            if ($savedPath) {
                $questionPhotos[(int)$questionIndex][] = $savedPath;
            }
        }
    }
}

// Process general tour photos (not linked to specific questions)
$generalPhotos = [];
if (!empty($_FILES['photos']['name'][0] ?? '')) {
    foreach ($_FILES['photos']['name'] as $index => $fileName) {
        // Reconstruct individual file upload array
        $fileUpload = [
            'name'     => $_FILES['photos']['name'][$index] ?? '',
            'type'     => $_FILES['photos']['type'][$index] ?? '',
            'tmp_name' => $_FILES['photos']['tmp_name'][$index] ?? '',
            'error'    => $_FILES['photos']['error'][$index] ?? 0,
            'size'     => $_FILES['photos']['size'][$index] ?? 0,
        ];
        
        // Skip if no file was uploaded
        if (empty($fileUpload['tmp_name'])) continue;
        
        // Save file and store relative path
        $savedPath = save_file($fileUpload, 'tours');
        if ($savedPath) {
            $generalPhotos[] = $savedPath;
        }
    }
}

/**
 * =============================================================================
 * RESPONSE DATA COMPILATION
 * =============================================================================
 * Build complete response array by combining form data with checklist structure
 */

// Build comprehensive responses array aligned with the flattened checklist
$tourResponses = [];

// Determine maximum array length to handle all form inputs safely
$maxResponses = max(
    count($flattenedChecklist),
    count($checkStatuses), 
    count($severityLevels), 
    count($actionDueDates),
    count($actionDescriptions), 
    count($responsiblePersons), 
    count($observationNotes), 
    count($questionCategories)
);

// Build response for each question in the checklist
for ($index = 0; $index < $maxResponses; $index++) {
    // Get checklist metadata (section, code, question text)
    $checklistItem = $flattenedChecklist[$index] ?? [
        'section' => $questionCategories[$index] ?? '', 
        'code' => '', 
        'q' => ''
    ];
    
    // Compile complete response data for this question
    $tourResponses[] = [
        'section'     => (string)($checklistItem['section'] ?? ($questionCategories[$index] ?? '')),
        'code'        => (string)($checklistItem['code'] ?? ''),
        'question'    => (string)($checklistItem['q'] ?? ''),
        'result'      => (string)($checkStatuses[$index] ?? ''),        // Pass/Fail/Improvement/N/A
        'priority'    => (string)($severityLevels[$index] ?? ''),       // Low/Medium/High
        'note'        => (string)($observationNotes[$index] ?? ''),     // Observations and evidence
        'action'      => (string)($actionDescriptions[$index] ?? ''),   // Required actions
        'responsible' => (string)($responsiblePersons[$index] ?? ''),   // Who is responsible
        'due'         => (string)($actionDueDates[$index] ?? ''),       // When action is due
        'images'      => array_values($questionPhotos[$index] ?? []),   // Associated photos
    ];
}

/**
 * =============================================================================
 * AUTOMATIC SCORING CALCULATION
 * =============================================================================
 * Calculate tour score based on Pass/Fail/Improvement responses
 */

// Calculate automatic score if manual scores weren't provided
$autoScoreData = tally_score($tourResponses);

if ($scoreAchieved === null || $scoreTotal === null) {
    // Use automatic calculation based on response counts
    $scoreTotal = $autoScoreData['counts']['pass'] + 
                  $autoScoreData['counts']['fail'] + 
                  $autoScoreData['counts']['improvement'];
    $scoreAchieved = $autoScoreData['counts']['pass'];
}

// Calculate percentage if not manually provided
if ($scorePercent === null) {
    $scorePercent = $scoreTotal > 0 
        ? round(($scoreAchieved / $scoreTotal) * 100, 2) 
        : null;
}

/**
 * =============================================================================
 * DATABASE OPERATIONS
 * =============================================================================
 * Save tour data to database with dynamic column checking
 */

// Get available columns in safety_tours table for dynamic insertion
$availableColumns = getTableColumns($pdo, 'safety_tours');

// Prepare data for database insertion
$tourData = [
    'tour_date'      => $tourDate,                                              // UK formatted date/time
    'site'           => $siteName,                                              // Project name
    'area'           => $areaLocation,                                          // Location/area
    'lead_name'      => $leadName,                                              // Site manager name
    'participants'   => $participantsList,                                     // Other participants
    'responses'      => json_encode($tourResponses, JSON_UNESCAPED_UNICODE),   // All question responses
    'photos'         => json_encode($generalPhotos, JSON_UNESCAPED_UNICODE),   // General photos
    'signature_path' => $signaturePath,                                        // Signature file path
    'status'         => 'Open',                                                // Default status
    'score_achieved' => $scoreAchieved,                                        // Points achieved
    'score_total'    => $scoreTotal,                                           // Total possible points
    'score_percent'  => $scorePercent,                                         // Percentage score
    'recipients'     => $emailRecipients ? implode(',', $emailRecipients) : null, // Email list
];

// Build dynamic INSERT query using only existing columns
$insertColumns = [];
$insertPlaceholders = [];
$insertValues = [];

foreach ($tourData as $columnName => $value) {
    if (in_array($columnName, $availableColumns, true)) {
        $insertColumns[] = "`{$columnName}`";
        $insertPlaceholders[] = "?";
        $insertValues[] = $value;
    }
}

// Execute database insertion
$insertSql = "INSERT INTO `safety_tours` (" . implode(',', $insertColumns) . ") 
              VALUES (" . implode(',', $insertPlaceholders) . ")";
$insertStatement = $pdo->prepare($insertSql);
$insertStatement->execute($insertValues);

// Get the ID of the newly created tour record
$tourId = (int)$pdo->lastInsertId();

/* -------------------------------------------------------
   Save/refresh recipient directory (table: safety_recipient_emails)
--------------------------------------------------------*/
if ($recipients) {
  foreach ($recipients as $em) {
    try {
      $pdo->prepare("
        INSERT INTO safety_recipient_emails (email, label, use_count, last_used)
        VALUES (?, '', 1, NOW())
        ON DUPLICATE KEY UPDATE use_count = use_count+1, last_used = NOW()
      ")->execute([$em]);
    } catch (Throwable $e) { /* non-fatal */ }
  }
}

/* -------------------------------------------------------
   PDF + Email
--------------------------------------------------------*/
$pdfOK = 0; $mailOK = 0;

// UK file name e.g. SafetyTour_rochdale-road_21-09-2025_14-30.pdf
$ukDate  = date('d-m-Y_H-i', strtotime($tour_date));
$slug    = hslug($site);
$dir     = __DIR__ . '/uploads/tours/' . $id;
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$outPath = $dir . '/SafetyTour_' . $slug . '_' . $ukDate . '.pdf';

try {
  // re-fetch row to feed renderer (ensures DB formatting)
  $tour = $pdo->prepare("SELECT * FROM safety_tours WHERE id=?");
  $tour->execute([$id]);
  $row = $tour->fetch();
  render_pdf($row, $outPath);
  $pdfOK = 1;
} catch (Throwable $e) {
  error_log('PDF: '.$e->getMessage());
}

$toSend = $recipients ?: [SMTP_USER]; // fallback to your mailbox so nothing is lost
try {
  $mailOK = send_mail_multi(
    $toSend,
    'Safety Tour #'.$id.' — '.$site,
    '<p>Safety Tour <strong>#'.$id.'</strong> for <strong>'.htmlspecialchars($site).'</strong> ('.$ukDate.').</p>'.
    '<p><a href="'.htmlspecialchars((($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.$_SERVER['HTTP_HOST']).'/pdf.php?id='.$id).'">Open PDF</a></p>',
    [['path'=>$outPath, 'name'=>basename($outPath)]]
  ) ? 1 : 0;
} catch (Throwable $e) {
  error_log('MAIL: '.$e->getMessage());
}

/* -------------------------------------------------------
   Done → success page
--------------------------------------------------------*/
header('Location: success.php?id='.$id.'&pdf='.$pdfOK.'&mail='.$mailOK);
exit;
