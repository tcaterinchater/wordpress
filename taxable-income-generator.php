<?php
// =============================================================================
// TEMPLATE NAME: Taxable Income Generator
// -----------------------------------------------------------------------------
// Generates comprehensive taxable income reports for cryptocurrency activities
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
$income_data = null;
$summary = null;
$selected_exchanges = [];
$selected_income_types = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_income_report') {
        $tax_year = $_POST['tax_year'] ?? date('Y');
        $selected_exchanges = $_POST['selected_exchanges'] ?? [];
        $selected_income_types = $_POST['selected_income_types'] ?? [];
        $include_manual_transactions = isset($_POST['include_manual_transactions']);
        $currency = $_POST['currency'] ?? 'USD';
        
        // Process transactions for taxable income
        $income_data = processTransactionsForTaxableIncome($pdo, $tax_year, $selected_exchanges, $selected_income_types, $include_manual_transactions, $currency);
        $summary = calculateTaxableIncomeSummary($income_data);
        $message = "Taxable income report generated successfully for tax year {$tax_year}";
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

// Income types and their tax implications
$income_types = [
    'Income: Mining' => [
        'name' => 'Mining Income',
        'description' => 'Cryptocurrency earned through mining activities',
        'taxable' => true,
        'form' => 'Schedule C or Schedule 1',
        'category' => 'Self-Employment Income',
        'color' => '#FF6B6B'
    ],
    'Income: Paycheck' => [
        'name' => 'Crypto Paycheck',
        'description' => 'Cryptocurrency received as salary or wages',
        'taxable' => true,
        'form' => 'W-2 or 1099',
        'category' => 'Wage Income',
        'color' => '#4ECDC4'
    ],
    'Income: Airdrop' => [
        'name' => 'Airdrop Income',
        'description' => 'Free cryptocurrency received through airdrops',
        'taxable' => true,
        'form' => 'Schedule 1',
        'category' => 'Other Income',
        'color' => '#45B7D1'
    ],
    'Income: Fork' => [
        'name' => 'Fork Income',
        'description' => 'Cryptocurrency received from blockchain forks',
        'taxable' => true,
        'form' => 'Schedule 1',
        'category' => 'Other Income',
        'color' => '#96CEB4'
    ],
    'Gift Received' => [
        'name' => 'Gift Income',
        'description' => 'Cryptocurrency received as a gift',
        'taxable' => false,
        'form' => 'Gift Tax Return (if applicable)',
        'category' => 'Gift Income',
        'color' => '#FFEAA7'
    ],
    'Staking Rewards' => [
        'name' => 'Staking Rewards',
        'description' => 'Rewards earned from staking cryptocurrency',
        'taxable' => true,
        'form' => 'Schedule 1',
        'category' => 'Other Income',
        'color' => '#DDA0DD'
    ],
    'DeFi Yield' => [
        'name' => 'DeFi Yield',
        'description' => 'Yield earned from DeFi protocols',
        'taxable' => true,
        'form' => 'Schedule 1',
        'category' => 'Other Income',
        'color' => '#98D8C8'
    ],
    'Lending Interest' => [
        'name' => 'Lending Interest',
        'description' => 'Interest earned from cryptocurrency lending',
        'taxable' => true,
        'form' => 'Schedule 1',
        'category' => 'Interest Income',
        'color' => '#F7DC6F'
    ]
];

