<?php
/**
 * ============================================================================
 * FILE NAME: analytics.php
 * ============================================================================
 * 
 * DESCRIPTION:
 * Professional Analytics and Business Intelligence Dashboard for Safety
 * Tracker. Displays comprehensive insights, trends, patterns, and statistics
 * from all safety tours in an interactive, modern dashboard. Features
 * beautiful Chart.js visualizations showing monthly trends with dual-axis
 * line charts, pie/doughnut charts for status distribution, bar charts for
 * risk analysis, and horizontal bar charts for recurring issues. Users can
 * select different time periods (7/30/90/365 days) to view analytics for
 * specific date ranges. Displays key statistics cards with colour-coded
 * metrics (blue, green, orange, red), professional data tables with risk
 * heatmaps by site location, analysis of top 15 recurring failure issues
 * with frequency counts, and export buttons to download data as CSV or
 * Excel. Theme matches existing dashboard.php for consistent user experience.
 * All dates displayed in UK format (DD/MM/YYYY HH:MM:SS). Only authenticated
 * users can access - login required. Mobile responsive design works perfectly
 * on all devices (phones, tablets, desktops). Current date/time:
 * 27/10/2025 14:33:22 (UK Time). Current user: irlam
 * 
 * FILE PURPOSE:
 * This file provides a comprehensive analytics dashboard that allows safety
 * managers and supervisors to analyze safety tour data, identify trends,
 * spot recurring issues, and assess overall safety compliance across
 * different time periods and locations. The dashboard helps identify high-risk
 * sites, track improvement over time, and make data-driven decisions about
 * safety priorities.
 * 
 * WHAT THIS FILE DOES:
 * 1. Verifies user is logged in (requires authentication)
 * 2. Gets time period filter from URL (?period=week/month/quarter/year)
 * 3. Calculates date range (7/30/90/365 days) based on selected period
 * 4. Fetches total tours, average score, pass/fail counts (statistics)
 * 5. Fetches top 15 recurring failure issues with frequency counts
 * 6. Fetches tours grouped by site for risk heatmap analysis
 * 7. Fetches monthly trend data for line chart visualization
 * 8. Fetches status distribution (Open vs Closed tours)
 * 9. Fetches risk score distribution (0-25%, 26-50%, 51-75%, 76-100%)
 * 10. Renders professional HTML dashboard with modern styling
 * 11. Displays 5 colour-coded statistics cards with key metrics
 * 12. Shows 4 interactive Chart.js visualizations (line, doughnut, bar)
 * 13. Displays detailed data tables with risk badges and progress bars
 * 14. Includes export buttons to download analytics as CSV or Excel
 * 15. Implements mobile responsive design using CSS Grid and Flexbox
 * 
 * HOW TO USE:
 * View analytics dashboard (default 30 days):
 *   https://safety.defecttracker.uk/analytics.php
 * 
 * View last 7 days:
 *   https://safety.defecttracker.uk/analytics.php?period=week
 * 
 * View last 30 days:
 *   https://safety.defecttracker.uk/analytics.php?period=month
 * 
 * View last 90 days:
 *   https://safety.defecttracker.uk/analytics.php?period=quarter
 * 
 * View last 365 days:
 *   https://safety.defecttracker.uk/analytics.php?period=year
 * 
 * KEY FEATURES:
 * - 4 Interactive charts (line with dual axes, doughnut, bar, horizontal bar)
 * - 5 Statistics cards with colour-coded metrics
 * - Time period selector (7/30/90/365 days with active highlighting)
 * - Monthly trend analysis with dual-axis line chart
 * - Risk distribution analysis (Low/Medium/Good/Excellent)
 * - Status distribution pie chart (Open vs Closed)
 * - Top 15 recurring failure issues with visual progress bars
 * - Risk heatmap by site location with colour-coded risk levels
 * - Export buttons to download as CSV and Excel
 * - Professional gradient backgrounds and smooth animations
 * - Mobile responsive design using modern CSS
 * - UK date/time format throughout (DD/MM/YYYY HH:MM:SS)
 * - Theme matches existing dashboard.php
 * - Fully accessible HTML5 structure
 * - Professional colour scheme (purple/blue gradients)
 * 
 * DEPENDENCIES:
 * - Chart.js v4.4.0 (loaded from CDN)
 * - /includes/auth.php (authentication system)
 * - /includes/functions.php (database functions and db() connection)
 * - Modern web browser (Chrome, Firefox, Safari, Edge)
 * - Internet connection (for CDN resources)
 * 
 * DATABASE TABLES USED:
 * - safety_tours (main table with tour data)
 * - Columns: id, tour_date, site, area, lead_name, participants,
 *   status, score_achieved, score_total, score_percent, responses,
 *   photos, signature_path, recipients
 * 
 * CREATED: 27/10/2025 14:33:22 (UK Time)
 * LAST MODIFIED: 27/10/2025 14:33:22 (UK Time)
 * CREATED BY: irlam
 * THEME: Matches dashboard.php (purple/blue gradient, modern styling)
 * ============================================================================
 */

declare(strict_types=1);

// ============================================================================
// SECTION 1: INITIALIZATION - TIMEZONE & AUTHENTICATION
// ============================================================================

