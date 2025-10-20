<?php
// =============================================================================
// TEMPLATE NAME: Generate Tax Forms (FIXED VERSION)
// -----------------------------------------------------------------------------
// Fixed version with proper table aliases to avoid ambiguous column errors
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
$form_data = null;
$summary = null;
$selected_exchanges = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_form8949') {
        $tax_year = $_POST['tax_year'] ?? date('Y');
        $form_type = $_POST['form_type'] ?? 'short';
        $global_cost_basis_method = $_POST['global_cost_basis_method'] ?? 'FIFO';
        $selected_exchanges = $_POST['selected_exchanges'] ?? [];
        $include_manual_transactions = isset($_POST['include_manual_transactions']);
        
        // Process transactions for Form 8949
        $form_data = processTransactionsForForm8949WithExchanges($pdo, $tax_year, $form_type, $global_cost_basis_method, $selected_exchanges, $include_manual_transactions);
        $summary = calculateForm8949SummaryWithExchanges($form_data);
        $message = "Form 8949 generated successfully for tax year {$tax_year}";
    }
}

// Get user's exchanges
$user_exchanges = [];
try {
    $sql = "SELECT * FROM vd_user_exchanges WHERE userid = 1 ORDER BY exchangename ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $user_exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error fetching exchanges: " . $e->getMessage();
}

// Process transactions for Form 8949 with exchanges
function processTransactionsForForm8949WithExchanges($pdo, $tax_year, $form_type, $global_cost_basis_method, $selected_exchanges, $include_manual_transactions) {
    $start_date = $tax_year . '-01-01 00:00:00';
    $end_date = $tax_year . '-12-31 23:59:59';
    
    // Build query conditions
    $where_conditions = ['t.userid = 1', 't.datetime >= ?', 't.datetime <= ?'];
    $params = [$start_date, $end_date];
    
    // Filter by selected exchanges
    if (!empty($selected_exchanges)) {
        $placeholders = str_repeat('?,', count($selected_exchanges) - 1) . '?';
        $where_conditions[] = "t.exchange_id IN ($placeholders)";
        $params = array_merge($params, $selected_exchanges);
    }
    
    // Include manual transactions if requested
    if (!$include_manual_transactions) {
        $where_conditions[] = "t.exchange_id IS NOT NULL";
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Get all transactions for the tax year
    $sql = "SELECT t.*, e.exchangename, e.cost_basis_method as exchange_cost_method 
            FROM vd_user_transactions t 
            LEFT JOIN vd_user_exchanges e ON t.exchange_id = e.id 
            WHERE $where_sql 
            ORDER BY t.datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate purchases and sales
    $purchases = [];
    $sales = [];
    
    foreach ($all_transactions as $transaction) {
        $exchange_name = $transaction['exchangename'] ?: 'Manual Entry';
        $exchange_cost_method = $transaction['exchange_cost_method'] ?: $global_cost_basis_method;
        
        // Check for purchases (bought side)
        if ($transaction['w_status'] === 'Buy' && $transaction['w_total'] > 0) {
            $purchases[] = [
                'id' => $transaction['id'],
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['w_symbol']),
                'amount' => $transaction['w_total'],
                'price_per_unit' => $transaction['b_total'] / $transaction['w_total'],
                'exchange_name' => $exchange_name,
                'exchange_cost_method' => $exchange_cost_method,
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
                'exchange_name' => $exchange_name,
                'exchange_cost_method' => $exchange_cost_method,
                'notes' => $transaction['b_note']
            ];
        }
    }
    
    // Process sales and calculate gains/losses
    $form_8949_data = [];
    $asset_balances = [];
    
    foreach ($sales as $sale) {
        $asset = $sale['asset'];
        $amount_sold = $sale['amount'];
        $proceeds = $amount_sold * $sale['price_per_unit'];
        
        // Calculate cost basis using exchange-specific method
        $cost_basis = calculateExchangeSpecificCostBasis($asset, $amount_sold, $purchases, $sale['exchange_cost_method'], $sale['exchange_name']);
        
        $gain_loss = $proceeds - $cost_basis;
        
        $form_8949_data[] = [
            'date_acquired' => 'Various',
            'date_sold' => $sale['date'],
            'description' => $asset,
            'amount_sold' => $amount_sold,
            'proceeds' => $proceeds,
            'cost_basis' => $cost_basis,
            'gain_loss' => $gain_loss,
            'exchange_name' => $sale['exchange_name'],
            'cost_method' => $sale['exchange_cost_method'],
            'notes' => $sale['notes']
        ];
        
        // Update asset balances
        if (!isset($asset_balances[$asset])) {
            $asset_balances[$asset] = 0;
        }
        $asset_balances[$asset] -= $amount_sold;
    }
    
    return $form_8949_data;
}