// Process transactions for taxable income
function processTransactionsForTaxableIncome($pdo, $tax_year, $selected_exchanges, $selected_income_types, $include_manual_transactions, $currency) {
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
    
    // Filter by income types
    if (!empty($selected_income_types)) {
        $income_conditions = [];
        foreach ($selected_income_types as $type) {
            $income_conditions[] = "(t.w_status = ? OR t.status = ?)";
            $params[] = $type;
            $params[] = $type;
        }
        $where_conditions[] = "(" . implode(' OR ', $income_conditions) . ")";
    }
    
    $where_sql = implode(' AND ', $where_conditions);
    
    // Get all income transactions for the tax year
    $sql = "SELECT t.*, e.exchangename, e.cost_basis_method as exchange_cost_method 
            FROM vd_user_transactions t 
            LEFT JOIN vd_user_exchanges e ON t.exchange_id = e.id 
            WHERE $where_sql 
            ORDER BY t.datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $all_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process income transactions
    $income_transactions = [];
    
    foreach ($all_transactions as $transaction) {
        $exchange_name = $transaction['exchangename'] ?: 'Manual Entry';
        $income_type = null;
        $amount = 0;
        $usd_value = 0;
        $asset = '';
        
        // Check for income in w_status (bought side)
        if (in_array($transaction['w_status'], array_keys($income_types)) && $transaction['w_total'] > 0) {
            $income_type = $transaction['w_status'];
            $amount = $transaction['w_total'];
            $asset = strtoupper($transaction['w_symbol']);
            $usd_value = $transaction['b_total']; // Assuming b_total is USD value
        }
        // Check for income in status (sold side)
        elseif (in_array($transaction['status'], array_keys($income_types)) && $transaction['b_total'] > 0) {
            $income_type = $transaction['status'];
            $amount = $transaction['b_total'];
            $asset = strtoupper($transaction['b_symbol']);
            $usd_value = $transaction['w_total']; // Assuming w_total is USD value
        }
        
        if ($income_type && $amount > 0) {
            $income_transactions[] = [
                'date' => $transaction['datetime'],
                'income_type' => $income_type,
                'asset' => $asset,
                'amount' => $amount,
                'usd_value' => $usd_value,
                'exchange_name' => $exchange_name,
                'transaction_id' => $transaction['id'],
                'notes' => $transaction['w_note'] ?: $transaction['b_note'],
                'taxable' => $income_types[$income_type]['taxable'],
                'form' => $income_types[$income_type]['form'],
                'category' => $income_types[$income_type]['category']
            ];
        }
    }
    
    return $income_transactions;
}

// Calculate taxable income summary
function calculateTaxableIncomeSummary($income_data) {
    $summary = [
        'total_transactions' => count($income_data),
        'total_taxable_income' => 0,
        'total_non_taxable_income' => 0,
        'by_type' => [],
        'by_exchange' => [],
        'by_asset' => [],
        'by_month' => [],
        'taxable_transactions' => [],
        'non_taxable_transactions' => []
    ];
    
    foreach ($income_data as $transaction) {
        $type = $transaction['income_type'];
        $exchange = $transaction['exchange_name'];
        $asset = $transaction['asset'];
        $month = date('Y-m', strtotime($transaction['date']));
        $usd_value = $transaction['usd_value'];
        
        // Initialize arrays if not set
        if (!isset($summary['by_type'][$type])) {
            $summary['by_type'][$type] = [
                'count' => 0,
                'total_usd' => 0,
                'transactions' => []
            ];
        }
        if (!isset($summary['by_exchange'][$exchange])) {
            $summary['by_exchange'][$exchange] = [
                'count' => 0,
                'total_usd' => 0,
                'transactions' => []
            ];
        }
        if (!isset($summary['by_asset'][$asset])) {
            $summary['by_asset'][$asset] = [
                'count' => 0,
                'total_usd' => 0,
                'transactions' => []
            ];
        }
        if (!isset($summary['by_month'][$month])) {
            $summary['by_month'][$month] = [
                'count' => 0,
                'total_usd' => 0,
                'transactions' => []
            ];
        }
        
        // Update counts and totals
        $summary['by_type'][$type]['count']++;
        $summary['by_type'][$type]['total_usd'] += $usd_value;
        $summary['by_type'][$type]['transactions'][] = $transaction;
        
        $summary['by_exchange'][$exchange]['count']++;
        $summary['by_exchange'][$exchange]['total_usd'] += $usd_value;
        $summary['by_exchange'][$exchange]['transactions'][] = $transaction;
        
        $summary['by_asset'][$asset]['count']++;
        $summary['by_asset'][$asset]['total_usd'] += $usd_value;
        $summary['by_asset'][$asset]['transactions'][] = $transaction;
        
        $summary['by_month'][$month]['count']++;
        $summary['by_month'][$month]['total_usd'] += $usd_value;
        $summary['by_month'][$month]['transactions'][] = $transaction;
        
        // Categorize as taxable or non-taxable
        if ($transaction['taxable']) {
            $summary['total_taxable_income'] += $usd_value;
            $summary['taxable_transactions'][] = $transaction;
        } else {
            $summary['total_non_taxable_income'] += $usd_value;
            $summary['non_taxable_transactions'][] = $transaction;
        }
    }
    
    return $summary;
}

// Get available tax years
$available_years = [];
$current_year = date('Y');
for ($i = $current_year; $i >= 2020; $i--) {
    $available_years[] = $i;
}

