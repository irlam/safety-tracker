<?php
/**
 * ============================================================================
 * FILE NAME: analytics.php
 * ============================================================================
 * 
 * DESCRIPTION:
 * Professional Analytics and Business Intelligence Dashboard - Displays
 * comprehensive insights, trends, patterns, and statistics from all safety
 * tours. Features interactive Chart.js visualizations including line charts,
 * pie charts, and bar charts showing monthly trends, risk analysis by
 * location, recurring issue identification, and safety compliance metrics.
 * Allows users to view data for different time periods (last 7 days, 30 days,
 * 90 days, or 365 days). Displays key statistics cards with colour-coded
 * metrics, professional data tables with risk heatmaps, and top recurring
 * issues analysis. All dates are displayed in UK format (DD/MM/YYYY HH:MM:SS).
 * Includes export buttons to download data as CSV or Excel. Only authenticated
 * users can access - login required.
 * 
 * WHAT THIS FILE DOES:
 * 1. Verifies user is logged in (requires authentication)
 * 2. Gets time period filter from URL (7/30/90/365 days)
 * 3. Fetches overall statistics (total tours, average score, pass/fail counts)
 * 4. Fetches top 15 recurring failure issues with frequency counts
 * 5. Fetches tours by site location for risk heatmap analysis
 * 6. Fetches monthly trend data for line chart visualization
 * 7. Fetches status distribution (Open vs Closed tours)
 * 8. Fetches risk score distribution by percentage range
 * 9. Renders professional HTML dashboard with interactive charts
 * 10. Displays beautiful statistics cards, data tables, risk badges
 * 11. Includes export buttons to download analytics as CSV or Excel
 * 12. Mobile responsive design - works on all devices
 * 
 * HOW TO USE:
 * View analytics dashboard: https://safety.defecttracker.uk/analytics.php
 * View last 7 days: https://safety.defecttracker.uk/analytics.php?period=week
 * View last 30 days: https://safety.defecttracker.uk/analytics.php?period=month
 * View last 90 days: https://safety.defecttracker.uk/analytics.php?period=quarter
 * View last 365 days: https://safety.defecttracker.uk/analytics.php?period=year
 * 
 * FEATURES:
 * - 4 Interactive charts (line, doughnut, bar, horizontal bar)
 * - 5 Statistics cards (total tours, avg score, passes, fails, completion rate)
 * - Time period selector with 4 options (7/30/90/365 days)
 * - Monthly trend analysis with dual-axis chart
 * - Risk distribution analysis (Low/Medium/Good/Excellent)
 * - Status distribution (Open vs Closed)
 * - Top 15 recurring failure issues with visual progress bars
 * - Risk heatmap by site location with colour-coded risk levels
 * - Export buttons (CSV and Excel)
 * - Professional styling with gradients and shadows
 * - Mobile responsive design
 * - UK date/time format throughout
 * - Dark theme with modern colour scheme
 * 
 * CREATED: 27/10/2025 13:59:20 (UK Time)
 * LAST MODIFIED: 27/10/2025 13:59:20 (UK Time)
 * CREATED BY: irlam
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// SECTION 1: INITIALIZATION - TIMEZONE & AUTHENTICATION
// ============================================================================

// Set timezone to Europe/London (UK Time)
// All dates will automatically be formatted in UK timezone (GMT/BST)
// Current date/time: 27/10/2025 13:59:20 (UK Time)
date_default_timezone_set('Europe/London');

// ============================================================================
// Load authentication system to verify user is logged in
// ============================================================================
// Check if authentication file exists in includes directory
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    // If auth_check() function exists, it will verify user is logged in
    // If user is NOT logged in, it automatically redirects to login page
    // This ensures only authorized users can view analytics
    if (function_exists('auth_check')) {
        auth_check();
    }
}

// ============================================================================
// Load shared database functions and connection
// ============================================================================
// This file contains database helper functions like db() for connection
require_once __DIR__ . '/includes/functions.php';

// Get database connection - returns PDO object
// This is used to query the safety_tours table
$pdo = db();

// ============================================================================
// SECTION 2: GET TIME PERIOD FILTER FROM URL
// ============================================================================

// Get the period parameter from URL query string
// User provides: ?period=week, ?period=month, ?period=quarter, or ?period=year
// Default to 'month' if no period specified
$period = strtolower(trim($_GET['period'] ?? 'month'));

// Validate period - only allow 'week', 'month', 'quarter', or 'year'
// If invalid period provided, default to 'month' for safety
if (!in_array($period, ['week', 'month', 'quarter', 'year'])) {
    $period = 'month';
}

// ============================================================================
// SECTION 3: CALCULATE DATE RANGE BASED ON SELECTED PERIOD
// ============================================================================

// Create DateTime objects with UK timezone
$endDate = new DateTime('now', new DateTimeZone('Europe/London'));
$startDate = new DateTime('now', new DateTimeZone('Europe/London'));

// Adjust start date based on selected period
switch ($period) {
    case 'week':
        // Go back 6 days (7 days total including today)
        $startDate->modify('-6 days');
        $periodLabel = 'Last 7 Days';
        $periodDays = 7;
        break;
        
    case 'month':
        // Go back 29 days (30 days total including today)
        $startDate->modify('-29 days');
        $periodLabel = 'Last 30 Days';
        $periodDays = 30;
        break;
        
    case 'quarter':
        // Go back 89 days (90 days total including today)
        $startDate->modify('-89 days');
        $periodLabel = 'Last Quarter (90 Days)';
        $periodDays = 90;
        break;
        
    case 'year':
        // Go back 364 days (365 days total including today)
        $startDate->modify('-364 days');
        $periodLabel = 'Last Year (365 Days)';
        $periodDays = 365;
        break;
}

// Format dates for database queries (Y-m-d H:i:s format)
$start = $startDate->format('Y-m-d 00:00:00');
$end = $endDate->format('Y-m-d 23:59:59');

// ============================================================================
// SECTION 4: FETCH OVERALL STATISTICS
// ============================================================================

// Initialize statistics array with default values
$stats = [
    'total_tours' => 0,
    'avg_score' => 0,
    'total_passes' => 0,
    'total_fails' => 0,
    'completion_rate' => 0
];

try {
    // Query to get overall statistics for the selected period
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            AVG(CAST(score_percent AS DECIMAL(5,2))) as avg_score,
            SUM(CAST(score_achieved AS UNSIGNED)) as total_passed,
            SUM(CAST(score_total AS UNSIGNED)) - SUM(CAST(score_achieved AS UNSIGNED)) as total_failed,
            SUM(CASE WHEN status='Closed' THEN 1 ELSE 0 END) as closed_count
        FROM safety_tours
        WHERE tour_date BETWEEN ? AND ?
    ");
    
    // Execute query with date range parameters
    $stmt->execute([$start, $end]);
    
    // Fetch results
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Store calculated statistics
    $stats['total_tours'] = (int)($result['total'] ?? 0);
    $stats['avg_score'] = round((float)($result['avg_score'] ?? 0), 1);
    $stats['total_passes'] = (int)($result['total_passed'] ?? 0);
    $stats['total_fails'] = (int)($result['total_failed'] ?? 0);
    
    // Calculate completion rate (percentage of closed tours)
    if ($stats['total_tours'] > 0) {
        $stats['completion_rate'] = round(
            ((int)($result['closed_count'] ?? 0) / $stats['total_tours']) * 100,
            1
        );
    }
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    error_log('Statistics fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 5: FETCH TOP RECURRING ISSUES (Most Common Failures)
// ============================================================================

// Initialize array to store issues
$topIssues = [];

try {
    // Query to get all tours with responses in the selected period
    $stmt = $pdo->prepare("
        SELECT id, responses 
        FROM safety_tours 
        WHERE tour_date BETWEEN ? AND ? AND responses IS NOT NULL
    ");
    
    // Execute query
    $stmt->execute([$start, $end]);
    
    // Fetch all tours
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Array to count issue frequencies
    $issues = [];
    
    // Process each tour's responses
    foreach ($tours as $tour) {
        // Decode JSON responses
        $responses = json_decode($tour['responses'], true) ?? [];
        
        // Look for all failed items
        foreach ($responses as $r) {
            // Check if this item failed
            if (strtolower(trim($r['result'] ?? '')) === 'fail' && !empty($r['question'])) {
                // Get the question text
                $q = trim($r['question']);
                
                // Increment count for this issue
                $issues[$q] = ($issues[$q] ?? 0) + 1;
            }
        }
    }
    
    // Sort issues by frequency (highest first)
    arsort($issues);
    
    // Get top 15 issues
    $topIssues = array_slice($issues, 0, 15, true);
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    error_log('Top issues fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 6: FETCH TOURS BY SITE (Risk Heatmap)
// ============================================================================

// Initialize array for site data
$toursBySite = [];

try {
    // Query to get statistics per site
    $stmt = $pdo->prepare("
        SELECT 
            site,
            COUNT(*) as tour_count,
            AVG(CAST(score_percent AS DECIMAL(5,2))) as avg_score,
            MIN(CAST(score_percent AS DECIMAL(5,2))) as min_score,
            MAX(CAST(score_percent AS DECIMAL(5,2))) as max_score
        FROM safety_tours
        WHERE tour_date BETWEEN ? AND ?
        GROUP BY site
        ORDER BY avg_score ASC
    ");
    
    // Execute query
    $stmt->execute([$start, $end]);
    
    // Fetch results
    $toursBySite = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    error_log('Tours by site fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 7: FETCH MONTHLY TREND DATA (for line chart)
// ============================================================================

// Initialize array for monthly data
$monthlyTrend = [];

try {
    // Query to get monthly statistics
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(tour_date, '%Y-%m') as month,
            DATE_FORMAT(tour_date, '%b %Y') as month_display,
            COUNT(*) as tour_count,
            AVG(CAST(score_percent AS DECIMAL(5,2))) as avg_score,
            SUM(CASE WHEN status='Closed' THEN 1 ELSE 0 END) as closed_count
        FROM safety_tours
        WHERE tour_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(tour_date, '%Y-%m')
        ORDER BY month ASC
    ");
    
    // Execute query
    $stmt->execute([$start, $end]);
    
    // Fetch results
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    error_log('Monthly trend fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 8: FETCH STATUS DISTRIBUTION (Open vs Closed)
// ============================================================================

// Initialize status counts
$statusDistribution = ['Open' => 0, 'Closed' => 0];

try {
    // Query to count tours by status
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM safety_tours
        WHERE tour_date BETWEEN ? AND ?
        GROUP BY status
    ");
    
    // Execute query
    $stmt->execute([$start, $end]);
    
    // Process results
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = $row['status'] ?? 'Open';
        $statusDistribution[$status] = (int)$row['count'];
    }
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    error_log('Status distribution fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 9: FETCH RISK SCORE DISTRIBUTION
// ============================================================================

// Initialize risk distribution buckets
$riskDistribution = [
    '0-25' => 0,      // High Risk (0-25%)
    '26-50' => 0,     // Medium Risk (26-50%)
    '51-75' => 0,     // Good (51-75%)
    '76-100' => 0     // Excellent (76-100%)
];

try {
    // Query to count tours in each risk bucket
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN CAST(score_percent AS DECIMAL(5,2)) <= 25 THEN '0-25'
                WHEN CAST(score_percent AS DECIMAL(5,2)) <= 50 THEN '26-50'
                WHEN CAST(score_percent AS DECIMAL(5,2)) <= 75 THEN '51-75'
                ELSE '76-100'
            END as range,
            COUNT(*) as count
        FROM safety_tours
        WHERE tour_date BETWEEN ? AND ? AND score_percent IS NOT NULL
        GROUP BY range
    ");
    
    // Execute query
    $stmt->execute([$start, $end]);
    
    // Process results
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $riskDistribution[$row['range']] = (int)$row['count'];
    }
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    error_log('Risk distribution fetch error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ================================================================ -->
    <!-- METADATA & CHARACTER ENCODING -->
    <!-- ================================================================ -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- ================================================================ -->
    <!-- PAGE TITLE & FAVICON -->
    <!-- ================================================================ -->
    <title>Analytics Dashboard — Safety Tours</title>
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    
    <!-- ================================================================ -->
    <!-- CHART.JS LIBRARY FOR INTERACTIVE CHARTS -->
    <!-- ================================================================ -->
    <!-- Using latest version 4.4.0 from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- ================================================================ -->
    <!-- INLINE STYLES (Modern, Beautiful Design) -->
    <!-- ================================================================ -->
    <style>
        /* ================================================================ */
        /* GLOBAL STYLES */
        /* ================================================================ */
        
        /* Reset all default margins and padding */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Body - Full screen with gradient background */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        /* Main container - max width for readability */
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* ================================================================ */
        /* BACK LINK / NAVIGATION */
        /* ================================================================ */
        
        /* Back to dashboard link */
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        /* Back link hover effect */
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }
        
        /* ================================================================ */
        /* HEADER SECTION */
        /* ================================================================ */
        
        /* Main header container */
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        /* Main heading */
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 700;
        }
        
        /* Subheading paragraph */
        .header p {
            color: #666;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        /* ================================================================ */
        /* HEADER CONTROLS (Filters & Exports) */
        /* ================================================================ */
        
        /* Container for period filter and export buttons */
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        /* Period filter buttons container */
        .period-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        /* Individual period filter button */
        .period-filter a {
            padding: 10px 18px;
            border: 2px solid #ddd;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        /* Period filter button hover */
        .period-filter a:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        /* Active period filter button */
        .period-filter a.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        /* Export buttons container */
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        /* Individual export button */
        .export-buttons a {
            padding: 10px 16px;
            background: #10b981;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        /* Export button hover */
        .export-buttons a:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        /* ================================================================ */
        /* STATISTICS CARDS GRID */
        /* ================================================================ */
        
        /* Grid for statistics cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        /* Individual statistic card */
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        /* Stat card hover effect */
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        /* Stat card for success (green) */
        .stat-card.success {
            border-left-color: #10b981;
        }
        
        /* Stat card for danger (red) */
        .stat-card.danger {
            border-left-color: #ef4444;
        }
        
        /* Stat card for warning (orange) */
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        
        /* Stat card heading */
        .stat-card h3 {
            color: #999;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        
        /* Stat card value (big number) */
        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            line-height: 1.2;
        }
        
        /* Stat card value colour overrides */
        .stat-card.success .value {
            color: #10b981;
        }
        
        .stat-card.danger .value {
            color: #ef4444;
        }
        
        .stat-card.warning .value {
            color: #f59e0b;
        }
        
        /* Stat card subtitle */
        .stat-card .subtitle {
            color: #999;
            font-size: 12px;
            margin-top: 8px;
        }
        
        /* ================================================================ */
        /* CHARTS GRID */
        /* ================================================================ */
        
        /* Grid for chart cards */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        /* Individual chart card */
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        /* Chart card hover */
        .chart-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        /* Chart card heading */
        .chart-card h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            font-weight: 700;
        }
        
        /* Chart canvas container */
        .chart-container {
            position: relative;
            height: 320px;
            margin-bottom: 10px;
        }
        
        /* Chart description text */
        .chart-description {
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        
        /* ================================================================ */
        /* DATA TABLES */
        /* ================================================================ */
        
        /* Data table container */
        .data-table {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow-x: auto;
        }
        
        /* Data table heading */
        .data-table h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            font-weight: 700;
        }
        
        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        /* Table head */
        thead {
            background: #f8f9fa;
        }
        
        /* Table header cell */
        th {
            padding: 14px 12px;
            text-align: left;
            font-weight: 700;
            color: #333;
            border-bottom: 2px solid #e5e7eb;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Table data cell */
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #666;
            font-size: 14px;
        }
        
        /* Table row hover */
        tbody tr:hover {
            background: #f8f9ff;
        }
        
        /* Progress bar for trend */
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
        }
        
        /* Progress bar fill */
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        
        /* ================================================================ */
        /* RISK BADGES */
        /* ================================================================ */
        
        /* Risk badge styling */
        .risk-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        /* Low risk badge (green) */
        .risk-low {
            background: #d1fae5;
            color: #065f46;
        }
        
        /* Medium risk badge (orange) */
        .risk-medium {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* High risk badge (red) */
        .risk-high {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* ================================================================ */
        /* NO DATA MESSAGE */
        /* ================================================================ */
        
        /* No data message styling */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        /* ================================================================ */
        /* RESPONSIVE DESIGN (Tablets & Mobile) */
        /* ================================================================ */
        
        @media (max-width: 1024px) {
            /* Stack charts on tablet */
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            /* Stack controls on tablet */
            .header-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            /* Period filter buttons full width */
            .period-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .period-filter a {
                flex: 1;
                text-align: center;
            }
            
            /* Export buttons full width */
            .export-buttons {
                justify-content: stretch;
            }
            
            .export-buttons a {
                flex: 1;
                text-align: center;
            }
        }
        
        @media (max-width: 768px) {
            /* Single column stats on mobile */
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            /* Smaller header padding */
            .header {
                padding: 20px;
            }
            
            /* Smaller heading */
            .header h1 {
                font-size: 24px;
            }
            
            /* Smaller stat values */
            .stat-card .value {
                font-size: 28px;
            }
            
            /* Smaller chart height */
            .chart-container {
                height: 250px;
            }
            
            /* Smaller table text */
            table {
                font-size: 12px;
            }
            
            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- ================================================================ -->
    <!-- MAIN CONTAINER START -->
    <!-- ================================================================ -->
    <div class="container">
        
        <!-- ============================================================ -->
        <!-- BACK LINK -->
        <!-- ============================================================ -->
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <!-- ============================================================ -->
        <!-- HEADER SECTION -->
        <!-- ============================================================ -->
        <div class="header">
            <!-- Main title -->
            <h1>📊 Analytics Dashboard</h1>
            
            <!-- Subtitle with period info and current time -->
            <p>
                <strong><?php echo htmlspecialchars($periodLabel); ?></strong> 
                • Generated <?php echo date('d/m/Y H:i:s'); ?> (UK Time)
            </p>
            
            <!-- ========================================================== -->
            <!-- HEADER CONTROLS (Period Filter & Export Buttons) -->
            <!-- ========================================================== -->
            <div class="header-controls">
                
                <!-- Period filter buttons -->
                <div class="period-filter">
                    <a href="?period=week" class="<?php echo $period === 'week' ? 'active' : ''; ?>">📅 Last 7 Days</a>
                    <a href="?period=month" class="<?php echo $period === 'month' ? 'active' : ''; ?>">📅 Last 30 Days</a>
                    <a href="?period=quarter" class="<?php echo $period === 'quarter' ? 'active' : ''; ?>">📅 Last Quarter</a>
                    <a href="?period=year" class="<?php echo $period === 'year' ? 'active' : ''; ?>">📅 Last Year</a>
                </div>
                
                <!-- Export buttons -->
                <div class="export-buttons">
                    <a href="export.php?format=csv">📥 Download CSV</a>
                    <a href="export.php?format=excel">📥 Download Excel</a>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- KEY STATISTICS CARDS SECTION -->
        <!-- ============================================================ -->
        <!-- Five cards showing key metrics -->
        
        <div class="stats-grid">
            
            <!-- Total Tours Card -->
            <div class="stat-card">
                <h3>Total Tours</h3>
                <div class="value"><?php echo $stats['total_tours']; ?></div>
                <div class="subtitle">in selected period</div>
            </div>
            
            <!-- Average Score Card (Warning colour) -->
            <div class="stat-card warning">
                <h3>Average Score</h3>
                <div class="value"><?php echo $stats['avg_score']; ?>%</div>
                <div class="subtitle">safety compliance</div>
            </div>
            
            <!-- Total Pass Items Card (Success colour) -->
            <div class="stat-card success">
                <h3>Pass Items</h3>
                <div class="value"><?php echo $stats['total_passes']; ?></div>
                <div class="subtitle">compliant findings</div>
            </div>
            
            <!-- Total Fail Items Card (Danger colour) -->
            <div class="stat-card danger">
                <h3>Fail Items</h3>
                <div class="value"><?php echo $stats['total_fails']; ?></div>
                <div class="subtitle">non-compliance</div>
            </div>
            
            <!-- Completion Rate Card -->
            <div class="stat-card">
                <h3>Completion Rate</h3>
                <div class="value"><?php echo $stats['completion_rate']; ?>%</div>
                <div class="subtitle">tours closed</div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- CHARTS SECTION - INTERACTIVE VISUALIZATIONS -->
        <!-- ============================================================ -->
        <!-- Four interactive charts using Chart.js library -->
        
        <div class="charts-grid">
            
            <!-- CHART 1: Monthly Trend (Line Chart with Dual Axes) -->
            <div class="chart-card">
                <h3>📈 Monthly Trend</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
                <div class="chart-description">Average safety score and tour count per month</div>
            </div>
            
            <!-- CHART 2: Status Distribution (Doughnut Chart) -->
            <div class="chart-card">
                <h3>📋 Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="chart-description">Open vs Closed tours</div>
            </div>
            
            <!-- CHART 3: Risk Score Distribution (Bar Chart) -->
            <div class="chart-card">
                <h3>⚠️ Risk Distribution</h3>
                <div class="chart-container">
                    <canvas id="riskChart"></canvas>
                </div>
                <div class="chart-description">Tours by safety score range</div>
            </div>
            
            <!-- CHART 4: Top 10 Failure Issues (Horizontal Bar Chart) -->
            <div class="chart-card">
                <h3>🔴 Top 10 Failure Issues</h3>
                <div class="chart-container">
                    <canvas id="issuesChart"></canvas>
                </div>
                <div class="chart-description">Most frequently reported failures</div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- TOP ISSUES DETAILED TABLE -->
        <!-- ============================================================ -->
        <!-- Shows top 15 recurring failure issues with frequency counts -->
        
        <?php if (!empty($topIssues)) { ?>
        <div class="data-table">
            <h3>🔍 Most Recurring Issues (Detailed Analysis)</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>Issue Description</th>
                        <th style="width: 120px; text-align: center;">Occurrences</th>
                        <th style="width: 150px; text-align: center;">Frequency Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Get maximum count to calculate percentages for progress bars
                    $maxIssues = max(array_values($topIssues));
                    $rank = 1;
                    
                    // Display each issue in order
                    foreach ($topIssues as $issue => $count) { 
                        // Calculate percentage for progress bar width
                        $percentage = ($count / $maxIssues) * 100;
                    ?>
                    <tr>
                        <!-- Rank number (1-15) -->
                        <td style="font-weight: 700; color: #667eea;">#<?php echo $rank; ?></td>
                        
                        <!-- Issue description (truncated to 120 characters) -->
                        <td><?php echo htmlspecialchars(substr($issue, 0, 120)); ?></td>
                        
                        <!-- Count of occurrences -->
                        <td style="text-align: center; font-weight: 600;"><?php echo $count; ?></td>
                        
                        <!-- Visual progress bar showing frequency -->
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php $rank++; } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
            <!-- No issues message if no failures recorded -->
            <div class="data-table">
                <div class="no-data">📊 No failure data available for the selected period</div>
            </div>
        <?php } ?>
        
        <!-- ============================================================ -->
        <!-- RISK HEATMAP BY SITE TABLE -->
        <!-- ============================================================ -->
        <!-- Shows performance and risk level for each site -->
        
        <?php if (!empty($toursBySite)) { ?>
        <div class="data-table">
            <h3>🔥 Risk Heatmap by Site (Performance Analysis)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Site Name</th>
                        <th style="text-align: center;">Tours</th>
                        <th style="text-align: center;">Avg Score</th>
                        <th style="text-align: center;">Score Range</th>
                        <th style="text-align: center;">Risk Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Process each site
                    foreach ($toursBySite as $site) { 
                        // Round average score to 1 decimal place
                        $avgScore = round((float)($site['avg_score'] ?? 0), 1);
                        $minScore = round((float)($site['min_score'] ?? 0), 1);
                        $maxScore = round((float)($site['max_score'] ?? 0), 1);
                        
                        // Determine risk level based on average score
                        if ($avgScore >= 75) {
                            // Low risk (green) - Safe and compliant
                            $riskLevel = '<span class="risk-badge risk-low">🟢 Low Risk</span>';
                            $scoreColor = '#10b981';
                        } elseif ($avgScore >= 50) {
                            // Medium risk (orange) - Some concerns
                            $riskLevel = '<span class="risk-badge risk-medium">🟡 Medium Risk</span>';
                            $scoreColor = '#f59e0b';
                        } else {
                            // High risk (red) - Needs attention
                            $riskLevel = '<span class="risk-badge risk-high">🔴 High Risk</span>';
                            $scoreColor = '#ef4444';
                        }
                    ?>
                    <tr>
                        <!-- Site name -->
                        <td><strong><?php echo htmlspecialchars($site['site'] ?? 'Unknown'); ?></strong></td>
                        
                        <!-- Number of tours at this site -->
                        <td style="text-align: center;"><?php echo (int)$site['tour_count']; ?></td>
                        
                        <!-- Average score (colour-coded) -->
                        <td style="text-align: center; font-weight: 600; color: <?php echo $scoreColor; ?>"><?php echo $avgScore; ?>%</td>
                        
                        <!-- Min and Max score range -->
                        <td style="text-align: center; font-size: 12px;"><?php echo $minScore; ?>% - <?php echo $maxScore; ?>%</td>
                        
                        <!-- Risk level badge with emoji -->
                        <td style="text-align: center;"><?php echo $riskLevel; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
            <!-- No site data message -->
            <div class="data-table">
                <div class="no-data">📍 No site data available for the selected period</div>
            </div>
        <?php } ?>
    
    </div>
    <!-- END MAIN CONTAINER -->
    
    <!-- ================================================================ -->
    <!-- CHART.JS LIBRARY INITIALIZATION -->
    <!-- ================================================================ -->
    <!-- JavaScript code to create and configure all interactive charts -->
    
    <script>
        // ================================================================
        // CHART 1: MONTHLY TREND - Line Chart with Dual Y-Axes
        // ================================================================
        // Shows both average safety score AND number of tours per month
        // Uses two different Y-axes to show both metrics on same chart
        
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx && <?php echo count($monthlyTrend); ?> > 0) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    // Month labels for X-axis (e.g., "Oct 2025", "Nov 2025")
                    labels: [<?php echo implode(',', array_map(fn($m) => '"' . htmlspecialchars($m['month_display']) . '"', $monthlyTrend)); ?>],
                    datasets: [
                        {
                            // First dataset: Average Score %
                            label: 'Avg Score %',
                            data: [<?php echo implode(',', array_map(fn($m) => round((float)$m['avg_score'], 1), $monthlyTrend)); ?>],
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointBackgroundColor: '#667eea',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            yAxisID: 'y'
                        },
                        {
                            // Second dataset: Tour Count
                            label: 'Tour Count',
                            data: [<?php echo implode(',', array_map(fn($m) => (int)$m['tour_count'], $monthlyTrend)); ?>],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true, position: 'top' }
                    },
                    scales: {
                        // Left Y-axis: Safety Score (0-100%)
                        y: { 
                            type: 'linear', 
                            display: true, 
                            position: 'left',
                            title: { display: true, text: 'Safety Score %' },
                            min: 0,
                            max: 100
                        },
                        // Right Y-axis: Tour Count
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Tour Count' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        }
        
        // ================================================================
        // CHART 2: STATUS DISTRIBUTION - Doughnut Chart
        // ================================================================
        // Shows ratio of Open vs Closed tours (pie/doughnut style)
        
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Open', 'Closed'],
                    datasets: [{
                        data: [<?php echo $statusDistribution['Open']; ?>, <?php echo $statusDistribution['Closed']; ?>],
                        backgroundColor: ['#ef4444', '#10b981'],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        
        // ================================================================
        // CHART 3: RISK SCORE DISTRIBUTION - Bar Chart
        // ================================================================
        // Shows how many tours fall into each risk category (0-25%, 26-50%, etc.)
        
        const riskCtx = document.getElementById('riskChart');
        if (riskCtx) {
            new Chart(riskCtx, {
                type: 'bar',
                data: {
                    // X-axis labels with risk levels
                    labels: [
                        '0-25%\n(High Risk)',
                        '26-50%\n(Medium)',
                        '51-75%\n(Good)',
                        '76-100%\n(Excellent)'
                    ],
                    datasets: [{
                        label: 'Number of Tours',
                        data: [
                            <?php echo $riskDistribution['0-25']; ?>,
                            <?php echo $riskDistribution['26-50']; ?>,
                            <?php echo $riskDistribution['51-75']; ?>,
                            <?php echo $riskDistribution['76-100']; ?>
                        ],
                        // Each bar colour-coded by risk level
                        backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
        
        // ================================================================
        // CHART 4: TOP FAILURE ISSUES - Horizontal Bar Chart
        // ================================================================
        // Shows the 10 most frequently reported failure issues
        
        const issuesCtx = document.getElementById('issuesChart');
        if (issuesCtx && <?php echo count($topIssues); ?> > 0) {
            new Chart(issuesCtx, {
                type: 'bar',
                data: {
                    // Issue descriptions (truncated to 35 characters for readability)
                    labels: [<?php echo implode(',', array_map(fn($i) => '"' . htmlspecialchars(substr($i, 0, 35)) . '..."' , array_keys(array_slice($topIssues, 0, 10)))); ?>],
                    datasets: [{
                        label: 'Occurrences',
                        // Occurrence count for each issue
                        data: [<?php echo implode(',', array_slice(array_values($topIssues), 0, 10)); ?>],
                        backgroundColor: '#ef4444',
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    indexAxis: 'y',  // Horizontal bars (y-axis index)
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } }
                }
            });
        }
    </script>
</body>
</html>
