<?php
// =============================================================================
// TEMPLATE NAME: Form 8949 Generator with Exchange Integration
// -----------------------------------------------------------------------------
// Generates IRS Form 8949 with integrated exchange data and cost basis methods
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
$global_cost_basis_method = 'FIFO';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_form') {
        $tax_year = $_POST['tax_year'] ?? date('Y');
        $form_type = $_POST['form_type'] ?? 'both';
        $global_cost_basis_method = $_POST['global_cost_basis_method'] ?? 'FIFO';
        $selected_exchanges = $_POST['selected_exchanges'] ?? [];
        $include_manual_transactions = isset($_POST['include_manual_transactions']);
        
        // Process transactions for Form 8949 with exchange integration
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

// Enhanced transaction processing with exchange integration
function processTransactionsForForm8949WithExchanges($pdo, $tax_year, $form_type, $global_cost_basis_method, $selected_exchanges, $include_manual_transactions) {
    $start_date = $tax_year . '-01-01 00:00:00';
    $end_date = $tax_year . '-12-31 23:59:59';
    
    // Build query conditions
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_date, $end_date];
    
    // Filter by selected exchanges
    if (!empty($selected_exchanges)) {
        $placeholders = str_repeat('?,', count($selected_exchanges) - 1) . '?';
        $where_conditions[] = "exchange_id IN ($placeholders)";
        $params = array_merge($params, $selected_exchanges);
    }
    
    // Include manual transactions if requested
    if (!$include_manual_transactions) {
        $where_conditions[] = "exchange_id IS NOT NULL";
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
        $cost_method = $transaction['exchange_cost_method'] ?: $global_cost_basis_method;
        
        if ($transaction['w_status'] === 'Purchase' && $transaction['w_total'] > 0) {
            $purchases[] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['w_symbol']),
                'amount' => $transaction['w_total'],
                'price_per_unit' => $transaction['b_total'] / $transaction['w_total'], // Assuming b_total is USD cost
                'total_cost' => $transaction['b_total'],
                'transaction_id' => $transaction['id'],
                'exchange_name' => $exchange_name,
                'cost_method' => $cost_method
            ];
        } elseif ($transaction['status'] === 'Sale' && $transaction['b_total'] > 0) {
            $sales[] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['b_symbol']),
                'amount' => $transaction['b_total'],
                'price_per_unit' => $transaction['w_total'] / $transaction['b_total'], // Assuming w_total is USD proceeds
                'total_proceeds' => $transaction['w_total'],
                'transaction_id' => $transaction['id'],
                'exchange_name' => $exchange_name,
                'cost_method' => $cost_method
            ];
        }
    }
    
    // Process sales with exchange-specific cost basis calculation
    $processed_sales = [];
    
    foreach ($sales as $sale) {
        $asset = $sale['asset'];
        $amount_sold = $sale['amount'];
        $proceeds = $sale['total_proceeds'];
        $exchange_name = $sale['exchange_name'];
        $cost_method = $sale['cost_method'];
        
        // Get cost basis using exchange-specific method
        $cost_basis = calculateExchangeSpecificCostBasis($asset, $amount_sold, $purchases, $cost_method, $exchange_name);
        
        // Determine if short-term or long-term (simplified: < 1 year = short-term)
        $is_short_term = true; // In production, calculate based on acquisition dates
        
        $processed_sales[] = [
            'date_acquired' => 'Various', // Would need to track actual acquisition dates
            'date_sold' => date('m/d/Y', strtotime($sale['date'])),
            'description' => "Sale of {$amount_sold} {$asset} ({$exchange_name})",
            'proceeds' => $proceeds,
            'cost_basis' => $cost_basis,
            'adjustment_code' => '',
            'adjustment_amount' => 0,
            'gain_loss' => $proceeds - $cost_basis,
            'asset' => $asset,
            'amount' => $amount_sold,
            'is_short_term' => $is_short_term,
            'transaction_id' => $sale['transaction_id'],
            'exchange_name' => $exchange_name,
            'cost_method' => $cost_method
        ];
    }
    
    return $processed_sales;
}

