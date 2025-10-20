<?php
// =============================================================================
// TEMPLATE NAME: Capital Gains Analyzer
// -----------------------------------------------------------------------------
// Comprehensive capital gains analysis and visualization for cryptocurrency
// =============================================================================

// Include configuration
require_once get_stylesheet_directory() . '/dashboard/database-config.php';
include get_stylesheet_directory() . '/dashboard/header-dashboard.php';

// Start session and basic security
session_start();
if (!isset($_SESSION['token_user'])) {
    $_SESSION['token_user'] = bin2hex(random_bytes(32));
}

// Basic WordPress functions simulation (for standalone use)
if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return 'CryptoTax';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        return (object) [
            'ID' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'user_login' => 'admin'
        ];
    }
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$error = '';
$gains_data = null;
$summary = null;
$charts_data = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'analyze_gains') {
        $start_date = $_POST['start_date'] ?? date('Y-01-01');
        $end_date = $_POST['end_date'] ?? date('Y-12-31');
        $cost_basis_method = $_POST['cost_basis_method'] ?? 'FIFO';
        $selected_assets = $_POST['selected_assets'] ?? [];
        $view_type = $_POST['view_type'] ?? 'overview';
        
        // Process transactions for capital gains analysis
        $gains_data = processTransactionsForCapitalGains($pdo, $start_date, $end_date, $cost_basis_method, $selected_assets);
        $summary = calculateCapitalGainsSummary($gains_data);
        $charts_data = generateChartsData($gains_data, $summary);
        $message = "Capital gains analysis completed for period {$start_date} to {$end_date}";
    }
}

// Get available assets
$available_assets = [];
try {
    $sql = "SELECT DISTINCT w_symbol as asset FROM vd_user_transactions 
            WHERE userid = 1 AND w_symbol IS NOT NULL AND w_symbol != ''
            UNION
            SELECT DISTINCT b_symbol as asset FROM vd_user_transactions 
            WHERE userid = 1 AND b_symbol IS NOT NULL AND b_symbol != ''
            ORDER BY asset ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $assets = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $available_assets = array_unique($assets);
} catch(PDOException $e) {
    $error = "Error fetching assets: " . $e->getMessage();
}

// Process transactions for capital gains analysis
function processTransactionsForCapitalGains($pdo, $start_date, $end_date, $cost_basis_method, $selected_assets) {
    $start_datetime = $start_date . ' 00:00:00';
    $end_datetime = $end_date . ' 23:59:59';
    
    // Build query conditions
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_datetime, $end_datetime];
    
    // Filter by selected assets
    if (!empty($selected_assets)) {
        $asset_conditions = [];
        foreach ($selected_assets as $asset) {
            $asset_conditions[] = "(w_symbol = ? OR b_symbol = ?)";
            $params[] = $asset;
            $params[] = $asset;
        }
        $where_conditions[] = "(" . implode(' OR ', $asset_conditions) . ")";
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Get all transactions for the period
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE $where_sql 
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate purchases and sales
    $purchases = [];
    $sales = [];
    
    foreach ($all_transactions as $transaction) {
        // Check for purchases (bought side)
        if ($transaction['w_status'] === 'Buy' && $transaction['w_total'] > 0) {
            $purchases[] = [
                'id' => $transaction['id'],
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['w_symbol']),
                'amount' => $transaction['w_total'],
                'price_per_unit' => $transaction['b_total'] / $transaction['w_total'],
                'total_cost' => $transaction['b_total'],
                'notes' => $transaction['w_note']
            ];
        }
        
        // Check for sales (sold side)
        if ($transaction['status'] === 'Sale' && $transaction['b_total'] > 0) {
            $sales[] = [
                'id' => $transaction['id'],
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['b_symbol']),
                'amount' => $transaction['b_total'],
                'price_per_unit' => $transaction['w_total'] / $transaction['b_total'],
                'total_proceeds' => $transaction['w_total'],
                'notes' => $transaction['b_note']
            ];
        }
    }
    
    // Process sales and calculate gains/losses
    $gains_data = [];
    
    foreach ($sales as $sale) {
        $asset = $sale['asset'];
        $amount_sold = $sale['amount'];
        $proceeds = $sale['total_proceeds'];
        
        // Calculate cost basis using specified method
        $cost_basis = calculateCostBasisForGains($asset, $amount_sold, $purchases, $cost_basis_method);
        
        $gain_loss = $proceeds - $cost_basis;
        $gain_loss_percentage = $cost_basis > 0 ? ($gain_loss / $cost_basis) * 100 : 0;
        
        // Determine if short-term or long-term (assuming 1 year threshold)
        $sale_date = new DateTime($sale['date']);
        $is_long_term = true; // Default to long-term, will be updated based on purchase dates
        
        // Check if any purchases were within 1 year
        $asset_purchases = array_filter($purchases, function($purchase) use ($asset) {
            return $purchase['asset'] === $asset;
        });
        
        foreach ($asset_purchases as $purchase) {
            $purchase_date = new DateTime($purchase['date']);
            $days_diff = $sale_date->diff($purchase_date)->days;
            if ($days_diff <= 365) {
                $is_long_term = false;
                break;
            }
        }
        
        $gains_data[] = [
            'id' => $sale['id'],
            'date' => $sale['date'],
            'asset' => $asset,
            'amount_sold' => $amount_sold,
            'proceeds' => $proceeds,
            'cost_basis' => $cost_basis,
            'gain_loss' => $gain_loss,
            'gain_loss_percentage' => $gain_loss_percentage,
            'is_long_term' => $is_long_term,
            'notes' => $sale['notes'],
            'cost_basis_method' => $cost_basis_method
        ];
    }
    
    return $gains_data;
}