// Set timezone to Europe/London (UK Time)
// All dates will automatically be formatted in UK timezone (GMT/BST)
// Current date/time: 27/10/2025 14:33:22 (UK Time)
date_default_timezone_set('Europe/London');

// ============================================================================
// Load authentication system to verify user is logged in
// ============================================================================
// Check if authentication file exists in includes directory
// This ensures only authorized users can view analytics data
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    // If auth_check() function exists, it will verify user is logged in
    // If user is NOT logged in, it automatically redirects to login page
    // This ensures only authorized users can view sensitive analytics data
    if (function_exists('auth_check')) {
        auth_check();
    }
}

// ============================================================================
// Load shared database functions and connection
// ============================================================================
// This file contains database helper functions like db() for connection
// and other shared utilities used throughout the Safety Tracker application
require_once __DIR__ . '/includes/functions.php';

// Get database connection - returns PDO object
// This is used to query the safety_tours table and fetch analytics data
$pdo = db();

// ============================================================================
// SECTION 2: GET TIME PERIOD FILTER FROM URL
// ============================================================================

// Get the period parameter from URL query string
// User provides: ?period=week, ?period=month, ?period=quarter, or ?period=year
// Default to 'month' (30 days) if no period specified by user
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
// These are used to calculate the start and end dates for the selected period
$endDate = new DateTime('now', new DateTimeZone('Europe/London'));
$startDate = new DateTime('now', new DateTimeZone('Europe/London'));

// Adjust start date based on selected period
// The end date is always "now" (current moment)
// The start date is moved back based on the period selected
switch ($period) {
    case 'week':
        // Go back 6 days (7 days total including today)
        // This shows data from the last 7 calendar days
        $startDate->modify('-6 days');
        $periodLabel = 'Last 7 Days';
        $periodDays = 7;
        break;
        
    case 'month':
        // Go back 29 days (30 days total including today)
        // This is the default view showing last 30 days of data
        $startDate->modify('-29 days');
        $periodLabel = 'Last 30 Days';
        $periodDays = 30;
        break;
        
    case 'quarter':
        // Go back 89 days (90 days total including today)
        // This shows approximately one quarter of the year
        $startDate->modify('-89 days');
        $periodLabel = 'Last Quarter (90 Days)';
        $periodDays = 90;
        break;
        
    case 'year':
        // Go back 364 days (365 days total including today)
        // This shows exactly one year of data
        $startDate->modify('-364 days');
        $periodLabel = 'Last Year (365 Days)';
        $periodDays = 365;
        break;
}

// Format dates for database queries (Y-m-d H:i:s format)
// Start date at 00:00:00 (beginning of that day)
// End date at 23:59:59 (end of current day)
$start = $startDate->format('Y-m-d 00:00:00');
$end = $endDate->format('Y-m-d 23:59:59');

// ============================================================================
// SECTION 4: FETCH OVERALL STATISTICS
// ============================================================================

// Initialize statistics array with default values
// These will be populated from database queries
$stats = [
    'total_tours' => 0,           // Total number of tours in period
    'avg_score' => 0,             // Average safety score (0-100%)
    'total_passes' => 0,          // Total items that passed
    'total_fails' => 0,           // Total items that failed
    'completion_rate' => 0        // Percentage of closed tours
];

