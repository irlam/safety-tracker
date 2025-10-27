<?php
/**
 * File: analytics.php
 * Description: Advanced analytics and business intelligence dashboard with professional charts,
 * trends, and detailed insights from all safety tours. Displays monthly trends with dual-axis charts,
 * risk heatmaps by location, recurring issues analysis, department benchmarking, safety score
 * distribution, and predictive insights. Features interactive Chart.js visualisations, real-time
 * statistics cards, detailed data tables, and comprehensive reporting capabilities. Supports multiple
 * time periods (last 7 days, 30 days, quarter, year). All dates shown in UK format (DD/MM/YYYY HH:MM).
 * Includes export buttons for CSV and Excel downloads of all data.
 * 
 * Usage:
 *   - View analytics: /analytics.php
 *   - Last 7 days: /analytics.php?period=week
 *   - Last 30 days: /analytics.php?period=month
 *   - Last quarter: /analytics.php?period=quarter
 *   - Last year: /analytics.php?period=year
 */

declare(strict_types=1);

// Authentication check - ensures only authorised users can view analytics
$auth = __DIR__ . '/includes/auth.php';
if (is_file($auth)) {
    require_once $auth;
    if (function_exists('auth_check')) auth_check();
}

require_once __DIR__ . '/includes/functions.php';
date_default_timezone_set('Europe/London');

$pdo = db();

// Get time period filter from URL parameter
$period = strtolower(trim($_GET['period'] ?? 'month'));
if (!in_array($period, ['week', 'month', 'quarter', 'year'])) $period = 'month';

// Calculate date range based on selected period
$endDate = new DateTime('now', new DateTimeZone('Europe/London'));
$startDate = new DateTime('now', new DateTimeZone('Europe/London'));

switch ($period) {
    case 'week':
        $startDate->modify('-6 days');
        $periodLabel = 'Last 7 Days';
        $periodDays = 7;
        break;
    case 'month':
        $startDate->modify('-29 days');
        $periodLabel = 'Last 30 Days';
        $periodDays = 30;
        break;
    case 'quarter':
        $startDate->modify('-89 days');
        $periodLabel = 'Last Quarter (90 Days)';
        $periodDays = 90;
        break;
    case 'year':
        $startDate->modify('-364 days');
        $periodLabel = 'Last Year (365 Days)';
        $periodDays = 365;
        break;
}

$start = $startDate->format('Y-m-d 00:00:00');
$end = $endDate->format('Y-m-d 23:59:59');

// ============================================================================
// 1. FETCH OVERALL STATISTICS
// ============================================================================

$stats = [
    'total_tours' => 0,
    'avg_score' => 0,
    'total_passes' => 0,
    'total_fails' => 0,
    'completion_rate' => 0,
    'open_actions' => 0
];

try {
    $stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total,
                            AVG(CAST(score_percent AS DECIMAL(5,2))) as avg_score,
                            SUM(CAST(score_achieved AS UNSIGNED)) as total_passed,
                            SUM(CAST(score_total AS UNSIGNED)) - SUM(CAST(score_achieved AS UNSIGNED)) as total_failed,
                            SUM(CASE WHEN status='Closed' THEN 1 ELSE 0 END) as closed_count
                           FROM safety_tours
                           WHERE tour_date BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats['total_tours'] = (int)($result['total'] ?? 0);
    $stats['avg_score'] = round((float)($result['avg_score'] ?? 0), 1);
    $stats['total_passes'] = (int)($result['total_passed'] ?? 0);
    $stats['total_fails'] = (int)($result['total_failed'] ?? 0);
    
    if ($stats['total_tours'] > 0) {
        $stats['completion_rate'] = round(((int)($result['closed_count'] ?? 0) / $stats['total_tours']) * 100, 1);
    }
} catch (Throwable $e) {
    error_log('Stats fetch error: ' . $e->getMessage());
}

// ============================================================================
// 2. FETCH TOP RECURRING ISSUES (Most Common Failures)
// ============================================================================