// Calculate cost basis for gains analysis
function calculateCostBasisForGains($asset, $amount_sold, $purchases, $method) {
    $asset_purchases = array_filter($purchases, function($purchase) use ($asset) {
        return $purchase['asset'] === $asset;
    });
    
    if (empty($asset_purchases)) {
        return $amount_sold * 0.8; // Fallback estimate
    }
    
    switch ($method) {
        case 'FIFO':
            return calculateFIFOCostBasisForGains($asset_purchases, $amount_sold);
        case 'LIFO':
            return calculateLIFOCostBasisForGains($asset_purchases, $amount_sold);
        case 'HIFO':
            return calculateHIFOCostBasisForGains($asset_purchases, $amount_sold);
        case 'Average Cost Basis':
            return calculateAverageCostBasisForGains($asset_purchases, $amount_sold);
        default:
            return calculateFIFOCostBasisForGains($asset_purchases, $amount_sold);
    }
}

// FIFO Cost Basis Calculation for Gains
function calculateFIFOCostBasisForGains($purchases, $amount_sold) {
    usort($purchases, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    $remaining_to_sell = $amount_sold;
    $total_cost_basis = 0;
    
    foreach ($purchases as $purchase) {
        if ($remaining_to_sell <= 0) break;
        
        $amount_from_this_purchase = min($remaining_to_sell, $purchase['amount']);
        $cost_basis_from_this_purchase = $amount_from_this_purchase * $purchase['price_per_unit'];
        $total_cost_basis += $cost_basis_from_this_purchase;
        $remaining_to_sell -= $amount_from_this_purchase;
    }
    
    return $total_cost_basis;
}

// LIFO Cost Basis Calculation for Gains
function calculateLIFOCostBasisForGains($purchases, $amount_sold) {
    usort($purchases, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    $remaining_to_sell = $amount_sold;
    $total_cost_basis = 0;
    
    foreach ($purchases as $purchase) {
        if ($remaining_to_sell <= 0) break;
        
        $amount_from_this_purchase = min($remaining_to_sell, $purchase['amount']);
        $cost_basis_from_this_purchase = $amount_from_this_purchase * $purchase['price_per_unit'];
        $total_cost_basis += $cost_basis_from_this_purchase;
        $remaining_to_sell -= $amount_from_this_purchase;
    }
    
    return $total_cost_basis;
}

// HIFO Cost Basis Calculation for Gains
function calculateHIFOCostBasisForGains($purchases, $amount_sold) {
    usort($purchases, function($a, $b) {
        return $b['price_per_unit'] - $a['price_per_unit'];
    });
    
    $remaining_to_sell = $amount_sold;
    $total_cost_basis = 0;
    
    foreach ($purchases as $purchase) {
        if ($remaining_to_sell <= 0) break;
        
        $amount_from_this_purchase = min($remaining_to_sell, $purchase['amount']);
        $cost_basis_from_this_purchase = $amount_from_this_purchase * $purchase['price_per_unit'];
        $total_cost_basis += $cost_basis_from_this_purchase;
        $remaining_to_sell -= $amount_from_this_purchase;
    }
    
    return $total_cost_basis;
}

// Average Cost Basis Calculation for Gains
function calculateAverageCostBasisForGains($purchases, $amount_sold) {
    $total_amount = 0;
    $total_cost = 0;
    
    foreach ($purchases as $purchase) {
        $total_amount += $purchase['amount'];
        $total_cost += $purchase['amount'] * $purchase['price_per_unit'];
    }
    
    if ($total_amount == 0) return 0;
    
    $average_cost_per_unit = $total_cost / $total_amount;
    return $amount_sold * $average_cost_per_unit;
}

// Calculate capital gains summary
function calculateCapitalGainsSummary($gains_data) {
    $summary = [
        'total_transactions' => count($gains_data),
        'total_proceeds' => 0,
        'total_cost_basis' => 0,
        'total_gain_loss' => 0,
        'short_term_gain_loss' => 0,
        'long_term_gain_loss' => 0,
        'short_term_transactions' => 0,
        'long_term_transactions' => 0,
        'by_asset' => [],
        'by_month' => [],
        'by_quarter' => [],
        'winning_transactions' => 0,
        'losing_transactions' => 0,
        'largest_gain' => 0,
        'largest_loss' => 0,
        'average_gain_loss' => 0
    ];
    
    foreach ($gains_data as $transaction) {
        $summary['total_proceeds'] += $transaction['proceeds'];
        $summary['total_cost_basis'] += $transaction['cost_basis'];
        $summary['total_gain_loss'] += $transaction['gain_loss'];
        
        if ($transaction['is_long_term']) {
            $summary['long_term_gain_loss'] += $transaction['gain_loss'];
            $summary['long_term_transactions']++;
        } else {
            $summary['short_term_gain_loss'] += $transaction['gain_loss'];
            $summary['short_term_transactions']++;
        }
        
        // Categorize by asset
        $asset = $transaction['asset'];
        if (!isset($summary['by_asset'][$asset])) {
            $summary['by_asset'][$asset] = [
                'count' => 0,
                'proceeds' => 0,
                'cost_basis' => 0,
                'gain_loss' => 0,
                'short_term_gain_loss' => 0,
                'long_term_gain_loss' => 0
            ];
        }
        $summary['by_asset'][$asset]['count']++;
        $summary['by_asset'][$asset]['proceeds'] += $transaction['proceeds'];
        $summary['by_asset'][$asset]['cost_basis'] += $transaction['cost_basis'];
        $summary['by_asset'][$asset]['gain_loss'] += $transaction['gain_loss'];
        
        if ($transaction['is_long_term']) {
            $summary['by_asset'][$asset]['long_term_gain_loss'] += $transaction['gain_loss'];
        } else {
            $summary['by_asset'][$asset]['short_term_gain_loss'] += $transaction['gain_loss'];
        }
        
        // Categorize by month
        $month = date('Y-m', strtotime($transaction['date']));
        if (!isset($summary['by_month'][$month])) {
            $summary['by_month'][$month] = [
                'count' => 0,
                'proceeds' => 0,
                'cost_basis' => 0,
                'gain_loss' => 0
            ];
        }
        $summary['by_month'][$month]['count']++;
        $summary['by_month'][$month]['proceeds'] += $transaction['proceeds'];
        $summary['by_month'][$month]['cost_basis'] += $transaction['cost_basis'];
        $summary['by_month'][$month]['gain_loss'] += $transaction['gain_loss'];
        
        // Categorize by quarter
        $quarter = 'Q' . ceil(date('n', strtotime($transaction['date'])) / 3) . ' ' . date('Y', strtotime($transaction['date']));
        if (!isset($summary['by_quarter'][$quarter])) {
            $summary['by_quarter'][$quarter] = [
                'count' => 0,
                'proceeds' => 0,
                'cost_basis' => 0,
                'gain_loss' => 0
            ];
        }
        $summary['by_quarter'][$quarter]['count']++;
        $summary['by_quarter'][$quarter]['proceeds'] += $transaction['proceeds'];
        $summary['by_quarter'][$quarter]['cost_basis'] += $transaction['cost_basis'];
        $summary['by_quarter'][$quarter]['gain_loss'] += $transaction['gain_loss'];
        
        // Track winning/losing transactions
        if ($transaction['gain_loss'] > 0) {
            $summary['winning_transactions']++;
            $summary['largest_gain'] = max($summary['largest_gain'], $transaction['gain_loss']);
        } else {
            $summary['losing_transactions']++;
            $summary['largest_loss'] = min($summary['largest_loss'], $transaction['gain_loss']);
        }
    }
    
    // Calculate average gain/loss
    if ($summary['total_transactions'] > 0) {
        $summary['average_gain_loss'] = $summary['total_gain_loss'] / $summary['total_transactions'];
    }
    
    return $summary;
}

// Generate charts data
function generateChartsData($gains_data, $summary) {
    $charts = [
        'monthly_gains' => [],
        'asset_breakdown' => [],
        'gain_loss_distribution' => [],
        'quarterly_trends' => []
    ];
    
    // Monthly gains data
    foreach ($summary['by_month'] as $month => $data) {
        $charts['monthly_gains'][] = [
            'month' => $month,
            'gain_loss' => $data['gain_loss'],
            'proceeds' => $data['proceeds'],
            'cost_basis' => $data['cost_basis']
        ];
    }
    
    // Asset breakdown data
    foreach ($summary['by_asset'] as $asset => $data) {
        $charts['asset_breakdown'][] = [
            'asset' => $asset,
            'gain_loss' => $data['gain_loss'],
            'count' => $data['count']
        ];
    }
    
    // Gain/Loss distribution
    $charts['gain_loss_distribution'] = [
        'winning' => $summary['winning_transactions'],
        'losing' => $summary['losing_transactions']
    ];
    
    // Quarterly trends
    foreach ($summary['by_quarter'] as $quarter => $data) {
        $charts['quarterly_trends'][] = [
            'quarter' => $quarter,
            'gain_loss' => $data['gain_loss']
        ];
    }
    
    return $charts;
}

// Cost basis methods
$cost_basis_methods = [
    'FIFO' => 'First In, First Out',
    'LIFO' => 'Last In, First Out',
    'HIFO' => 'Highest In, First Out',
    'Average Cost Basis' => 'Average Cost Basis'
];

// View types
$view_types = [
    'overview' => 'Overview',
    'detailed' => 'Detailed Transactions',
    'by_asset' => 'By Asset',
    'by_time' => 'By Time Period'
];
?>

<style>
    /* CryptoTax Theme Integration */
    :root {
        --primary-blue: #0043FF;
        --primary-purple: #A370F1;
        --primary-cyan: #4BF2E6;
        --bright-blue: #0065FF;
        --light-gray: #EEEEEE;
        --medium-gray: #777777;
        --dark-gray: #333333;
        --white: #FFFFFF;
        --success-green: #28a745;
        --danger-red: #dc3545;
        --warning-orange: #ffc107;
        --info-blue: #17a2b8;
    }

    .container {
        padding: 30px;
    }

    /* Header */
    .page-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0, 67, 255, 0.15);
    }

    .page-header h1 {
        font-size: 2rem;
        margin-bottom: 10px;
        color: var(--white);
    }

    .page-header p {
        opacity: 0.9;
        font-size: 1.1rem;
    }

    /* Alerts */
    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background: #d4edda;
        color: #155724;
        border-left: 4px solid #28a745;
    }

    .alert-error {
        background: #f8d7da;
        color: #721c24;
        border-left: 4px solid #dc3545;
    }

    .alert-info {
        background: #d1ecf1;
        color: #0c5460;
        border-left: 4px solid #17a2b8;
    }

    /* Form Container */
    .form-container {
        background: var(--white);
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 25px;
    }

    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .form-header h3 {
        color: var(--dark-gray);
        font-size: 1.2rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-group label {
        font-weight: 600;
        color: var(--dark-gray);
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        background: var(--white);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-blue);
        box-shadow: 0 0 0 3px rgba(0, 67, 255, 0.1);
    }

    .form-help {
        font-size: 0.8rem;
        color: var(--medium-gray);
        margin-top: 5px;
    }

    /* Asset Selection */
    .asset-selection {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid rgba(0, 67, 255, 0.1);
    }

    .asset-selection h4 {
        color: var(--dark-gray);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .asset-checkboxes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }

    .asset-checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: var(--white);
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        transition: all 0.2s ease;
    }

    .asset-checkbox-item:hover {
        border-color: var(--primary-blue);
        background: rgba(0, 67, 255, 0.02);
    }

    .asset-checkbox-item input[type="checkbox"] {
        margin: 0;
    }

    .asset-checkbox-item label {
        margin: 0;
        font-weight: 500;
        color: var(--dark-gray);
        cursor: pointer;
        flex: 1;
    }

    /* Buttons */
    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, var(--primary-cyan) 0%, var(--bright-blue) 100%);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 67, 255, 0.3);
    }

    .btn-success {
        background: var(--success-green);
        color: var(--white);
    }

    .btn-success:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    .btn-secondary {
        background: var(--medium-gray);
        color: var(--white);
    }

    .btn-secondary:hover {
        background: var(--dark-gray);
    }

    /* Summary Cards */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: var(--white);
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--primary-blue);
        transition: transform 0.2s ease;
    }

    .summary-card:hover {
        transform: translateY(-2px);
    }

    .summary-card h4 {
        color: var(--dark-gray);
        margin-bottom: 10px;
        font-size: 1rem;
    }

    .summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
    }

    .summary-value.positive {
        color: var(--success-green);
    }

    .summary-value.negative {
        color: var(--danger-red);
    }

    .summary-subtitle {
        font-size: 0.8rem;
        color: var(--medium-gray);
        margin-top: 5px;
    }

    /* Charts Container */
    .charts-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .chart-card {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .chart-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 15px 20px;
    }

    .chart-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.1rem;
    }

    .chart-content {
        padding: 20px;
        min-height: 300px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Breakdown Sections */
    .breakdown-section {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .breakdown-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
    }

    .breakdown-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .breakdown-content {
        padding: 25px;
    }

    .breakdown-item {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        transition: all 0.2s ease;
    }

    .breakdown-item:hover {
        background: var(--white);
        border-color: var(--primary-blue);
        box-shadow: 0 4px 15px rgba(0, 67, 255, 0.1);
    }

    .breakdown-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .breakdown-item-name {
        font-weight: 600;
        color: var(--primary-blue);
        font-size: 1.1rem;
    }

    .breakdown-item-count {
        background: var(--primary-blue);
        color: var(--white);
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: bold;
    }

    .breakdown-item-totals {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .breakdown-total-item {
        text-align: center;
    }

    .breakdown-total-label {
        font-size: 0.8rem;
        color: var(--medium-gray);
        margin-bottom: 5px;
    }

    .breakdown-total-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .breakdown-total-value.positive {
        color: var(--success-green);
    }

    .breakdown-total-value.negative {
        color: var(--danger-red);
    }

    /* Gains Table */
    .gains-table-container {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .gains-table-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .gains-table-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .gains-table-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .gains-table {
        width: 100%;
        border-collapse: collapse;
    }

    .gains-table th,
    .gains-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }

    .gains-table th {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        font-weight: 600;
        color: var(--dark-gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
    }

    .gains-table tbody tr:hover {
        background: rgba(0, 67, 255, 0.02);
    }

    .gains-table .number {
        text-align: right;
        font-family: 'Courier New', monospace;
    }

    .term-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--white);
    }

    .short-term-badge {
        background: var(--warning-orange);
    }

    .long-term-badge {
        background: var(--info-blue);
    }

    .method-badge {
        background: var(--primary-purple);
        color: var(--white);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 5px;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--medium-gray);
    }

    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state h3 {
        color: var(--dark-gray);
        margin-bottom: 10px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .container {
            padding: 15px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .summary-grid {
            grid-template-columns: 1fr;
        }

        .charts-container {
            grid-template-columns: 1fr;
        }

        .asset-checkboxes {
            grid-template-columns: 1fr;
        }

        .gains-table {
            font-size: 0.8rem;
        }

        .gains-table th,
        .gains-table td {
            padding: 8px;
        }

        .gains-table-actions {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1>📈 Capital Gains Analyzer</h1>
        <p>Comprehensive analysis of your cryptocurrency capital gains and losses</p>
    </div>

    <!-- Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            ✓ <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            ✗ <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Analysis Form -->
    <div class="form-container">
        <div class="form-header">
            <h3>⚙️ Analyze Capital Gains</h3>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="analyze_gains">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" 
                           value="<?php echo $_POST['start_date'] ?? date('Y-01-01'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" 
                           value="<?php echo $_POST['end_date'] ?? date('Y-12-31'); ?>" required>
                </div>

                <div class="form-group">
                    <label for="cost_basis_method">Cost Basis Method</label>
                    <select name="cost_basis_method" id="cost_basis_method" class="form-control">
                        <?php foreach ($cost_basis_methods as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($_POST['cost_basis_method'] ?? 'FIFO') == $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="view_type">View Type</label>
                    <select name="view_type" id="view_type" class="form-control">
                        <?php foreach ($view_types as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($_POST['view_type'] ?? 'overview') == $value ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Asset Selection -->
            <?php if (!empty($available_assets)): ?>
                <div class="asset-selection">
                    <h4>💰 Select Assets to Include</h4>
                    <div class="asset-checkboxes">
                        <?php foreach ($available_assets as $asset): ?>
                            <div class="asset-checkbox-item">
                                <input type="checkbox" 
                                       name="selected_assets[]" 
                                       value="<?php echo $asset; ?>" 
                                       id="asset_<?php echo $asset; ?>"
                                       <?php echo in_array($asset, $_POST['selected_assets'] ?? []) ? 'checked' : ''; ?>>
                                <label for="asset_<?php echo $asset; ?>">
                                    <?php echo htmlspecialchars($asset); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    ℹ️ No assets found. Please add some transactions first.
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">
                📊 Analyze Capital Gains
            </button>
        </form>
    </div>

    <?php if ($gains_data && $summary): ?>
        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Transactions</h4>
                <div class="summary-value"><?php echo $summary['total_transactions']; ?></div>
                <div class="summary-subtitle">Sales processed</div>
            </div>
            <div class="summary-card">
                <h4>Total Proceeds</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_proceeds'], 2); ?></div>
                <div class="summary-subtitle">From all sales</div>
            </div>
            <div class="summary-card">
                <h4>Total Cost Basis</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_cost_basis'], 2); ?></div>
                <div class="summary-subtitle">Total cost basis</div>
            </div>
            <div class="summary-card">
                <h4>Net Gain/Loss</h4>
                <div class="summary-value <?php echo $summary['total_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($summary['total_gain_loss'], 2); ?>
                </div>
                <div class="summary-subtitle">Overall performance</div>
            </div>
            <div class="summary-card">
                <h4>Short-term Gain/Loss</h4>
                <div class="summary-value <?php echo $summary['short_term_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($summary['short_term_gain_loss'], 2); ?>
                </div>
                <div class="summary-subtitle"><?php echo $summary['short_term_transactions']; ?> transactions</div>
            </div>
            <div class="summary-card">
                <h4>Long-term Gain/Loss</h4>
                <div class="summary-value <?php echo $summary['long_term_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($summary['long_term_gain_loss'], 2); ?>
                </div>
                <div class="summary-subtitle"><?php echo $summary['long_term_transactions']; ?> transactions</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-container">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>📊 Monthly Gains/Losses</h3>
                </div>
                <div class="chart-content">
                    <canvas id="monthlyChart" width="400" height="300"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-header">
                    <h3>💰 Asset Performance</h3>
                </div>
                <div class="chart-content">
                    <canvas id="assetChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>

        <!-- Asset Breakdown -->
        <?php if (!empty($summary['by_asset'])): ?>
            <div class="breakdown-section">
                <div class="breakdown-header">
                    <h3>💰 Breakdown by Asset</h3>
                </div>
                <div class="breakdown-content">
                    <?php foreach ($summary['by_asset'] as $asset => $data): ?>
                        <div class="breakdown-item">
                            <div class="breakdown-item-header">
                                <div class="breakdown-item-name"><?php echo htmlspecialchars($asset); ?></div>
                                <div class="breakdown-item-count"><?php echo $data['count']; ?> transactions</div>
                            </div>
                            <div class="breakdown-item-totals">
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Proceeds</div>
                                    <div class="breakdown-total-value">$<?php echo number_format($data['proceeds'], 2); ?></div>
                                </div>
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Cost Basis</div>
                                    <div class="breakdown-total-value">$<?php echo number_format($data['cost_basis'], 2); ?></div>
                                </div>
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Total Gain/Loss</div>
                                    <div class="breakdown-total-value <?php echo $data['gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($data['gain_loss'], 2); ?>
                                    </div>
                                </div>
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Short-term</div>
                                    <div class="breakdown-total-value <?php echo $data['short_term_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($data['short_term_gain_loss'], 2); ?>
                                    </div>
                                </div>
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Long-term</div>
                                    <div class="breakdown-total-value <?php echo $data['long_term_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($data['long_term_gain_loss'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Detailed Gains Table -->
        <div class="gains-table-container">
            <div class="gains-table-header">
                <h3>📋 Detailed Capital Gains</h3>
                <div class="gains-table-actions">
                    <button class="btn btn-success" onclick="exportToPDF()">📄 Export PDF</button>
                    <button class="btn btn-secondary" onclick="printReport()">🖨️ Print</button>
                    <button class="btn btn-secondary" onclick="exportToCSV()">📊 Export CSV</button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="gains-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">Date</th>
                            <th style="width: 12%;">Asset</th>
                            <th style="width: 10%;">Amount Sold</th>
                            <th style="width: 12%;">Proceeds</th>
                            <th style="width: 12%;">Cost Basis</th>
                            <th style="width: 12%;">Gain/Loss</th>
                            <th style="width: 8%;">%</th>
                            <th style="width: 10%;">Term</th>
                            <th style="width: 14%;">Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gains_data as $transaction): ?>
                        <tr>
                            <td><?php echo date('m/d/Y', strtotime($transaction['date'])); ?></td>
                            <td><?php echo htmlspecialchars($transaction['asset']); ?></td>
                            <td class="number"><?php echo number_format($transaction['amount_sold'], 8); ?></td>
                            <td class="number">$<?php echo number_format($transaction['proceeds'], 2); ?></td>
                            <td class="number">$<?php echo number_format($transaction['cost_basis'], 2); ?></td>
                            <td class="number <?php echo $transaction['gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format($transaction['gain_loss'], 2); ?>
                            </td>
                            <td class="number <?php echo $transaction['gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                <?php echo number_format($transaction['gain_loss_percentage'], 1); ?>%
                            </td>
                            <td>
                                <span class="term-badge <?php echo $transaction['is_long_term'] ? 'long-term-badge' : 'short-term-badge'; ?>">
                                    <?php echo $transaction['is_long_term'] ? 'Long-term' : 'Short-term'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="method-badge"><?php echo htmlspecialchars($transaction['cost_basis_method']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart data from PHP
    const chartsData = <?php echo json_encode($charts_data ?? []); ?>;
    const summary = <?php echo json_encode($summary ?? []); ?>;

    // Initialize charts when data is available
    document.addEventListener('DOMContentLoaded', function() {
        if (chartsData && Object.keys(chartsData).length > 0) {
            initMonthlyChart();
            initAssetChart();
        }
    });

    // Monthly gains chart
    function initMonthlyChart() {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyData = chartsData.monthly_gains || [];
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Gain/Loss',
                    data: monthlyData.map(item => item.gain_loss),
                    borderColor: '#0043FF',
                    backgroundColor: 'rgba(0, 67, 255, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });
    }

    // Asset performance chart
    function initAssetChart() {
        const ctx = document.getElementById('assetChart').getContext('2d');
        const assetData = chartsData.asset_breakdown || [];
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: assetData.map(item => item.asset),
                datasets: [{
                    data: assetData.map(item => Math.abs(item.gain_loss)),
                    backgroundColor: [
                        '#0043FF',
                        '#A370F1',
                        '#4BF2E6',
                        '#28a745',
                        '#ffc107',
                        '#dc3545',
                        '#17a2b8'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Export to PDF functionality
    function exportToPDF() {
        alert('PDF export functionality would be implemented here. Consider using libraries like jsPDF, Puppeteer, or server-side PDF generation.');
    }

    // Print functionality
    function printReport() {
        window.print();
    }

    // Export to CSV functionality
    function exportToCSV() {
        const gainsData = <?php echo json_encode($gains_data ?? []); ?>;
        const summary = <?php echo json_encode($summary ?? []); ?>;
        
        if (gainsData.length === 0) {
            alert('No data to export');
            return;
        }
        
        let csv = 'Date,Asset,Amount Sold,Proceeds,Cost Basis,Gain/Loss,Gain/Loss %,Term,Method\n';
        
        gainsData.forEach(transaction => {
            csv += `"${transaction.date}","${transaction.asset}","${transaction.amount_sold}","${transaction.proceeds}","${transaction.cost_basis}","${transaction.gain_loss}","${transaction.gain_loss_percentage}","${transaction.is_long_term ? 'Long-term' : 'Short-term'}","${transaction.cost_basis_method}"\n`;
        });
        
        // Add summary data
        csv += '\n\nSummary\n';
        csv += `Total Transactions,${summary.total_transactions}\n`;
        csv += `Total Proceeds,${summary.total_proceeds}\n`;
        csv += `Total Cost Basis,${summary.total_cost_basis}\n`;
        csv += `Total Gain/Loss,${summary.total_gain_loss}\n`;
        csv += `Short-term Gain/Loss,${summary.short_term_gain_loss}\n`;
        csv += `Long-term Gain/Loss,${summary.long_term_gain_loss}\n`;
        csv += `Winning Transactions,${summary.winning_transactions}\n`;
        csv += `Losing Transactions,${summary.losing_transactions}\n`;
        
        // Add asset breakdown
        csv += '\n\nAsset Breakdown\n';
        csv += 'Asset,Count,Proceeds,Cost Basis,Gain/Loss,Short-term,Long-term\n';
        Object.entries(summary.by_asset || {}).forEach(([asset, data]) => {
            csv += `"${asset}","${data.count}","${data.proceeds}","${data.cost_basis}","${data.gain_loss}","${data.short_term_gain_loss}","${data.long_term_gain_loss}"\n`;
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `capital_gains_${document.getElementById('start_date').value}_to_${document.getElementById('end_date').value}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);

    // Select all functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Select all assets
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn btn-secondary';
        selectAllBtn.textContent = 'Select All Assets';
        selectAllBtn.style.marginBottom = '10px';
        
        const assetSelection = document.querySelector('.asset-selection');
        if (assetSelection) {
            assetSelection.insertBefore(selectAllBtn, assetSelection.querySelector('.asset-checkboxes'));
            
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_assets[]"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                });
                
                selectAllBtn.textContent = allChecked ? 'Select All Assets' : 'Deselect All Assets';
            });
        }
    });
</script>

<?php include get_stylesheet_directory() . '/dashboard/footer-dashboard.php'; ?>