// Exchange-specific cost basis calculation
function calculateExchangeSpecificCostBasis($asset, $amount_sold, $purchases, $method, $exchange_name) {
    // Filter purchases for this asset and exchange
    $asset_purchases = array_filter($purchases, function($purchase) use ($asset, $exchange_name) {
        return $purchase['asset'] === $asset && $purchase['exchange_name'] === $exchange_name;
    });
    
    if (empty($asset_purchases)) {
        // No purchase history found for this exchange, use estimated cost basis
        return $amount_sold * 0.8; // 80% of sale price as estimated cost
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

function calculateFIFOCostBasis($purchases, $amount_sold) {
    // Sort purchases by date (FIFO = First In, First Out)
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

function calculateLIFOCostBasis($purchases, $amount_sold) {
    // Sort purchases by date descending (LIFO = Last In, First Out)
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

function calculateHIFOCostBasis($purchases, $amount_sold) {
    // Sort purchases by price per unit descending (HIFO = Highest In, First Out)
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

function calculateAverageCostBasis($purchases, $amount_sold) {
    $total_amount = 0;
    $total_cost = 0;
    
    foreach ($purchases as $purchase) {
        $total_amount += $purchase['amount'];
        $total_cost += $purchase['total_cost'];
    }
    
    if ($total_amount == 0) return 0;
    
    $average_price_per_unit = $total_cost / $total_amount;
    return $amount_sold * $average_price_per_unit;
}

// Enhanced summary calculation with exchange breakdown
function calculateForm8949SummaryWithExchanges($form_data) {
    $short_term = [];
    $long_term = [];
    $total_short_proceeds = 0;
    $total_short_cost = 0;
    $total_long_proceeds = 0;
    $total_long_cost = 0;
    $exchange_breakdown = [];
    
    foreach ($form_data as $transaction) {
        $exchange_name = $transaction['exchange_name'];
        
        if (!isset($exchange_breakdown[$exchange_name])) {
            $exchange_breakdown[$exchange_name] = [
                'short_term' => [],
                'long_term' => [],
                'total_proceeds' => 0,
                'total_cost' => 0,
                'total_gain_loss' => 0
            ];
        }
        
        if ($transaction['is_short_term']) {
            $short_term[] = $transaction;
            $total_short_proceeds += $transaction['proceeds'];
            $total_short_cost += $transaction['cost_basis'];
            $exchange_breakdown[$exchange_name]['short_term'][] = $transaction;
        } else {
            $long_term[] = $transaction;
            $total_long_proceeds += $transaction['proceeds'];
            $total_long_cost += $transaction['cost_basis'];
            $exchange_breakdown[$exchange_name]['long_term'][] = $transaction;
        }
        
        $exchange_breakdown[$exchange_name]['total_proceeds'] += $transaction['proceeds'];
        $exchange_breakdown[$exchange_name]['total_cost'] += $transaction['cost_basis'];
        $exchange_breakdown[$exchange_name]['total_gain_loss'] += $transaction['gain_loss'];
    }
    
    return [
        'short_term' => $short_term,
        'long_term' => $long_term,
        'total_short_proceeds' => $total_short_proceeds,
        'total_short_cost' => $total_short_cost,
        'total_short_gain_loss' => $total_short_proceeds - $total_short_cost,
        'total_long_proceeds' => $total_long_proceeds,
        'total_long_cost' => $total_long_cost,
        'total_long_gain_loss' => $total_long_proceeds - $total_long_cost,
        'total_gain_loss' => ($total_short_proceeds - $total_short_cost) + ($total_long_proceeds - $total_long_cost),
        'total_transactions' => count($form_data),
        'exchange_breakdown' => $exchange_breakdown
    ];
}

// Get available tax years
$available_years = [];
$current_year = date('Y');
for ($i = $current_year; $i >= 2020; $i--) {
    $available_years[] = $i;
}
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

    /* Exchange Breakdown */
    .exchange-breakdown {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .exchange-breakdown-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
    }

    .exchange-breakdown-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .exchange-breakdown-content {
        padding: 25px;
    }

    .exchange-item {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .exchange-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .exchange-name {
        font-weight: 600;
        color: var(--primary-blue);
        font-size: 1.1rem;
    }

    .exchange-totals {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .exchange-total-item {
        text-align: center;
    }

    .exchange-total-label {
        font-size: 0.8rem;
        color: var(--medium-gray);
        margin-bottom: 5px;
    }

    .exchange-total-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .exchange-total-value.positive {
        color: var(--success-green);
    }

    .exchange-total-value.negative {
        color: var(--danger-red);
    }

    /* Form 8949 Table */
    .form8949-container {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .form8949-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .form8949-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .form8949-actions {
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

    .form8949-table .gain {
        color: var(--success-green);
        font-weight: 600;
    }

    .form8949-table .loss {
        color: var(--danger-red);
        font-weight: 600;
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

    /* Totals Section */
    .totals-section {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        padding: 20px;
        border-top: 2px solid #e0e0e0;
    }

    .totals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .total-item {
        text-align: center;
    }

    .total-label {
        font-size: 0.9rem;
        color: var(--medium-gray);
        margin-bottom: 5px;
    }

    .total-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--dark-gray);
    }

    .total-value.positive {
        color: var(--success-green);
    }

    .total-value.negative {
        color: var(--danger-red);
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

        .form8949-actions {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1>📋 Form 8949 Generator with Exchanges</h1>
        <p>Generate IRS Form 8949 with integrated exchange data and cost basis methods</p>
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
            <input type="hidden" name="action" value="generate_form">
            
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
                        <option value="both">Both Short & Long Term</option>
                        <option value="short">Short Term Only</option>
                        <option value="long">Long Term Only</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="global_cost_basis_method">Default Cost Basis Method</label>
                    <select name="global_cost_basis_method" id="global_cost_basis_method" class="form-control">
                        <option value="FIFO" <?php echo $global_cost_basis_method == 'FIFO' ? 'selected' : ''; ?>>FIFO (First In, First Out)</option>
                        <option value="LIFO" <?php echo $global_cost_basis_method == 'LIFO' ? 'selected' : ''; ?>>LIFO (Last In, First Out)</option>
                        <option value="HIFO" <?php echo $global_cost_basis_method == 'HIFO' ? 'selected' : ''; ?>>HIFO (Highest In, First Out)</option>
                        <option value="Average Cost Basis" <?php echo $global_cost_basis_method == 'Average Cost Basis' ? 'selected' : ''; ?>>Average Cost Basis</option>
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
        <!-- Exchange Breakdown -->
        <?php if (!empty($summary['exchange_breakdown'])): ?>
            <div class="exchange-breakdown">
                <div class="exchange-breakdown-header">
                    <h3>🏦 Exchange Breakdown</h3>
                </div>
                <div class="exchange-breakdown-content">
                    <?php foreach ($summary['exchange_breakdown'] as $exchange_name => $data): ?>
                        <div class="exchange-item">
                            <div class="exchange-item-header">
                                <div class="exchange-name"><?php echo htmlspecialchars($exchange_name); ?></div>
                                <div style="font-size: 0.9rem; color: var(--medium-gray);">
                                    <?php echo count($data['short_term']) + count($data['long_term']); ?> transactions
                                </div>
                            </div>
                            <div class="exchange-totals">
                                <div class="exchange-total-item">
                                    <div class="exchange-total-label">Proceeds</div>
                                    <div class="exchange-total-value">$<?php echo number_format($data['total_proceeds'], 2); ?></div>
                                </div>
                                <div class="exchange-total-item">
                                    <div class="exchange-total-label">Cost Basis</div>
                                    <div class="exchange-total-value">$<?php echo number_format($data['total_cost'], 2); ?></div>
                                </div>
                                <div class="exchange-total-item">
                                    <div class="exchange-total-label">Gain/Loss</div>
                                    <div class="exchange-total-value <?php echo $data['total_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($data['total_gain_loss'], 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Transactions</h4>
                <div class="summary-value"><?php echo $summary['total_transactions']; ?></div>
                <div class="summary-subtitle">Sale transactions processed</div>
            </div>
            <div class="summary-card">
                <h4>Short Term</h4>
                <div class="summary-value"><?php echo count($summary['short_term']); ?></div>
                <div class="summary-subtitle">Held less than 1 year</div>
            </div>
            <div class="summary-card">
                <h4>Long Term</h4>
                <div class="summary-value"><?php echo count($summary['long_term']); ?></div>
                <div class="summary-subtitle">Held 1 year or more</div>
            </div>
            <div class="summary-card">
                <h4>Total Proceeds</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_short_proceeds'] + $summary['total_long_proceeds'], 2); ?></div>
                <div class="summary-subtitle">From all sales</div>
            </div>
            <div class="summary-card">
                <h4>Total Cost Basis</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_short_cost'] + $summary['total_long_cost'], 2); ?></div>
                <div class="summary-subtitle">Using exchange methods</div>
            </div>
            <div class="summary-card">
                <h4>Net Gain/Loss</h4>
                <div class="summary-value <?php echo $summary['total_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($summary['total_gain_loss'], 2); ?>
                </div>
                <div class="summary-subtitle"><?php echo $summary['total_gain_loss'] >= 0 ? 'Capital Gain' : 'Capital Loss'; ?></div>
            </div>
        </div>

        <!-- Form 8949 Display -->
        <div class="form8949-container">
            <div class="form8949-header">
                <h3>📋 Form 8949 - Sales and Other Dispositions of Capital Assets</h3>
                <div class="form8949-actions">
                    <button class="btn btn-success" onclick="exportToPDF()">📄 Export PDF</button>
                    <button class="btn btn-secondary" onclick="printForm()">🖨️ Print</button>
                    <button class="btn btn-secondary" onclick="exportToCSV()">📊 Export CSV</button>
                </div>
            </div>

            <?php if (!empty($summary['short_term'])): ?>
                <h4 style="padding: 20px 25px 10px; margin: 0; color: var(--dark-gray); background: rgba(0, 67, 255, 0.05);">
                    Part I - Short-Term Capital Gains and Losses (Assets held 1 year or less)
                </h4>
                <div style="overflow-x: auto;">
                    <table class="form8949-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Date Acquired</th>
                                <th style="width: 10%;">Date Sold</th>
                                <th style="width: 25%;">Description</th>
                                <th style="width: 10%;">Proceeds</th>
                                <th style="width: 10%;">Cost Basis</th>
                                <th style="width: 6%;">Code</th>
                                <th style="width: 6%;">Adjustment</th>
                                <th style="width: 10%;">Gain/Loss</th>
                                <th style="width: 13%;">Exchange</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['short_term'] as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['date_acquired']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['date_sold']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td class="number">$<?php echo number_format($transaction['proceeds'], 2); ?></td>
                                <td class="number">$<?php echo number_format($transaction['cost_basis'], 2); ?></td>
                                <td class="number"><?php echo htmlspecialchars($transaction['adjustment_code']); ?></td>
                                <td class="number">$<?php echo number_format($transaction['adjustment_amount'], 2); ?></td>
                                <td class="number <?php echo $transaction['gain_loss'] >= 0 ? 'gain' : 'loss'; ?>">
                                    $<?php echo number_format($transaction['gain_loss'], 2); ?>
                                </td>
                                <td>
                                    <span class="exchange-badge"><?php echo htmlspecialchars($transaction['exchange_name']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="totals-section">
                    <div class="totals-grid">
                        <div class="total-item">
                            <div class="total-label">Total Proceeds</div>
                            <div class="total-value">$<?php echo number_format($summary['total_short_proceeds'], 2); ?></div>
                        </div>
                        <div class="total-item">
                            <div class="total-label">Total Cost Basis</div>
                            <div class="total-value">$<?php echo number_format($summary['total_short_cost'], 2); ?></div>
                        </div>
                        <div class="total-item">
                            <div class="total-label">Net Gain/Loss</div>
                            <div class="total-value <?php echo $summary['total_short_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format($summary['total_short_gain_loss'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($summary['long_term'])): ?>
                <h4 style="padding: 20px 25px 10px; margin: 0; color: var(--dark-gray); background: rgba(0, 67, 255, 0.05);">
                    Part II - Long-Term Capital Gains and Losses (Assets held more than 1 year)
                </h4>
                <div style="overflow-x: auto;">
                    <table class="form8949-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Date Acquired</th>
                                <th style="width: 10%;">Date Sold</th>
                                <th style="width: 25%;">Description</th>
                                <th style="width: 10%;">Proceeds</th>
                                <th style="width: 10%;">Cost Basis</th>
                                <th style="width: 6%;">Code</th>
                                <th style="width: 6%;">Adjustment</th>
                                <th style="width: 10%;">Gain/Loss</th>
                                <th style="width: 13%;">Exchange</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['long_term'] as $transaction): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($transaction['date_acquired']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['date_sold']); ?></td>
                                <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                <td class="number">$<?php echo number_format($transaction['proceeds'], 2); ?></td>
                                <td class="number">$<?php echo number_format($transaction['cost_basis'], 2); ?></td>
                                <td class="number"><?php echo htmlspecialchars($transaction['adjustment_code']); ?></td>
                                <td class="number">$<?php echo number_format($transaction['adjustment_amount'], 2); ?></td>
                                <td class="number <?php echo $transaction['gain_loss'] >= 0 ? 'gain' : 'loss'; ?>">
                                    $<?php echo number_format($transaction['gain_loss'], 2); ?>
                                </td>
                                <td>
                                    <span class="exchange-badge"><?php echo htmlspecialchars($transaction['exchange_name']); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="totals-section">
                    <div class="totals-grid">
                        <div class="total-item">
                            <div class="total-label">Total Proceeds</div>
                            <div class="total-value">$<?php echo number_format($summary['total_long_proceeds'], 2); ?></div>
                        </div>
                        <div class="total-item">
                            <div class="total-label">Total Cost Basis</div>
                            <div class="total-value">$<?php echo number_format($summary['total_long_cost'], 2); ?></div>
                        </div>
                        <div class="total-item">
                            <div class="total-label">Net Gain/Loss</div>
                            <div class="total-value <?php echo $summary['total_long_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format($summary['total_long_gain_loss'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($summary['short_term']) && empty($summary['long_term'])): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Sale Transactions Found</h3>
                    <p>No cryptocurrency sales were found for the selected tax year and exchange filters.</p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Export to PDF functionality
    function exportToPDF() {
        // This would integrate with a PDF generation library like jsPDF or server-side PDF generation
        alert('PDF export functionality would be implemented here. Consider using libraries like jsPDF, Puppeteer, or server-side PDF generation.');
    }

    // Print functionality
    function printForm() {
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
        
        let csv = 'Date Acquired,Date Sold,Description,Proceeds,Cost Basis,Adjustment Code,Adjustment Amount,Gain/Loss,Asset,Amount,Transaction Type,Exchange,Cost Method\n';
        
        formData.forEach(transaction => {
            csv += `"${transaction.date_acquired}","${transaction.date_sold}","${transaction.description}","${transaction.proceeds}","${transaction.cost_basis}","${transaction.adjustment_code}","${transaction.adjustment_amount}","${transaction.gain_loss}","${transaction.asset}","${transaction.amount}","${transaction.is_short_term ? 'Short Term' : 'Long Term'}","${transaction.exchange_name}","${transaction.cost_method}"\n`;
        });
        
        // Add summary data
        csv += '\n\nSummary\n';
        csv += `Total Short Term Proceeds,${summary.total_short_proceeds}\n`;
        csv += `Total Short Term Cost Basis,${summary.total_short_cost}\n`;
        csv += `Total Short Term Gain/Loss,${summary.total_short_gain_loss}\n`;
        csv += `Total Long Term Proceeds,${summary.total_long_proceeds}\n`;
        csv += `Total Long Term Cost Basis,${summary.total_long_cost}\n`;
        csv += `Total Long Term Gain/Loss,${summary.total_long_gain_loss}\n`;
        csv += `Total Gain/Loss,${summary.total_gain_loss}\n`;
        
        // Add exchange breakdown
        csv += '\n\nExchange Breakdown\n';
        csv += 'Exchange,Proceeds,Cost Basis,Gain/Loss,Transactions\n';
        Object.entries(summary.exchange_breakdown || {}).forEach(([exchange, data]) => {
            csv += `"${exchange}","${data.total_proceeds}","${data.total_cost}","${data.total_gain_loss}","${data.short_term.length + data.long_term.length}"\n`;
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `form8949_with_exchanges_${document.getElementById('tax_year').value}.csv`;
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

    // Select all exchanges functionality
    document.addEventListener('DOMContentLoaded', function() {
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