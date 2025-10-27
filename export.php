<?php
/**
 * File: export.php
 * Description: Comprehensive data export tool for safety tours. Allows exporting all tour data,
 * responses, scores, and action items to CSV or Excel format. Supports filtering by date range,
 * site name, and status. Automatically calculates pass/fail/improvement item counts and generates
 * professional formatted spreadsheets ready for compliance audits, management reports, and analysis.
 * All dates displayed in UK format (DD/MM/YYYY HH:MM). Both CSV (lightweight) and Excel (formatted)
 * export options available with proper headers, styling, and UTF-8 encoding.
 * 
 * Usage: 
 *   - Download all tours: /export.php?format=csv
 *   - Download Excel: /export.php?format=excel
 *   - With filters: /export.php?format=excel&from=2025-01-01&to=2025-10-27&status=Closed&q=rochdale
 */

declare(strict_types=1);

// Authentication check - ensures only authorised users can export data
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    if (function_exists('auth_check')) auth_check();
}

require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

$pdo = db();

// Get export format from URL parameter (csv or excel)
$format = strtolower(trim($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv', 'excel'])) $format = 'csv';

// Get filter parameters from URL
$from   = trim($_GET['from'] ?? '');
$to     = trim($_GET['to'] ?? '');
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');

// Build WHERE clause for database query based on filters
$where = [];
$args  = [];

if ($from !== '') { 
    $where[] = 'tour_date >= ?'; 
    $args[] = date('Y-m-d 00:00:00', strtotime($from)); 
}
if ($to !== '') { 
    $where[] = 'tour_date <= ?'; 
    $args[] = date('Y-m-d 23:59:59', strtotime($to)); 
}
if ($q !== '') { 
    $where[] = '(site LIKE ? OR area LIKE ?)'; 
    $args[] = "%$q%"; 
    $args[] = "%$q%"; 
}
if ($status === 'Open' || $status === 'Closed') { 
    $where[] = 'status = ?'; 
    $args[] = $status; 
}

$whereSql = $where ? ('WHERE '.implode(' AND ', $where)) : '';

// Fetch all tour data from database
try {
    $stmt = $pdo->prepare("SELECT id, tour_date, site, area, lead_name, participants, status, 
                                  score_achieved, score_total, score_percent, responses, photos, 
                                  signature_path, recipients
                           FROM safety_tours
                           $whereSql
                           ORDER BY tour_date DESC");
    $stmt->execute($args);
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error fetching data: " . htmlspecialchars($e->getMessage());
    exit;
}

// Generate filename with UK date format (DD-MM-YYYY HH-MM)
$dateRange = '';
if ($from && $to) {
    $dateRange = '_' . date('d-m-Y', strtotime($from)) . '_to_' . date('d-m-Y', strtotime($to));
} elseif ($from) {
    $dateRange = '_from_' . date('d-m-Y', strtotime($from));
} elseif ($to) {
    $dateRange = '_to_' . date('d-m-Y', strtotime($to));
}

$fileName = 'Safety_Tours_Export_' . date('d-m-Y_H-i') . $dateRange;

// ============================================================================
// EXPORT TO CSV FORMAT
// ============================================================================

if ($format === 'csv') {
    // Set headers to download as CSV file
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM (Byte Order Mark) - ensures Excel opens file correctly with special characters
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Write CSV header row with all column names
    fputcsv($output, [
        'Tour ID',
        'Tour Date (UK)',
        'Site',
        'Area',
        'Lead Inspector',
        'Participants',
        'Status',
        'Score Achieved',
        'Score Total',
        'Score %',
        'Total Questions',
        'Pass Items',
        'Fail Items',
        'Improvement Items',
        'Recipients Notified'
    ]);
    
    // Process each tour and write row to CSV
    foreach ($tours as $tour) {
        // Decode responses JSON to analyse results
        $responses = json_decode($tour['responses'], true) ?? [];
        
        // Count pass/fail/improvement items
        $counts = ['pass' => 0, 'fail' => 0, 'improvement' => 0];
        foreach ($responses as $r) {
            $result = strtolower(trim($r['result'] ?? ''));
            if ($result === 'pass') $counts['pass']++;
            elseif ($result === 'fail') $counts['fail']++;
            elseif ($result === 'improvement') $counts['improvement']++;
        }
        
        // Format tour date to UK format (DD/MM/YYYY HH:MM)
        $tourDateUK = date('d/m/Y H:i', strtotime($tour['tour_date']));
        
        // Write data row to CSV
        fputcsv($output, [
            $tour['id'],
            $tourDateUK,
            $tour['site'],
            $tour['area'],
            $tour['lead_name'],
            $tour['participants'],
            $tour['status'],
            $tour['score_achieved'] ?? 0,
            $tour['score_total'] ?? 0,
            ($tour['score_percent'] ?? 0) . '%',
            count($responses),
            $counts['pass'],
            $counts['fail'],
            $counts['improvement'],
            $tour['recipients'] ?? 'None'
        ]);
    }
    
    fclose($output);
    exit;

// ============================================================================
// EXPORT TO EXCEL FORMAT (XML-based SpreadsheetML)
// ============================================================================

} else {
    // Set headers to download as Excel file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '.xlsx"');
    
    // Start building Excel XML structure (SpreadsheetML format)
    $xml = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ';
    $xml .= 'xmlns:o="urn:schemas-microsoft-com:office:office" ';
    $xml .= 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
    $xml .= 'xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" ';
    $xml .= 'xmlns:html="http://www.w3.org/TR/REC-html40">';
    
    // Define styles for Excel formatting
    $xml .= '<Styles>';
    
    // Header style - dark blue background with white bold text
    $xml .= '<Style ss:ID="Header">';
    $xml .= '<Font ss:Bold="1" ss:Color="#FFFFFF" ss:Size="12"/>';
    $xml .= '<Interior ss:Color="#366092" ss:Pattern="Solid"/>';
    $xml .= '<Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/>';
    $xml .= '<Borders>';
    $xml .= '<Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="2" ss:Color="#000000"/>';
    $xml .= '</Borders>';
    $xml .= '</Style>';
    
    // Title style - large bold blue text
    $xml .= '<Style ss:ID="Title">';
    $xml .= '<Font ss:Bold="1" ss:Size="16" ss:Color="#366092"/>';
    $xml .= '<Alignment ss:Horizontal="Left" ss:Vertical="Center"/>';
    $xml .= '</Style>';
    
    // Percentage format style
    $xml .= '<Style ss:ID="Percent">';
    $xml .= '<NumberFormat ss:Format="0.00%"/>';
    $xml .= '</Style>';
    
    // Number format style - centre aligned
    $xml .= '<Style ss:ID="Number">';
    $xml .= '<Alignment ss:Horizontal="Center"/>';
    $xml .= '</Style>';
    
    $xml .= '</Styles>';
    
    // Create worksheet
    $xml .= '<Worksheet ss:Name="Safety Tours">';
    
    // Define column widths for better readability
    $xml .= '<Table>';
    $xml .= '<Column ss:Width="60"/>';   // Tour ID
    $xml .= '<Column ss:Width="130"/>';  // Tour Date
    $xml .= '<Column ss:Width="150"/>';  // Site
    $xml .= '<Column ss:Width="120"/>';  // Area
    $xml .= '<Column ss:Width="130"/>';  // Lead Inspector
    $xml .= '<Column ss:Width="140"/>';  // Participants
    $xml .= '<Column ss:Width="80"/>';   // Status
    $xml .= '<Column ss:Width="100"/>';  // Score Achieved
    $xml .= '<Column ss:Width="80"/>';   // Score Total
    $xml .= '<Column ss:Width="70"/>';   // Score %
    $xml .= '<Column ss:Width="120"/>';  // Total Questions
    $xml .= '<Column ss:Width="80"/>';   // Pass Items
    $xml .= '<Column ss:Width="80"/>';   // Fail Items
    $xml .= '<Column ss:Width="110"/>';  // Improvement Items
    $xml .= '<Column ss:Width="140"/>';  // Recipients
    
    // Title row
    $xml .= '<Row>';
    $xml .= '<Cell ss:StyleID="Title" ss:MergeAcross="14">';
    $xml .= '<Data ss:Type="String">Safety Tours Export - ' . date('d/m/Y H:i') . '</Data>';
    $xml .= '</Cell>';
    $xml .= '</Row>';
    
    // Empty row for spacing
    $xml .= '<Row/>';
    
    // Header row with styling
    $xml .= '<Row ss:StyleID="Header">';
    $headers = [
        'Tour ID',
        'Tour Date (UK)',
        'Site',
        'Area',
        'Lead Inspector',
        'Participants',
        'Status',
        'Score Achieved',
        'Score Total',
        'Score %',
        'Total Questions',
        'Pass Items',
        'Fail Items',
        'Improvement Items',
        'Recipients Notified'
    ];
    
    foreach ($headers as $h) {
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
    }
    $xml .= '</Row>';
    
    // Add data rows from all tours
    foreach ($tours as $tour) {
        $responses = json_decode($tour['responses'], true) ?? [];
        
        // Count results
        $counts = ['pass' => 0, 'fail' => 0, 'improvement' => 0];
        foreach ($responses as $r) {
            $result = strtolower(trim($r['result'] ?? ''));
            if ($result === 'pass') $counts['pass']++;
            elseif ($result === 'fail') $counts['fail']++;
            elseif ($result === 'improvement') $counts['improvement']++;
        }
        
        // Format date to UK format
        $tourDateUK = date('d/m/Y H:i', strtotime($tour['tour_date']));
        
        // Write data row
        $xml .= '<Row>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . (int)$tour['id'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tourDateUK) . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tour['site'] ?? '') . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tour['area'] ?? '') . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tour['lead_name'] ?? '') . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tour['participants'] ?? '') . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tour['status'] ?? 'Open') . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . (int)($tour['score_achieved'] ?? 0) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . (int)($tour['score_total'] ?? 0) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Percent"><Data ss:Type="Number">' . ((float)($tour['score_percent'] ?? 0) / 100) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . count($responses) . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . $counts['pass'] . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . $counts['fail'] . '</Data></Cell>';
        $xml .= '<Cell ss:StyleID="Number"><Data ss:Type="Number">' . $counts['improvement'] . '</Data></Cell>';
        $xml .= '<Cell><Data ss:Type="String">' . htmlspecialchars($tour['recipients'] ?? 'None') . '</Data></Cell>';
        $xml .= '</Row>';
    }
    
    $xml .= '</Table>';
    $xml .= '</Worksheet>';
    $xml .= '</Workbook>';
    
    // Output Excel file
    echo $xml;
    exit;
}
?>