// Calculate exchange-specific cost basis
function calculateExchangeSpecificCostBasis($asset, $amount_sold, $purchases, $method, $exchange_name) {
    $asset_purchases = array_filter($purchases, function($purchase) use ($asset, $exchange_name) {
        return $purchase['asset'] === $asset && $purchase['exchange_name'] === $exchange_name;
    });
    
    if (empty($asset_purchases)) {
        // Fallback to global method if no exchange-specific purchases
        $asset_purchases = array_filter($purchases, function($purchase) use ($asset) {
            return $purchase['asset'] === $asset;
        });
    }
    
    if (empty($asset_purchases)) {
        return $amount_sold * 0.8; // Fallback estimate
    }
    
    switch ($method) {
        case 'FIFO':
            return calculateFIFOCostBasis($asset_purchases, $amount_sold);
        case 'LIFO':
            return calculateLIFOCostBasis($asset_purchases, $amount_sold);
        case 'HIFO':
            return calculateHIFOCostBasis($asset_purchases, $amount_sold);
        case 'Average Cost Basis':
            return calculateAverageCostBasis($asset_purchases, $amount_sold);
        default:
            return calculateFIFOCostBasis($asset_purchases, $amount_sold);
    }
}

// FIFO Cost Basis Calculation
function calculateFIFOCostBasis($purchases, $amount_sold) {
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

// LIFO Cost Basis Calculation
function calculateLIFOCostBasis($purchases, $amount_sold) {
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

// HIFO Cost Basis Calculation
function calculateHIFOCostBasis($purchases, $amount_sold) {
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

// Average Cost Basis Calculation
function calculateAverageCostBasis($purchases, $amount_sold) {
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

// Calculate Form 8949 summary with exchanges
function calculateForm8949SummaryWithExchanges($form_data) {
    $summary = [
        'total_transactions' => count($form_data),
        'total_proceeds' => 0,
        'total_cost_basis' => 0,
        'total_gain_loss' => 0,
        'short_term_gain_loss' => 0,
        'long_term_gain_loss' => 0,
        'by_exchange' => [],
        'by_asset' => []
    ];
    
    foreach ($form_data as $transaction) {
        $summary['total_proceeds'] += $transaction['proceeds'];
        $summary['total_cost_basis'] += $transaction['cost_basis'];
        $summary['total_gain_loss'] += $transaction['gain_loss'];
        
        // Categorize by exchange
        $exchange = $transaction['exchange_name'];
        if (!isset($summary['by_exchange'][$exchange])) {
            $summary['by_exchange'][$exchange] = [
                'count' => 0,
                'proceeds' => 0,
                'cost_basis' => 0,
                'gain_loss' => 0
            ];
        }
        $summary['by_exchange'][$exchange]['count']++;
        $summary['by_exchange'][$exchange]['proceeds'] += $transaction['proceeds'];
        $summary['by_exchange'][$exchange]['cost_basis'] += $transaction['cost_basis'];
        $summary['by_exchange'][$exchange]['gain_loss'] += $transaction['gain_loss'];
        
        // Categorize by asset
        $asset = $transaction['description'];
        if (!isset($summary['by_asset'][$asset])) {
            $summary['by_asset'][$asset] = [
                'count' => 0,
                'proceeds' => 0,
                'cost_basis' => 0,
                'gain_loss' => 0
            ];
        }
        $summary['by_asset'][$asset]['count']++;
        $summary['by_asset'][$asset]['proceeds'] += $transaction['proceeds'];
        $summary['by_asset'][$asset]['cost_basis'] += $transaction['cost_basis'];
        $summary['by_asset'][$asset]['gain_loss'] += $transaction['gain_loss'];
    }
    
    return $summary;
}

// Get available tax years
$available_years = [];
$current_year = date('Y');
for ($i = $current_year; $i >= 2020; $i--) {
    $available_years[] = $i;
}

// Cost basis methods
$cost_basis_methods = [
    'FIFO' => 'First In, First Out',
    'LIFO' => 'Last In, First Out',
    'HIFO' => 'Highest In, First Out',
    'Average Cost Basis' => 'Average Cost Basis'
];

// Form types
$form_types = [
    'short' => 'Short Term (Part I)',
    'long' => 'Long Term (Part II)',
    'both' => 'Both Parts'
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

    /* Exchange Selection */
    .exchange-selection {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid rgba(0, 67, 255, 0.1);
    }

    .exchange-selection h4 {
        color: var(--dark-gray);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .exchange-checkboxes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
    }

    .exchange-checkbox-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: var(--white);
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        transition: all 0.2s ease;
    }

    .exchange-checkbox-item:hover {
        border-color: var(--primary-blue);
        background: rgba(0, 67, 255, 0.02);
    }

    .exchange-checkbox-item input[type="checkbox"] {
        margin: 0;
    }

    .exchange-checkbox-item label {
        margin: 0;
        font-weight: 500;
        color: var(--dark-gray);
        cursor: pointer;
        flex: 1;
    }

    .exchange-method {
        font-size: 0.8rem;
        color: var(--medium-gray);
        font-style: italic;
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

    /* Form 8949 Table */
    .form8949-table-container {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .form8949-table-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .form8949-table-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .form8949-table-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .form8949-table {
        width: 100%;
        border-collapse: collapse;
    }

    .form8949-table th,
    .form8949-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }

    .form8949-table th {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        font-weight: 600;
        color: var(--dark-gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
    }

    .form8949-table tbody tr:hover {
        background: rgba(0, 67, 255, 0.02);
    }

    .form8949-table .number {
        text-align: right;
        font-family: 'Courier New', monospace;
    }

    .exchange-badge {
        background: var(--primary-blue);
        color: var(--white);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 5px;
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

        .exchange-checkboxes {
            grid-template-columns: 1fr;
        }

        .form8949-table {
            font-size: 0.8rem;
        }

        .form8949-table th,
        .form8949-table td {
            padding: 8px;
        }

        .form8949-table-actions {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1>📊 Generate Tax Forms</h1>
        <p>Generate Form 8949 for cryptocurrency capital gains and losses</p>
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

    <!-- Generation Form -->
    <div class="form-container">
        <div class="form-header">
            <h3>⚙️ Generate Form 8949</h3>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_form8949">
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="tax_year">Tax Year</label>
                    <select name="tax_year" id="tax_year" class="form-control" required>
                        <?php foreach ($available_years as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo $year == date('Y') ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="form_type">Form Type</label>
                    <select name="form_type" id="form_type" class="form-control">
                        <?php foreach ($form_types as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $value == 'short' ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="global_cost_basis_method">Global Cost Basis Method</label>
                    <select name="global_cost_basis_method" id="global_cost_basis_method" class="form-control">
                        <?php foreach ($cost_basis_methods as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $value == 'FIFO' ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">Default method for exchanges without specific settings</div>
                </div>
            </div>

            <!-- Exchange Selection -->
            <?php if (!empty($user_exchanges)): ?>
                <div class="exchange-selection">
                    <h4>🏦 Select Exchanges to Include</h4>
                    <div class="exchange-checkboxes">
                        <?php foreach ($user_exchanges as $exchange): ?>
                            <div class="exchange-checkbox-item">
                                <input type="checkbox" 
                                       name="selected_exchanges[]" 
                                       value="<?php echo $exchange['id']; ?>" 
                                       id="exchange_<?php echo $exchange['id']; ?>"
                                       <?php echo in_array($exchange['id'], $selected_exchanges) ? 'checked' : ''; ?>>
                                <label for="exchange_<?php echo $exchange['id']; ?>">
                                    <?php echo htmlspecialchars($exchange['exchangename']); ?>
                                    <div class="exchange-method">
                                        Method: <?php echo htmlspecialchars($exchange['cost_basis_method'] ?: 'Default'); ?>
                                    </div>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    ℹ️ No exchanges found. <a href="add-exchanges.php">Add exchanges</a> to sync transactions automatically.
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="include_manual_transactions" <?php echo isset($_POST['include_manual_transactions']) ? 'checked' : ''; ?>>
                    Include manual transactions (not from exchanges)
                </label>
                <div class="form-help">Include transactions that were manually entered without exchange sync</div>
            </div>

            <button type="submit" class="btn btn-primary">
                📊 Generate Form 8949
            </button>
        </form>
    </div>

    <?php if ($form_data && $summary): ?>
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
                <h4>Total Gain/Loss</h4>
                <div class="summary-value <?php echo $summary['total_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($summary['total_gain_loss'], 2); ?>
                </div>
                <div class="summary-subtitle">Net gain or loss</div>
            </div>
        </div>

        <!-- Exchange Breakdown -->
        <?php if (!empty($summary['by_exchange'])): ?>
            <div class="breakdown-section">
                <div class="breakdown-header">
                    <h3>🏦 Breakdown by Exchange</h3>
                </div>
                <div class="breakdown-content">
                    <?php foreach ($summary['by_exchange'] as $exchange => $data): ?>
                        <div class="breakdown-item">
                            <div class="breakdown-item-header">
                                <div class="breakdown-item-name"><?php echo htmlspecialchars($exchange); ?></div>
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
                                    <div class="breakdown-total-label">Gain/Loss</div>
                                    <div class="breakdown-total-value <?php echo $data['gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($data['gain_loss'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form 8949 Table -->
        <div class="form8949-table-container">
            <div class="form8949-table-header">
                <h3>📋 Form 8949 Data</h3>
                <div class="form8949-table-actions">
                    <button class="btn btn-success" onclick="exportToPDF()">📄 Export PDF</button>
                    <button class="btn btn-secondary" onclick="printReport()">🖨️ Print</button>
                    <button class="btn btn-secondary" onclick="exportToCSV()">📊 Export CSV</button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="form8949-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Date Acquired</th>
                            <th style="width: 12%;">Date Sold</th>
                            <th style="width: 15%;">Description</th>
                            <th style="width: 10%;">Amount Sold</th>
                            <th style="width: 12%;">Proceeds</th>
                            <th style="width: 12%;">Cost Basis</th>
                            <th style="width: 12%;">Gain/Loss</th>
                            <th style="width: 15%;">Exchange</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($form_data as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['date_acquired']); ?></td>
                            <td><?php echo date('m/d/Y', strtotime($transaction['date_sold'])); ?></td>
                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                            <td class="number"><?php echo number_format($transaction['amount_sold'], 8); ?></td>
                            <td class="number">$<?php echo number_format($transaction['proceeds'], 2); ?></td>
                            <td class="number">$<?php echo number_format($transaction['cost_basis'], 2); ?></td>
                            <td class="number <?php echo $transaction['gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format($transaction['gain_loss'], 2); ?>
                            </td>
                            <td>
                                <span class="exchange-badge"><?php echo htmlspecialchars($transaction['exchange_name']); ?></span>
                                <span class="method-badge"><?php echo htmlspecialchars($transaction['cost_method']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
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
        const formData = <?php echo json_encode($form_data ?? []); ?>;
        const summary = <?php echo json_encode($summary ?? []); ?>;
        
        if (formData.length === 0) {
            alert('No data to export');
            return;
        }
        
        let csv = 'Date Acquired,Date Sold,Description,Amount Sold,Proceeds,Cost Basis,Gain/Loss,Exchange,Cost Method\n';
        
        formData.forEach(transaction => {
            csv += `"${transaction.date_acquired}","${transaction.date_sold}","${transaction.description}","${transaction.amount_sold}","${transaction.proceeds}","${transaction.cost_basis}","${transaction.gain_loss}","${transaction.exchange_name}","${transaction.cost_method}"\n`;
        });
        
        // Add summary data
        csv += '\n\nSummary\n';
        csv += `Total Transactions,${summary.total_transactions}\n`;
        csv += `Total Proceeds,${summary.total_proceeds}\n`;
        csv += `Total Cost Basis,${summary.total_cost_basis}\n`;
        csv += `Total Gain/Loss,${summary.total_gain_loss}\n`;
        
        // Add exchange breakdown
        csv += '\n\nExchange Breakdown\n';
        csv += 'Exchange,Count,Proceeds,Cost Basis,Gain/Loss\n';
        Object.entries(summary.by_exchange || {}).forEach(([exchange, data]) => {
            csv += `"${exchange}","${data.count}","${data.proceeds}","${data.cost_basis}","${data.gain_loss}"\n`;
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `form8949_${document.getElementById('tax_year').value}.csv`;
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
        // Select all exchanges
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn btn-secondary';
        selectAllBtn.textContent = 'Select All Exchanges';
        selectAllBtn.style.marginBottom = '10px';
        
        const exchangeSelection = document.querySelector('.exchange-selection');
        if (exchangeSelection) {
            exchangeSelection.insertBefore(selectAllBtn, exchangeSelection.querySelector('.exchange-checkboxes'));
            
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_exchanges[]"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                });
                
                selectAllBtn.textContent = allChecked ? 'Select All Exchanges' : 'Deselect All Exchanges';
            });
        }
    });
</script>

<?php include get_stylesheet_directory() . '/dashboard/footer-dashboard.php'; ?>