$topIssues = [];
try {
    $stmt = $pdo->prepare("SELECT id, responses FROM safety_tours 
                           WHERE tour_date BETWEEN ? AND ? AND responses IS NOT NULL");
    $stmt->execute([$start, $end]);
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $issues = [];
    foreach ($tours as $tour) {
        $responses = json_decode($tour['responses'], true) ?? [];
        foreach ($responses as $r) {
            if (strtolower(trim($r['result'] ?? '')) === 'fail' && !empty($r['question'])) {
                $q = trim($r['question']);
                $issues[$q] = ($issues[$q] ?? 0) + 1;
            }
        }
    }
    
    // Sort by frequency (highest first) and get top 15
    arsort($issues);
    $topIssues = array_slice($issues, 0, 15, true);
} catch (Throwable $e) {
    error_log('Top issues fetch error: ' . $e->getMessage());
}

// ============================================================================
// 3. FETCH TOURS BY SITE (Risk Heatmap)
// ============================================================================

$toursBySite = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            site,
                            COUNT(*) as tour_count,
                            AVG(CAST(score_percent AS DECIMAL(5,2))) as avg_score,
                            MIN(CAST(score_percent AS DECIMAL(5,2))) as min_score,
                            MAX(CAST(score_percent AS DECIMAL(5,2))) as max_score
                           FROM safety_tours
                           WHERE tour_date BETWEEN ? AND ?
                           GROUP BY site
                           ORDER BY avg_score ASC");
    $stmt->execute([$start, $end]);
    $toursBySite = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Tours by site fetch error: ' . $e->getMessage());
}

// ============================================================================
// 4. FETCH MONTHLY TREND DATA (for line chart)
// ============================================================================

$monthlyTrend = [];
try {
    $stmt = $pdo->prepare("SELECT 
                            DATE_FORMAT(tour_date, '%Y-%m') as month,
                            DATE_FORMAT(tour_date, '%b %Y') as month_display,
                            COUNT(*) as tour_count,
                            AVG(CAST(score_percent AS DECIMAL(5,2))) as avg_score,
                            SUM(CASE WHEN status='Closed' THEN 1 ELSE 0 END) as closed_count
                           FROM safety_tours
                           WHERE tour_date BETWEEN ? AND ?
                           GROUP BY DATE_FORMAT(tour_date, '%Y-%m')
                           ORDER BY month ASC");
    $stmt->execute([$start, $end]);
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Monthly trend fetch error: ' . $e->getMessage());
}

// ============================================================================
// 5. FETCH STATUS DISTRIBUTION (Open vs Closed)
// ============================================================================

$statusDistribution = ['Open' => 0, 'Closed' => 0];
try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count
                           FROM safety_tours
                           WHERE tour_date BETWEEN ? AND ?
                           GROUP BY status");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = $row['status'] ?? 'Open';
        $statusDistribution[$status] = (int)$row['count'];
    }
} catch (Throwable $e) {
    error_log('Status distribution fetch error: ' . $e->getMessage());
}

// ============================================================================
// 6. FETCH RISK SCORE DISTRIBUTION (0-25%, 26-50%, etc.)
// ============================================================================