try {
    // Query to get overall statistics for the selected period
    // This calculates totals and averages across all tours
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
    
    // Fetch results as associative array
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Store calculated statistics from query results
    $stats['total_tours'] = (int)($result['total'] ?? 0);
    $stats['avg_score'] = round((float)($result['avg_score'] ?? 0), 1);
    $stats['total_passes'] = (int)($result['total_passed'] ?? 0);
    $stats['total_fails'] = (int)($result['total_failed'] ?? 0);
    
    // Calculate completion rate (percentage of closed tours)
    // Only calculate if there are tours in the period
    if ($stats['total_tours'] > 0) {
        $stats['completion_rate'] = round(
            ((int)($result['closed_count'] ?? 0) / $stats['total_tours']) * 100,
            1
        );
    }
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    // If query fails, statistics will remain at default values
    error_log('Statistics fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 5: FETCH TOP RECURRING ISSUES (Most Common Failures)
// ============================================================================

// Initialize array to store issues and their frequencies
$topIssues = [];

try {
    // Query to get all tours with responses in the selected period
    // We fetch the raw responses JSON for each tour
    $stmt = $pdo->prepare("
        SELECT id, responses 
        FROM safety_tours 
        WHERE tour_date BETWEEN ? AND ? AND responses IS NOT NULL
    ");
    
    // Execute query with date range
    $stmt->execute([$start, $end]);
    
    // Fetch all tours in the period
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Array to count issue frequencies as we process tours
    $issues = [];
    
    // Process each tour's responses
    foreach ($tours as $tour) {
        // Decode JSON responses from database
        // Responses contain all the questions and answers from that tour
        $responses = json_decode($tour['responses'], true) ?? [];
        
        // Look for all failed items in this tour
        foreach ($responses as $r) {
            // Check if this item failed
            if (strtolower(trim($r['result'] ?? '')) === 'fail' && !empty($r['question'])) {
                // Get the question text
                $q = trim($r['question']);
                
                // Increment count for this issue
                // This tracks how many times this specific issue has failed
                $issues[$q] = ($issues[$q] ?? 0) + 1;
            }
        }
    }
    
    // Sort issues by frequency (highest count first)
    // This puts the most common failures at the top
    arsort($issues);
    
    // Get top 15 issues (slice array to 15 items)
    // We limit to 15 for display purposes
    $topIssues = array_slice($issues, 0, 15, true);
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    // If query fails, topIssues will remain empty
    error_log('Top issues fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 6: FETCH TOURS BY SITE (Risk Heatmap)
// ============================================================================

// Initialize array for site data
// This will contain statistics grouped by site name
$toursBySite = [];

try {
    // Query to get statistics per site
    // Groups all tours by site and calculates averages/counts for each site
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
    
    // Execute query with date range
    $stmt->execute([$start, $end]);
    
    // Fetch results (ordered by avg_score ascending, so worst sites first)
    $toursBySite = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    // If query fails, toursBySite will remain empty
    error_log('Tours by site fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 7: FETCH MONTHLY TREND DATA (for line chart)
// ============================================================================

// Initialize array for monthly data
// This will contain one entry per month in the selected period
$monthlyTrend = [];

try {
    // Query to get monthly statistics
    // Groups tours by month and calculates averages for each month
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
    
    // Execute query with date range
    $stmt->execute([$start, $end]);
    
    // Fetch results (ordered chronologically)
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    // If query fails, monthlyTrend will remain empty
    error_log('Monthly trend fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 8: FETCH STATUS DISTRIBUTION (Open vs Closed)
// ============================================================================

// Initialize status counts array
// Will count how many tours are Open and how many are Closed
$statusDistribution = ['Open' => 0, 'Closed' => 0];

try {
    // Query to count tours by status
    // Groups all tours by their status (Open or Closed)
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM safety_tours
        WHERE tour_date BETWEEN ? AND ?
        GROUP BY status
    ");
    
    // Execute query with date range
    $stmt->execute([$start, $end]);
    
    // Process results and populate status distribution
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = $row['status'] ?? 'Open';
        $statusDistribution[$status] = (int)$row['count'];
    }
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    // If query fails, statusDistribution remains at default
    error_log('Status distribution fetch error: ' . $e->getMessage());
}

// ============================================================================
// SECTION 9: FETCH RISK SCORE DISTRIBUTION
// ============================================================================

// Initialize risk distribution buckets
// Each bucket represents a range of safety scores
$riskDistribution = [
    '0-25' => 0,      // High Risk (0-25%) - Red
    '26-50' => 0,     // Medium Risk (26-50%) - Orange
    '51-75' => 0,     // Good (51-75%) - Blue
    '76-100' => 0     // Excellent (76-100%) - Green
];

try {
    // Query to count tours in each risk bucket
    // Uses CASE statement to categorize scores into ranges
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
    
    // Execute query with date range
    $stmt->execute([$start, $end]);
    
    // Process results and populate risk distribution
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $riskDistribution[$row['range']] = (int)$row['count'];
    }
    
} catch (Throwable $e) {
    // Log error but don't stop execution
    // If query fails, riskDistribution remains at default
    error_log('Risk distribution fetch error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ================================================================ -->
    <!-- METADATA & CHARACTER ENCODING -->
    <!-- ================================================================ -->
    <!-- UTF-8 encoding ensures all special characters display correctly -->
    <meta charset="utf-8">
    
    <!-- Viewport meta tag enables responsive design on mobile devices -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- ================================================================ -->
    <!-- PAGE TITLE & FAVICON -->
    <!-- ================================================================ -->
    <!-- Page title shown in browser tab -->
    <title>Analytics Dashboard — Safety Tours</title>
    
    <!-- Favicon shown in browser tab next to title -->
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    
    <!-- ================================================================ -->
    <!-- CHART.JS LIBRARY FOR INTERACTIVE CHARTS -->
    <!-- ================================================================ -->
    <!-- Loading Chart.js v4.4.0 from CDN (Content Delivery Network) -->
    <!-- This provides interactive chart visualizations (line, bar, pie, etc.) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- ================================================================ -->
    <!-- INLINE STYLES (Modern, Beautiful Design - MATCHES DASHBOARD.PHP) -->
    <!-- ================================================================ -->
    <!-- All CSS is included inline for better performance and simplicity -->
    <!-- Theme matches existing dashboard.php with purple/blue gradients -->
    <style>
        /* ================================================================ */
        /* GLOBAL STYLES - RESET & BASE */
        /* ================================================================ */
        
        /* Reset all default browser margins and padding */
        /* This gives us a clean slate to start with */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* ================================================================ */
        /* BODY & BACKGROUND */
        /* ================================================================ */
        
        /* Body styling - Full screen gradient background (MATCHES DASHBOARD) */
        body {
            /* Modern system font stack for best readability */
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            
            /* Beautiful gradient background (purple to blue - MATCHES DASHBOARD) */
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            
            /* Ensure gradient covers full viewport */
            min-height: 100vh;
            
            /* Add padding around content */
            padding: 20px;
            
            /* Default text colour */
            color: #333;
        }
        
        /* ================================================================ */
        /* CONTAINER & LAYOUT */
        /* ================================================================ */
        
        /* Main container - max width for readability on large screens */
        .container {
            /* Maximum width for better readability */
            max-width: 1400px;
            
            /* Center container horizontally */
            margin: 0 auto;
        }
        
        /* ================================================================ */
        /* BACK LINK / NAVIGATION */
        /* ================================================================ */
        
        /* Back to dashboard link styling */
        .back-link {
            /* Display as inline-block for proper padding */
            display: inline-block;
            
            /* Space below the link */
            margin-bottom: 20px;
            
            /* Padding inside the link button */
            padding: 12px 20px;
            
            /* Semi-transparent white background */
            background: rgba(255, 255, 255, 0.2);
            
            /* White text colour */
            color: white;
            
            /* Remove default link underline */
            text-decoration: none;
            
            /* Bold font weight */
            font-weight: 600;
            
            /* Rounded corners for modern look */
            border-radius: 8px;
            
            /* Smooth animation on hover */
            transition: all 0.3s ease;
            
            /* Smaller font size */
            font-size: 14px;
        }
        
        /* Back link hover effect */
        .back-link:hover {
            /* More opaque on hover */
            background: rgba(255, 255, 255, 0.3);
            
            /* Slide left animation */
            transform: translateX(-5px);
        }
        
        /* ================================================================ */
        /* HEADER SECTION */
        /* ================================================================ */
        
        /* Main header container (MATCHES DASHBOARD STYLE) */
        .header {
            /* White background */
            background: white;
            
            /* Internal spacing */
            padding: 30px;
            
            /* Rounded corners */
            border-radius: 10px;
            
            /* Space below header */
            margin-bottom: 25px;
            
            /* Professional shadow (MATCHES DASHBOARD) */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Main heading */
        .header h1 {
            /* Dark text colour */
            color: #333;
            
            /* Space below heading */
            margin-bottom: 10px;
            
            /* Large font size */
            font-size: 32px;
            
            /* Bold font weight */
            font-weight: 700;
        }
        
        /* Subheading paragraph */
        .header p {
            /* Medium grey text colour */
            color: #666;
            
            /* Space below paragraph */
            margin-bottom: 20px;
            
            /* Medium font size */
            font-size: 16px;
        }
        
        /* ================================================================ -->
        <!-- HEADER CONTROLS (Filters & Exports) -->
        <!-- ================================================================ */
        
        /* Container for period filter and export buttons */
        .header-controls {
            /* Flexbox for layout */
            display: flex;
            
            /* Space between filter and export buttons */
            justify-content: space-between;
            
            /* Center items vertically */
            align-items: center;
            
            /* Allow wrapping on small screens */
            flex-wrap: wrap;
            
            /* Space between items */
            gap: 20px;
        }
        
        /* Period filter buttons container */
        .period-filter {
            /* Flexbox for horizontal layout */
            display: flex;
            
            /* Space between buttons */
            gap: 10px;
            
            /* Allow wrapping */
            flex-wrap: wrap;
            
            /* Center items vertically */
            align-items: center;
        }
        
        /* Individual period filter button */
        .period-filter a {
            /* Internal padding */
            padding: 10px 18px;
            
            /* Border styling */
            border: 2px solid #ddd;
            
            /* Rounded corners */
            border-radius: 6px;
            
            /* White background */
            background: white;
            
            /* Pointer cursor on hover */
            cursor: pointer;
            
            /* Remove link underline */
            text-decoration: none;
            
            /* Dark text */
            color: #333;
            
            /* Bold font */
            font-weight: 600;
            
            /* Font size */
            font-size: 14px;
            
            /* Smooth transition for hover effects */
            transition: all 0.3s ease;
        }
        
        /* Period filter button hover effect */
        .period-filter a:hover {
            /* Purple border on hover */
            border-color: #667eea;
            
            /* Light purple background on hover */
            background: #f8f9ff;
        }
        
        /* Active period filter button (MATCHES DASHBOARD) */
        .period-filter a.active {
            /* Purple background */
            background: #667eea;
            
            /* White text */
            color: white;
            
            /* Purple border */
            border-color: #667eea;
        }
        
        /* Export buttons container */
        .export-buttons {
            /* Flexbox for horizontal layout */
            display: flex;
            
            /* Space between buttons */
            gap: 10px;
        }
        
        /* Individual export button */
        .export-buttons a {
            /* Internal padding */
            padding: 10px 16px;
            
            /* Green background (MATCHES DASHBOARD) */
            background: #10b981;
            
            /* White text */
            color: white;
            
            /* Remove link underline */
            text-decoration: none;
            
            /* Rounded corners */
            border-radius: 6px;
            
            /* Bold font */
            font-weight: 600;
            
            /* Font size */
            font-size: 14px;
            
            /* Smooth transition for hover effects */
            transition: all 0.3s ease;
            
            /* Remove default button border */
            border: none;
            
            /* Pointer cursor */
            cursor: pointer;
        }
        
        /* Export button hover effect */
        .export-buttons a:hover {
            /* Darker green on hover */
            background: #059669;
            
            /* Move up slightly */
            transform: translateY(-2px);
            
            /* Add shadow */
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        /* ================================================================ */
        /* STATISTICS CARDS GRID */
        /* ================================================================ */
        
        /* Grid for statistics cards */
        .stats-grid {
            /* CSS Grid layout */
            display: grid;
            
            /* Responsive grid with min 240px columns */
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            
            /* Space between cards */
            gap: 20px;
            
            /* Space below grid */
            margin-bottom: 25px;
        }
        
        /* Individual statistic card (MATCHES DASHBOARD STYLE) */
        .stat-card {
            /* White background */
            background: white;
            
            /* Internal padding */
            padding: 25px;
            
            /* Rounded corners */
            border-radius: 10px;
            
            /* Professional shadow (MATCHES DASHBOARD) */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            
            /* Left border accent */
            border-left: 4px solid #667eea;
            
            /* Smooth animation on hover */
            transition: all 0.3s ease;
        }
        
        /* Stat card hover effect */
        .stat-card:hover {
            /* Move up */
            transform: translateY(-5px);
            
            /* Deeper shadow */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        /* Stat card for success (green border) */
        .stat-card.success {
            border-left-color: #10b981;
        }
        
        /* Stat card for danger (red border) */
        .stat-card.danger {
            border-left-color: #ef4444;
        }
        
        /* Stat card for warning (orange border) */
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        
        /* Stat card heading */
        .stat-card h3 {
            /* Light grey text */
            color: #999;
            
            /* Small font size */
            font-size: 13px;
            
            /* UPPERCASE text */
            text-transform: uppercase;
            
            /* Semi-bold font */
            font-weight: 600;
            
            /* Space below heading */
            margin-bottom: 12px;
            
            /* Letter spacing for elegance */
            letter-spacing: 0.5px;
        }
        
        /* Stat card value (big number) */
        .stat-card .value {
            /* Very large font size */
            font-size: 36px;
            
            /* Bold font weight */
            font-weight: 700;
            
            /* Purple colour */
            color: #667eea;
            
            /* Tight line height */
            line-height: 1.2;
        }
        
        /* Stat card value colour overrides for different card types */
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
            /* Light grey text */
            color: #999;
            
            /* Small font size */
            font-size: 12px;
            
            /* Space above subtitle */
            margin-top: 8px;
        }
        
        /* ================================================================ */
        /* CHARTS GRID */
        /* ================================================================ */
        
        /* Grid for chart cards */
        .charts-grid {
            /* CSS Grid layout */
            display: grid;
            
            /* Responsive grid with min 500px columns */
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            
            /* Space between cards */
            gap: 20px;
            
            /* Space below grid */
            margin-bottom: 25px;
        }
        
        /* Individual chart card (MATCHES DASHBOARD STYLE) */
        .chart-card {
            /* White background */
            background: white;
            
            /* Internal padding */
            padding: 25px;
            
            /* Rounded corners */
            border-radius: 10px;
            
            /* Professional shadow (MATCHES DASHBOARD) */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            
            /* Smooth animation on hover */
            transition: all 0.3s ease;
        }
        
        /* Chart card hover effect */
        .chart-card:hover {
            /* Deeper shadow on hover */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        /* Chart card heading */
        .chart-card h3 {
            /* Space below heading */
            margin-bottom: 20px;
            
            /* Dark text colour */
            color: #333;
            
            /* Font size */
            font-size: 18px;
            
            /* Bold font weight */
            font-weight: 700;
        }
        
        /* Chart canvas container */
        .chart-container {
            /* Position relative for absolute positioning of canvas */
            position: relative;
            
            /* Fixed height for charts */
            height: 320px;
            
            /* Space below container */
            margin-bottom: 10px;
        }
        
        /* Chart description text below chart */
        .chart-description {
            /* Center text */
            text-align: center;
            
            /* Light grey text */
            color: #999;
            
            /* Small font size */
            font-size: 12px;
        }
        
        /* ================================================================ */
        /* DATA TABLES */
        /* ================================================================ */
        
        /* Data table container (MATCHES DASHBOARD STYLE) */
        .data-table {
            /* White background */
            background: white;
            
            /* Internal padding */
            padding: 25px;
            
            /* Rounded corners */
            border-radius: 10px;
            
            /* Professional shadow (MATCHES DASHBOARD) */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            
            /* Space below table */
            margin-bottom: 25px;
            
            /* Allow horizontal scrolling on small screens */
            overflow-x: auto;
        }
        
        /* Data table heading */
        .data-table h3 {
            /* Space below heading */
            margin-bottom: 20px;
            
            /* Dark text colour */
            color: #333;
            
            /* Font size */
            font-size: 18px;
            
            /* Bold font weight */
            font-weight: 700;
        }
        
        /* Table styles */
        table {
            /* Full width */
            width: 100%;
            
            /* Collapse borders */
            border-collapse: collapse;
        }
        
        /* Table head */
        thead {
            /* Light grey background */
            background: #f8f9fa;
        }
        
        /* Table header cell */
        th {
            /* Internal padding */
            padding: 14px 12px;
            
            /* Align text to left */
            text-align: left;
            
            /* Bold font */
            font-weight: 700;
            
            /* Dark text */
            color: #333;
            
            /* Border below header */
            border-bottom: 2px solid #e5e7eb;
            
            /* Font size */
            font-size: 14px;
            
            /* UPPERCASE text */
            text-transform: uppercase;
            
            /* Letter spacing */
            letter-spacing: 0.5px;
        }
        
        /* Table data cell */
        td {
            /* Internal padding */
            padding: 14px 12px;
            
            /* Border below cell */
            border-bottom: 1px solid #e5e7eb;
            
            /* Medium grey text */
            color: #666;
            
            /* Font size */
            font-size: 14px;
        }
        
        /* Table row hover effect */
        tbody tr:hover {
            /* Light background on hover */
            background: #f8f9ff;
        }
        
        /* Progress bar for trend visualization */
        .progress-bar {
            /* Full width */
            width: 100%;
            
            /* Height in pixels */
            height: 10px;
            
            /* Light grey background */
            background: #e5e7eb;
            
            /* Rounded corners */
            border-radius: 5px;
            
            /* Hide overflow */
            overflow: hidden;
        }
        
        /* Progress bar fill (the coloured part) */
        .progress-fill {
            /* Same height as bar */
            height: 100%;
            
            /* Gradient background (MATCHES DASHBOARD) */
            background: linear-gradient(90deg, #667eea, #764ba2);
            
            /* Rounded corners */
            border-radius: 5px;
            
            /* Smooth animation */
            transition: width 0.3s ease;
        }
        
        /* ================================================================ */
        /* RISK BADGES */
        /* ================================================================ */
        
        /* Risk badge styling */
        .risk-badge {
            /* Display as inline block */
            display: inline-block;
            
            /* Internal padding */
            padding: 4px 12px;
            
            /* Rounded like a pill */
            border-radius: 20px;
            
            /* Font size */
            font-size: 12px;
            
            /* Bold font */
            font-weight: 700;
        }
        
        /* Low risk badge (green) */
        .risk-low {
            /* Light green background */
            background: #d1fae5;
            
            /* Dark green text */
            color: #065f46;
        }
        
        /* Medium risk badge (orange/yellow) */
        .risk-medium {
            /* Light yellow background */
            background: #fef3c7;
            
            /* Dark brown text */
            color: #92400e;
        }
        
        /* High risk badge (red) */
        .risk-high {
            /* Light red background */
            background: #fee2e2;
            
            /* Dark red text */
            color: #991b1b;
        }
        
        /* ================================================================ */
        /* NO DATA MESSAGE */
        /* ================================================================ */
        
        /* No data message styling */
        .no-data {
            /* Center text */
            text-align: center;
            
            /* Padding around text */
            padding: 40px;
            
            /* Light grey text */
            color: #999;
            
            /* Font size */
            font-size: 16px;
        }
        
        /* ================================================================ */
        /* RESPONSIVE DESIGN (Tablets & Mobile) */
        /* ================================================================ */
        
        /* Tablet screens (max 1024px width) */
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
        
        /* Mobile screens (max 768px width) */
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
    <!-- This container holds all the dashboard content -->
    
    <div class="container">
        
        <!-- ============================================================ -->
        <!-- BACK LINK FOR NAVIGATION -->
        <!-- ============================================================ -->
        <!-- Link to return to main dashboard page -->
        
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <!-- ============================================================ -->
        <!-- HEADER SECTION -->
        <!-- ============================================================ -->
        <!-- Contains title, subtitle, period selector, and export buttons -->
        
        <div class="header">
            <!-- Main title with emoji icon -->
            <h1>📊 Analytics Dashboard</h1>
            
            <!-- Subtitle with period info and current time (UK format) -->
            <p>
                <strong><?php echo htmlspecialchars($periodLabel); ?></strong> 
                • Generated <?php echo date('d/m/Y H:i:s'); ?> (UK Time)
            </p>
            
            <!-- ========================================================== -->
            <!-- HEADER CONTROLS (Period Filter & Export Buttons) -->
            <!-- ========================================================== -->
            <!-- User can select time period and export data -->
            
            <div class="header-controls">
                
                <!-- Period filter buttons -->
                <div class="period-filter">
                    <!-- 7 days button -->
                    <a href="?period=week" class="<?php echo $period === 'week' ? 'active' : ''; ?>">
                        📅 Last 7 Days
                    </a>
                    
                    <!-- 30 days button (default) -->
                    <a href="?period=month" class="<?php echo $period === 'month' ? 'active' : ''; ?>">
                        📅 Last 30 Days
                    </a>
                    
                    <!-- 90 days button -->
                    <a href="?period=quarter" class="<?php echo $period === 'quarter' ? 'active' : ''; ?>">
                        📅 Last Quarter
                    </a>
                    
                    <!-- 365 days button -->
                    <a href="?period=year" class="<?php echo $period === 'year' ? 'active' : ''; ?>">
                        📅 Last Year
                    </a>
                </div>
                
                <!-- Export buttons -->
                <div class="export-buttons">
                    <!-- Download as CSV button -->
                    <a href="export.php?format=csv">📥 Download CSV</a>
                    
                    <!-- Download as Excel button -->
                    <a href="export.php?format=excel">📥 Download Excel</a>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- KEY STATISTICS CARDS SECTION -->
        <!-- ============================================================ -->
        <!-- Five cards showing key metrics with colour-coded values -->
        
        <div class="stats-grid">
            
            <!-- CARD 1: Total Tours (Blue) -->
            <div class="stat-card">
                <h3>Total Tours</h3>
                <div class="value"><?php echo $stats['total_tours']; ?></div>
                <div class="subtitle">in selected period</div>
            </div>
            
            <!-- CARD 2: Average Score (Orange/Warning) -->
            <div class="stat-card warning">
                <h3>Average Score</h3>
                <div class="value"><?php echo $stats['avg_score']; ?>%</div>
                <div class="subtitle">safety compliance</div>
            </div>
            
            <!-- CARD 3: Pass Items (Green/Success) -->
            <div class="stat-card success">
                <h3>Pass Items</h3>
                <div class="value"><?php echo $stats['total_passes']; ?></div>
                <div class="subtitle">compliant findings</div>
            </div>
            
            <!-- CARD 4: Fail Items (Red/Danger) -->
            <div class="stat-card danger">
                <h3>Fail Items</h3>
                <div class="value"><?php echo $stats['total_fails']; ?></div>
                <div class="subtitle">non-compliance</div>
            </div>
            
            <!-- CARD 5: Completion Rate (Blue) -->
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
            <!-- Shows average safety score and tour count over time -->
            <div class="chart-card">
                <h3>📈 Monthly Trend</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
                <div class="chart-description">Average safety score and tour count per month</div>
            </div>
            
            <!-- CHART 2: Status Distribution (Doughnut/Pie Chart) -->
            <!-- Shows proportion of Open vs Closed tours -->
            <div class="chart-card">
                <h3>📋 Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="chart-description">Open vs Closed tours</div>
            </div>
            
            <!-- CHART 3: Risk Score Distribution (Bar Chart) -->
            <!-- Shows how many tours fall into each risk level -->
            <div class="chart-card">
                <h3>⚠️ Risk Distribution</h3>
                <div class="chart-container">
                    <canvas id="riskChart"></canvas>
                </div>
                <div class="chart-description">Tours by safety score range</div>
            </div>
            
            <!-- CHART 4: Top 10 Failure Issues (Horizontal Bar Chart) -->
            <!-- Shows most frequently reported failure issues -->
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
                    // Calculate maximum count for progress bar scaling
                    $maxIssues = max(array_values($topIssues));
                    $rank = 1;
                    
                    // Loop through each issue and display in table
                    foreach ($topIssues as $issue => $count) { 
                        // Calculate percentage for progress bar width
                        $percentage = ($count / $maxIssues) * 100;
                    ?>
                    <tr>
                        <!-- Rank number (1-15) with colour styling -->
                        <td style="font-weight: 700; color: #667eea;">#<?php echo $rank; ?></td>
                        
                        <!-- Issue description (truncated to 120 characters) -->
                        <td><?php echo htmlspecialchars(substr($issue, 0, 120)); ?></td>
                        
                        <!-- Number of times this issue occurred -->
                        <td style="text-align: center; font-weight: 600;"><?php echo $count; ?></td>
                        
                        <!-- Visual progress bar showing relative frequency -->
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
            <!-- Message displayed if no failure data available -->
            <div class="data-table">
                <div class="no-data">📊 No failure data available for the selected period</div>
            </div>
        <?php } ?>
        
        <!-- ============================================================ -->
        <!-- RISK HEATMAP BY SITE TABLE -->
        <!-- ============================================================ -->
        <!-- Shows performance and risk level for each site location -->
        
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
                    // Process and display each site's statistics
                    foreach ($toursBySite as $site) { 
                        // Round scores to 1 decimal place
                        $avgScore = round((float)($site['avg_score'] ?? 0), 1);
                        $minScore = round((float)($site['min_score'] ?? 0), 1);
                        $maxScore = round((float)($site['max_score'] ?? 0), 1);
                        
                        // Determine risk level based on average score
                        // and set appropriate badge colour
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
                        <!-- Site name (bold for emphasis) -->
                        <td><strong><?php echo htmlspecialchars($site['site'] ?? 'Unknown'); ?></strong></td>
                        
                        <!-- Number of tours at this site -->
                        <td style="text-align: center;"><?php echo (int)$site['tour_count']; ?></td>
                        
                        <!-- Average score (colour-coded based on performance) -->
                        <td style="text-align: center; font-weight: 600; color: <?php echo $scoreColor; ?>">
                            <?php echo $avgScore; ?>%
                        </td>
                        
                        <!-- Min and Max score range for this site -->
                        <td style="text-align: center; font-size: 12px;">
                            <?php echo $minScore; ?>% - <?php echo $maxScore; ?>%
                        </td>
                        
                        <!-- Risk level badge with emoji indicator -->
                        <td style="text-align: center;"><?php echo $riskLevel; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
            <!-- Message displayed if no site data available -->
            <div class="data-table">
                <div class="no-data">📍 No site data available for the selected period</div>
            </div>
        <?php } ?>
    
    </div>
    <!-- END MAIN CONTAINER -->

    <!-- ================================================================ -->
    <!-- CHART.JS LIBRARY INITIALIZATION - JAVASCRIPT SECTION -->
    <!-- ================================================================ -->
    <!-- JavaScript code to create and configure all interactive charts -->
    <!-- Charts use data from the PHP section above and render with Chart.js -->
    
    <script>
        // ================================================================
        // CHART 1: MONTHLY TREND - Line Chart with Dual Y-Axes
        // ================================================================
        // Shows both average safety score AND number of tours per month
        // Uses two different Y-axes to display both metrics on same chart
        // Left axis: Safety Score %, Right axis: Tour Count
        
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx && <?php echo count($monthlyTrend); ?> > 0) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    // Month labels for X-axis (e.g., "Oct 2025", "Nov 2025")
                    labels: [<?php echo implode(',', array_map(fn($m) => '"' . htmlspecialchars($m['month_display']) . '"', $monthlyTrend)); ?>],
                    datasets: [
                        {
                            // First dataset: Average Score % (Purple line)
                            label: 'Avg Score %',
                            data: [<?php echo implode(',', array_map(fn($m) => round((float)$m['avg_score'], 1), $monthlyTrend)); ?>],
                            borderColor: '#667eea',                    // Purple line
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',  // Light purple fill
                            borderWidth: 3,                            // Thick line
                            fill: true,                               // Fill area under line
                            tension: 0.4,                             // Smooth curve
                            pointRadius: 5,                           // Large points
                            pointBackgroundColor: '#667eea',          // Purple points
                            pointBorderColor: '#fff',                 // White point borders
                            pointBorderWidth: 2,                      // Point border width
                            yAxisID: 'y'                              // Use left Y-axis
                        },
                        {
                            // Second dataset: Tour Count (Green line)
                            label: 'Tour Count',
                            data: [<?php echo implode(',', array_map(fn($m) => (int)$m['tour_count'], $monthlyTrend)); ?>],
                            borderColor: '#10b981',                   // Green line
                            backgroundColor: 'rgba(16, 185, 129, 0.1)', // Light green fill
                            borderWidth: 2,                           // Medium line width
                            fill: true,                               // Fill area under line
                            tension: 0.4,                             // Smooth curve
                            yAxisID: 'y1'                             // Use right Y-axis
                        }
                    ]
                },
                options: {
                    responsive: true,                               // Responsive to screen size
                    maintainAspectRatio: false,                     // Don't maintain aspect ratio
                    interaction: { mode: 'index', intersect: false }, // Show all data on hover
                    plugins: {
                        legend: { display: true, position: 'top' }  // Show legend at top
                    },
                    scales: {
                        // Left Y-axis: Safety Score (0-100%)
                        y: { 
                            type: 'linear',                         // Linear scale
                            display: true,                          // Display this axis
                            position: 'left',                       // Position on left
                            title: { display: true, text: 'Safety Score %' },
                            min: 0,                                 // Start at 0
                            max: 100                                // End at 100
                        },
                        // Right Y-axis: Tour Count
                        y1: {
                            type: 'linear',                         // Linear scale
                            display: true,                          // Display this axis
                            position: 'right',                      // Position on right
                            title: { display: true, text: 'Tour Count' },
                            grid: { drawOnChartArea: false }        // Don't draw grid for this axis
                        }
                    }
                }
            });
        }
        
        // ================================================================
        // CHART 2: STATUS DISTRIBUTION - Doughnut Chart
        // ================================================================
        // Shows ratio of Open vs Closed tours in doughnut/pie format
        
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',                                   // Doughnut (pie) chart type
                data: {
                    labels: ['Open', 'Closed'],                     // Chart labels
                    datasets: [{
                        data: [<?php echo $statusDistribution['Open']; ?>, <?php echo $statusDistribution['Closed']; ?>],
                        backgroundColor: ['#ef4444', '#10b981'],    // Red for Open, Green for Closed
                        borderColor: '#fff',                        // White borders between segments
                        borderWidth: 2                              // Border width
                    }]
                },
                options: {
                    responsive: true,                               // Responsive to screen size
                    maintainAspectRatio: false,                     // Don't maintain aspect ratio
                    plugins: {
                        legend: { position: 'bottom' }              // Show legend at bottom
                    }
                }
            });
        }
        
        // ================================================================
        // CHART 3: RISK SCORE DISTRIBUTION - Bar Chart
        // ================================================================
        // Shows how many tours fall into each risk category by score range
        
        const riskCtx = document.getElementById('riskChart');
        if (riskCtx) {
            new Chart(riskCtx, {
                type: 'bar',                                        // Bar chart type
                data: {
                    // X-axis labels with risk levels and percentages
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
                        // Red for high risk, Orange for medium, Blue for good, Green for excellent
                        backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
                        borderRadius: 8,                            // Rounded corners
                        borderSkipped: false                        // Draw border on all sides
                    }]
                },
                options: {
                    responsive: true,                               // Responsive to screen size
                    maintainAspectRatio