// Supported currencies
$supported_currencies = [
    'USD' => 'US Dollar',
    'EUR' => 'Euro',
    'GBP' => 'British Pound',
    'CAD' => 'Canadian Dollar',
    'AUD' => 'Australian Dollar'
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

    /* Income Type Selection */
    .income-type-selection {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid rgba(0, 67, 255, 0.1);
    }

    .income-type-selection h4 {
        color: var(--dark-gray);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .income-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
    }

    .income-type-item {
        background: var(--white);
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        padding: 15px;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .income-type-item:hover {
        border-color: var(--primary-blue);
        box-shadow: 0 4px 12px rgba(0, 67, 255, 0.1);
    }

    .income-type-item.selected {
        border-color: var(--primary-blue);
        background: rgba(0, 67, 255, 0.05);
    }

    .income-type-item input[type="checkbox"] {
        display: none;
    }

    .income-type-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .income-type-icon {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-size: 12px;
        font-weight: bold;
    }

    .income-type-name {
        font-weight: 600;
        color: var(--dark-gray);
        font-size: 1rem;
    }

    .income-type-description {
        font-size: 0.85rem;
        color: var(--medium-gray);
        margin-bottom: 8px;
    }

    .income-type-details {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
    }

    .income-type-taxable {
        background: var(--success-green);
        color: var(--white);
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .income-type-non-taxable {
        background: var(--warning-orange);
        color: var(--white);
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .income-type-form {
        color: var(--medium-gray);
        font-style: italic;
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

    /* Income Table */
    .income-table-container {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    .income-table-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }

    .income-table-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .income-table-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .income-table {
        width: 100%;
        border-collapse: collapse;
    }

    .income-table th,
    .income-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
        font-size: 0.9rem;
    }

    .income-table th {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        font-weight: 600;
        color: var(--dark-gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.8rem;
    }

    .income-table tbody tr:hover {
        background: rgba(0, 67, 255, 0.02);
    }

    .income-table .number {
        text-align: right;
        font-family: 'Courier New', monospace;
    }

    .income-type-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--white);
    }

    .taxable-badge {
        background: var(--success-green);
    }

    .non-taxable-badge {
        background: var(--warning-orange);
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

        .income-type-grid {
            grid-template-columns: 1fr;
        }

        .exchange-checkboxes {
            grid-template-columns: 1fr;
        }

        .income-table {
            font-size: 0.8rem;
        }

        .income-table th,
        .income-table td {
            padding: 8px;
        }

        .income-table-actions {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1>💰 Taxable Income Generator</h1>
        <p>Generate comprehensive taxable income reports for cryptocurrency activities</p>
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
            <h3>⚙️ Generate Taxable Income Report</h3>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_income_report">
            
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
                    <label for="currency">Currency</label>
                    <select name="currency" id="currency" class="form-control">
                        <?php foreach ($supported_currencies as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo $code == 'USD' ? 'selected' : ''; ?>>
                                <?php echo $code; ?> - <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">Currency for reporting values</div>
                </div>
            </div>

            <!-- Income Type Selection -->
            <div class="income-type-selection">
                <h4>📊 Select Income Types to Include</h4>
                <div class="income-type-grid">
                    <?php foreach ($income_types as $type_key => $type_info): ?>
                        <div class="income-type-item" onclick="toggleIncomeType('<?php echo $type_key; ?>')">
                            <input type="checkbox" 
                                   name="selected_income_types[]" 
                                   value="<?php echo $type_key; ?>" 
                                   id="income_type_<?php echo $type_key; ?>"
                                   <?php echo in_array($type_key, $selected_income_types) ? 'checked' : ''; ?>>
                            <div class="income-type-header">
                                <div class="income-type-icon" style="background-color: <?php echo $type_info['color']; ?>">
                                    <?php echo substr($type_info['name'], 0, 1); ?>
                                </div>
                                <div class="income-type-name"><?php echo $type_info['name']; ?></div>
                            </div>
                            <div class="income-type-description"><?php echo $type_info['description']; ?></div>
                            <div class="income-type-details">
                                <span class="<?php echo $type_info['taxable'] ? 'income-type-taxable' : 'income-type-non-taxable'; ?>">
                                    <?php echo $type_info['taxable'] ? 'Taxable' : 'Non-Taxable'; ?>
                                </span>
                                <span class="income-type-form"><?php echo $type_info['form']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
                💰 Generate Income Report
            </button>
        </form>
    </div>

    <?php if ($income_data && $summary): ?>
        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Transactions</h4>
                <div class="summary-value"><?php echo $summary['total_transactions']; ?></div>
                <div class="summary-subtitle">Income transactions processed</div>
            </div>
            <div class="summary-card">
                <h4>Taxable Income</h4>
                <div class="summary-value positive">$<?php echo number_format($summary['total_taxable_income'], 2); ?></div>
                <div class="summary-subtitle">Subject to taxation</div>
            </div>
            <div class="summary-card">
                <h4>Non-Taxable Income</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_non_taxable_income'], 2); ?></div>
                <div class="summary-subtitle">Not subject to taxation</div>
            </div>
            <div class="summary-card">
                <h4>Total Income</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_taxable_income'] + $summary['total_non_taxable_income'], 2); ?></div>
                <div class="summary-subtitle">All income sources</div>
            </div>
        </div>

        <!-- Income Type Breakdown -->
        <?php if (!empty($summary['by_type'])): ?>
            <div class="breakdown-section">
                <div class="breakdown-header">
                    <h3>📊 Income by Type</h3>
                </div>
                <div class="breakdown-content">
                    <?php foreach ($summary['by_type'] as $type => $data): ?>
                        <div class="breakdown-item">
                            <div class="breakdown-item-header">
                                <div class="breakdown-item-name">
                                    <?php echo $income_types[$type]['name']; ?>
                                    <span class="income-type-badge <?php echo $income_types[$type]['taxable'] ? 'taxable-badge' : 'non-taxable-badge'; ?>">
                                        <?php echo $income_types[$type]['taxable'] ? 'Taxable' : 'Non-Taxable'; ?>
                                    </span>
                                </div>
                                <div class="breakdown-item-count"><?php echo $data['count']; ?> transactions</div>
                            </div>
                            <div class="breakdown-item-totals">
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Total Value</div>
                                    <div class="breakdown-total-value">$<?php echo number_format($data['total_usd'], 2); ?></div>
                                </div>
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Tax Form</div>
                                    <div class="breakdown-total-value" style="font-size: 0.8rem;"><?php echo $income_types[$type]['form']; ?></div>
                                </div>
                                <div class="breakdown-total-item">
                                    <div class="breakdown-total-label">Category</div>
                                    <div class="breakdown-total-value" style="font-size: 0.8rem;"><?php echo $income_types[$type]['category']; ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Exchange Breakdown -->
        <?php if (!empty($summary['by_exchange'])): ?>
            <div class="breakdown-section">
                <div class="breakdown-header">
                    <h3>🏦 Income by Exchange</h3>
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
                                    <div class="breakdown-total-label">Total Value</div>
                                    <div class="breakdown-total-value">$<?php echo number_format($data['total_usd'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Detailed Income Table -->
        <div class="income-table-container">
            <div class="income-table-header">
                <h3>📋 Detailed Income Transactions</h3>
                <div class="income-table-actions">
                    <button class="btn btn-success" onclick="exportToPDF()">📄 Export PDF</button>
                    <button class="btn btn-secondary" onclick="printReport()">🖨️ Print</button>
                    <button class="btn btn-secondary" onclick="exportToCSV()">📊 Export CSV</button>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="income-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">Date</th>
                            <th style="width: 15%;">Income Type</th>
                            <th style="width: 10%;">Asset</th>
                            <th style="width: 12%;">Amount</th>
                            <th style="width: 12%;">USD Value</th>
                            <th style="width: 8%;">Taxable</th>
                            <th style="width: 10%;">Tax Form</th>
                            <th style="width: 15%;">Exchange</th>
                            <th style="width: 8%;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($income_data as $transaction): ?>
                        <tr>
                            <td><?php echo date('m/d/Y', strtotime($transaction['date'])); ?></td>
                            <td>
                                <?php echo $income_types[$transaction['income_type']]['name']; ?>
                                <span class="income-type-badge <?php echo $transaction['taxable'] ? 'taxable-badge' : 'non-taxable-badge'; ?>">
                                    <?php echo $transaction['taxable'] ? 'Taxable' : 'Non-Taxable'; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['asset']); ?></td>
                            <td class="number"><?php echo number_format($transaction['amount'], 8); ?></td>
                            <td class="number">$<?php echo number_format($transaction['usd_value'], 2); ?></td>
                            <td class="number">
                                <span class="income-type-badge <?php echo $transaction['taxable'] ? 'taxable-badge' : 'non-taxable-badge'; ?>">
                                    <?php echo $transaction['taxable'] ? 'Yes' : 'No'; ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($transaction['form']); ?></td>
                            <td>
                                <span class="exchange-badge"><?php echo htmlspecialchars($transaction['exchange_name']); ?></span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--medium-gray);">
                                <?php echo htmlspecialchars($transaction['notes'] ?: '--'); ?>
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
    // Toggle income type selection
    function toggleIncomeType(typeKey) {
        const checkbox = document.getElementById('income_type_' + typeKey);
        const item = checkbox.closest('.income-type-item');
        
        checkbox.checked = !checkbox.checked;
        
        if (checkbox.checked) {
            item.classList.add('selected');
        } else {
            item.classList.remove('selected');
        }
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
        const incomeData = <?php echo json_encode($income_data ?? []); ?>;
        const summary = <?php echo json_encode($summary ?? []); ?>;
        const incomeTypes = <?php echo json_encode($income_types); ?>;
        
        if (incomeData.length === 0) {
            alert('No data to export');
            return;
        }
        
        let csv = 'Date,Income Type,Asset,Amount,USD Value,Taxable,Tax Form,Exchange,Notes\n';
        
        incomeData.forEach(transaction => {
            csv += `"${transaction.date}","${incomeTypes[transaction.income_type].name}","${transaction.asset}","${transaction.amount}","${transaction.usd_value}","${transaction.taxable ? 'Yes' : 'No'}","${transaction.form}","${transaction.exchange_name}","${transaction.notes || ''}"\n`;
        });
        
        // Add summary data
        csv += '\n\nSummary\n';
        csv += `Total Transactions,${summary.total_transactions}\n`;
        csv += `Total Taxable Income,${summary.total_taxable_income}\n`;
        csv += `Total Non-Taxable Income,${summary.total_non_taxable_income}\n`;
        csv += `Total Income,${summary.total_taxable_income + summary.total_non_taxable_income}\n`;
        
        // Add income type breakdown
        csv += '\n\nIncome by Type\n';
        csv += 'Type,Count,Total USD,Taxable,Form,Category\n';
        Object.entries(summary.by_type || {}).forEach(([type, data]) => {
            csv += `"${incomeTypes[type].name}","${data.count}","${data.total_usd}","${incomeTypes[type].taxable ? 'Yes' : 'No'}","${incomeTypes[type].form}","${incomeTypes[type].category}"\n`;
        });
        
        // Download CSV
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `taxable_income_${document.getElementById('tax_year').value}.csv`;
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
        // Select all income types
        const selectAllIncomeBtn = document.createElement('button');
        selectAllIncomeBtn.type = 'button';
        selectAllIncomeBtn.className = 'btn btn-secondary';
        selectAllIncomeBtn.textContent = 'Select All Income Types';
        selectAllIncomeBtn.style.marginBottom = '10px';
        
        const incomeTypeSelection = document.querySelector('.income-type-selection');
        if (incomeTypeSelection) {
            incomeTypeSelection.insertBefore(selectAllIncomeBtn, incomeTypeSelection.querySelector('.income-type-grid'));
            
            selectAllIncomeBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_income_types[]"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                    const item = cb.closest('.income-type-item');
                    if (cb.checked) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                });
                
                selectAllIncomeBtn.textContent = allChecked ? 'Select All Income Types' : 'Deselect All Income Types';
            });
        }

        // Select all exchanges
        const selectAllExchangeBtn = document.createElement('button');
        selectAllExchangeBtn.type = 'button';
        selectAllExchangeBtn.className = 'btn btn-secondary';
        selectAllExchangeBtn.textContent = 'Select All Exchanges';
        selectAllExchangeBtn.style.marginBottom = '10px';
        
        const exchangeSelection = document.querySelector('.exchange-selection');
        if (exchangeSelection) {
            exchangeSelection.insertBefore(selectAllExchangeBtn, exchangeSelection.querySelector('.exchange-checkboxes'));
            
            selectAllExchangeBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_exchanges[]"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                });
                
                selectAllExchangeBtn.textContent = allChecked ? 'Select All Exchanges' : 'Deselect All Exchanges';
            });
        }
    });
</script>

<?php include get_stylesheet_directory() . '/dashboard/footer-dashboard.php'; ?>