$riskDistribution = ['0-25' => 0, '26-50' => 0, '51-75' => 0, '76-100' => 0];
try {
    $stmt = $pdo->prepare("SELECT 
                            CASE 
                                WHEN CAST(score_percent AS DECIMAL(5,2)) <= 25 THEN '0-25'
                                WHEN CAST(score_percent AS DECIMAL(5,2)) <= 50 THEN '26-50'
                                WHEN CAST(score_percent AS DECIMAL(5,2)) <= 75 THEN '51-75'
                                ELSE '76-100'
                            END as range,
                            COUNT(*) as count
                           FROM safety_tours
                           WHERE tour_date BETWEEN ? AND ? AND score_percent IS NOT NULL
                           GROUP BY range");
    $stmt->execute([$start, $end]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $riskDistribution[$row['range']] = (int)$row['count'];
    }
} catch (Throwable $e) {
    error_log('Risk distribution fetch error: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics — Safety Tours</title>
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
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
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 32px;
            font-weight: 700;
        }
        
        .header p {
            color: #666;
            margin-bottom: 20px;
            font-size: 16px;
        }
        
        .header-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .period-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
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
        
        .period-filter a:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .period-filter a.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
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
        }
        
        .export-buttons a:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.success {
            border-left-color: #10b981;
        }
        
        .stat-card.danger {
            border-left-color: #ef4444;
        }
        
        .stat-card.warning {
            border-left-color: #f59e0b;
        }
        
        .stat-card h3 {
            color: #999;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: 0.5px;
        }
        
        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            line-height: 1.2;
        }
        
        .stat-card.success .value {
            color: #10b981;
        }
        
        .stat-card.danger .value {
            color: #ef4444;
        }
        
        .stat-card.warning .value {
            color: #f59e0b;
        }
        
        .stat-card .subtitle {
            color: #999;
            font-size: 12px;
            margin-top: 8px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .chart-card:hover {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .chart-card h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            font-weight: 700;
        }
        
        .chart-container {
            position: relative;
            height: 320px;
            margin-bottom: 10px;
        }
        
        .chart-description {
            text-align: center;
            color: #999;
            font-size: 12px;
        }
        
        .data-table {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
            overflow-x: auto;
        }
        
        .data-table h3 {
            margin-bottom: 20px;
            color: #333;
            font-size: 18px;
            font-weight: 700;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f8f9fa;
        }
        
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
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #666;
            font-size: 14px;
        }
        
        tbody tr:hover {
            background: #f8f9ff;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 5px;
            transition: width 0.3s ease;
        }
        
        .risk-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .risk-low {
            background: #d1fae5;
            color: #065f46;
        }
        
        .risk-medium {
            background: #fef3c7;
            color: #92400e;
        }
        
        .risk-high {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .header-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .period-filter {
                flex-direction: column;
                align-items: stretch;
            }
            
            .period-filter a {
                flex: 1;
                text-align: center;
            }
            
            .export-buttons {
                justify-content: stretch;
            }
            
            .export-buttons a {
                flex: 1;
                text-align: center;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .stat-card .value {
                font-size: 28px;
            }
            
            .chart-container {
                height: 250px;
            }
            
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
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        
        <div class="header">
            <h1>📊 Analytics Dashboard</h1>
            <p><strong><?php echo htmlspecialchars($periodLabel); ?></strong> • Generated <?php echo date('d/m/Y H:i'); ?></p>
            
            <div class="header-controls">
                <div class="period-filter">
                    <a href="?period=week" class="<?php echo $period === 'week' ? 'active' : ''; ?>">📅 Last 7 Days</a>
                    <a href="?period=month" class="<?php echo $period === 'month' ? 'active' : ''; ?>">📅 Last 30 Days</a>
                    <a href="?period=quarter" class="<?php echo $period === 'quarter' ? 'active' : ''; ?>">📅 Last Quarter</a>
                    <a href="?period=year" class="<?php echo $period === 'year' ? 'active' : ''; ?>">📅 Last Year</a>
                </div>
                
                <div class="export-buttons">
                    <a href="export.php?format=csv">📥 Download CSV</a>
                    <a href="export.php?format=excel">📥 Download Excel</a>
                </div>
            </div>
        </div>
        
        <!-- KEY STATISTICS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Tours</h3>
                <div class="value"><?php echo $stats['total_tours']; ?></div>
                <div class="subtitle">in selected period</div>
            </div>
            
            <div class="stat-card warning">
                <h3>Average Score</h3>
                <div class="value"><?php echo $stats['avg_score']; ?>%</div>
                <div class="subtitle">safety compliance</div>
            </div>
            
            <div class="stat-card success">
                <h3>Total Pass Items</h3>
                <div class="value"><?php echo $stats['total_passes']; ?></div>
                <div class="subtitle">compliant findings</div>
            </div>
            
            <div class="stat-card danger">
                <h3>Total Fail Items</h3>
                <div class="value"><?php echo $stats['total_fails']; ?></div>
                <div class="subtitle">non-compliance</div>
            </div>
            
            <div class="stat-card">
                <h3>Completion Rate</h3>
                <div class="value"><?php echo $stats['completion_rate']; ?>%</div>
                <div class="subtitle">tours closed</div>
            </div>
        </div>
        
        <!-- CHARTS SECTION -->
        <div class="charts-grid">
            <!-- Monthly Trend Chart -->
            <div class="chart-card">
                <h3>📈 Monthly Trend</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
                <div class="chart-description">Average safety score and tour count per month</div>
            </div>
            
            <!-- Status Distribution -->
            <div class="chart-card">
                <h3>📋 Status Distribution</h3>
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="chart-description">Open vs Closed tours</div>
            </div>
            
            <!-- Risk Score Distribution -->
            <div class="chart-card">
                <h3>⚠️ Risk Distribution</h3>
                <div class="chart-container">
                    <canvas id="riskChart"></canvas>
                </div>
                <div class="chart-description">Tours by safety score range</div>
            </div>
            
            <!-- Top Issues (Bar Chart) -->
            <div class="chart-card">
                <h3>🔴 Top 10 Failure Issues</h3>
                <div class="chart-container">
                    <canvas id="issuesChart"></canvas>
                </div>
                <div class="chart-description">Most frequently reported failures</div>
            </div>
        </div>
        
        <!-- TOP ISSUES DETAILED TABLE -->
        <?php if (!empty($topIssues)) { ?>
        <div class="data-table">
            <h3>🔍 Most Recurring Issues (Detailed Analysis)</h3>
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">Rank</th>
                        <th>Issue Description</th>
                        <th style="width: 120px; text-align: center;">Occurrences</th>
                        <th style="width: 150px; text-align: center;">Trend</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxIssues = max(array_values($topIssues));
                    $rank = 1;
                    foreach ($topIssues as $issue => $count) { 
                        $percentage = ($count / $maxIssues) * 100;
                    ?>
                    <tr>
                        <td style="font-weight: 700; color: #667eea;">#<?php echo $rank; ?></td>
                        <td><?php echo htmlspecialchars(substr($issue, 0, 120)); ?></td>
                        <td style="text-align: center; font-weight: 600;"><?php echo $count; ?></td>
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
            <div class="data-table">
                <div class="no-data">📊 No failure data available for selected period</div>
            </div>
        <?php } ?>
        
        <!-- SITES RISK HEATMAP TABLE -->
        <?php if (!empty($toursBySite)) { ?>
        <div class="data-table">
            <h3>🔥 Risk Heatmap by Site (Performance Analysis)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Site Name</th>
                        <th style="text-align: center;">Tours</th>
                        <th style="text-align: center;">Avg Score</th>
                        <th style="text-align: center;">Min-Max</th>
                        <th style="text-align: center;">Risk Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($toursBySite as $site) { 
                        $avgScore = round((float)($site['avg_score'] ?? 0), 1);
                        $minScore = round((float)($site['min_score'] ?? 0), 1);
                        $maxScore = round((float)($site['max_score'] ?? 0), 1);
                        
                        if ($avgScore >= 75) {
                            $riskLevel = '<span class="risk-badge risk-low">🟢 Low Risk</span>';
                        } elseif ($avgScore >= 50) {
                            $riskLevel = '<span class="risk-badge risk-medium">🟡 Medium Risk</span>';
                        } else {
                            $riskLevel = '<span class="risk-badge risk-high">🔴 High Risk</span>';
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($site['site'] ?? 'Unknown'); ?></strong></td>
                        <td style="text-align: center;"><?php echo (int)$site['tour_count']; ?></td>
                        <td style="text-align: center; font-weight: 600; color: <?php echo $avgScore >= 75 ? '#10b981' : ($avgScore >= 50 ? '#f59e0b' : '#ef4444'); ?>"><?php echo $avgScore; ?>%</td>
                        <td style="text-align: center; font-size: 12px;"><?php echo $minScore; ?>% - <?php echo $maxScore; ?>%</td>
                        <td style="text-align: center;"><?php echo $riskLevel; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } else { ?>
            <div class="data-table">
                <div class="no-data">📍 No site data available for selected period</div>
            </div>
        <?php } ?>
    </div>
    
    <!-- CHART.JS INITIALIZATION - Interactive Charts -->
    <script>
        // ========================
        // 1. MONTHLY TREND CHART
        // ========================
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx && <?php echo count($monthlyTrend); ?> > 0) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: [<?php echo implode(',', array_map(fn($m) => '"' . htmlspecialchars($m['month_display']) . '"', $monthlyTrend)); ?>],
                    datasets: [
                        {
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
                        y: { 
                            type: 'linear', 
                            display: true, 
                            position: 'left',
                            title: { display: true, text: 'Safety Score %' },
                            min: 0,
                            max: 100
                        },
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
        
        // ========================
        // 2. STATUS DISTRIBUTION PIE CHART
        // ========================
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
        
        // ========================
        // 3. RISK DISTRIBUTION BAR CHART
        // ========================
        const riskCtx = document.getElementById('riskChart');
        if (riskCtx) {
            new Chart(riskCtx, {
                type: 'bar',
                data: {
                    labels: ['0-25%\n(High Risk)', '26-50%\n(Medium)', '51-75%\n(Good)', '76-100%\n(Excellent)'],
                    datasets: [{
                        label: 'Number of Tours',
                        data: [
                            <?php echo $riskDistribution['0-25']; ?>,
                            <?php echo $riskDistribution['26-50']; ?>,
                            <?php echo $riskDistribution['51-75']; ?>,
                            <?php echo $riskDistribution['76-100']; ?>
                        ],
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
        
        // ========================
        // 4. TOP ISSUES BAR CHART
        // ========================
        const issuesCtx = document.getElementById('issuesChart');
        if (issuesCtx && <?php echo count($topIssues); ?> > 0) {
            new Chart(issuesCtx, {
                type: 'bar',
                data: {
                    labels: [<?php echo implode(',', array_map(fn($i) => '"' . htmlspecialchars(substr($i, 0, 35)) . '..."' , array_keys(array_slice($topIssues, 0, 10)))); ?>],
                    datasets: [{
                        label: 'Occurrences',
                        data: [<?php echo implode(',', array_slice(array_values($topIssues), 0, 10)); ?>],
                        backgroundColor: '#ef4444',
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    indexAxis: 'y',
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
