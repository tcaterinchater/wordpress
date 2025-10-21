<?php
// =============================================================================
// TEMPLATE NAME: Form 8949 Generator
// -----------------------------------------------------------------------------
// Generates IRS Form 8949 for cryptocurrency capital gains and losses
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

// Configuration
if (!defined('CRYPTO_SYMBOLS')) {
    define('CRYPTO_SYMBOLS', ['BTC','ETH','XRP','LTC','ADA','DOT','USDT','USDC','BNB','DOGE','BCH','SOL','MATIC','AVAX','LINK','UNI','ATOM','NEAR','FTM','ALGO']);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_form') {
        $tax_year = $_POST['tax_year'] ?? date('Y');
        $form_type = $_POST['form_type'] ?? 'both'; // 'short', 'long', 'both'
        
        // Process transactions for Form 8949
        $form_data = processTransactionsForForm8949($pdo, $tax_year, $form_type);
        $summary = calculateForm8949Summary($form_data);
    }
}

// Process transactions for Form 8949
function processTransactionsForForm8949($pdo, $tax_year, $form_type) {
    $start_date = $tax_year . '-01-01 00:00:00';
    $end_date = $tax_year . '-12-31 23:59:59';
    
    // Get all transactions for the tax year
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE userid = 1 
            AND datetime >= ? AND datetime <= ?
            AND (w_status = 'Sale' OR status = 'Sale')
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed_transactions = [];
    $asset_balances = [];
    
    foreach ($transactions as $transaction) {
        $date = $transaction['datetime'];
        $asset = '';
        $amount = 0;
        $price_per_unit = 0;
        $transaction_type = '';
        
        // Determine if this is a sale transaction
        if ($transaction['status'] === 'Sale' && $transaction['b_total'] > 0) {
            $asset = strtoupper($transaction['b_symbol']);
            $amount = $transaction['b_total'];
            $transaction_type = 'Sale';
            
            // Calculate proceeds (assuming b_total is in USD)
            $proceeds = $amount;
            
            // For simplicity, we'll use a basic FIFO method
            // In a real implementation, you'd need to track cost basis properly
            $cost_basis = calculateCostBasis($asset, $amount, $asset_balances);
            
            $processed_transactions[] = [
                'date_acquired' => 'Various', // Would need to track actual acquisition dates
                'date_sold' => date('m/d/Y', strtotime($date)),
                'description' => "Sale of {$amount} {$asset}",
                'proceeds' => $proceeds,
                'cost_basis' => $cost_basis,
                'adjustment_code' => '',
                'adjustment_amount' => 0,
                'gain_loss' => $proceeds - $cost_basis,
                'asset' => $asset,
                'amount' => $amount
            ];
        }
    }
    
    return $processed_transactions;
}

// Calculate cost basis using FIFO method
function calculateCostBasis($asset, $amount_sold, &$asset_balances) {
    // This is a simplified FIFO implementation
    // In reality, you'd need to track each purchase with date and cost
    $cost_basis = 0;
    
    // For demo purposes, we'll use a simple average cost
    // In production, implement proper FIFO/LIFO tracking
    $estimated_cost_per_unit = 0.8; // 80% of sale price as cost basis
    $cost_basis = $amount_sold * $estimated_cost_per_unit;
    
    return $cost_basis;
}

// Calculate Form 8949 summary
function calculateForm8949Summary($form_data) {
    $short_term = [];
    $long_term = [];
    $total_short_proceeds = 0;
    $total_short_cost = 0;
    $total_long_proceeds = 0;
    $total_long_cost = 0;
    
    foreach ($form_data as $transaction) {
        // For demo, we'll classify as short-term (held < 1 year)
        // In production, calculate based on actual holding period
        $short_term[] = $transaction;
        $total_short_proceeds += $transaction['proceeds'];
        $total_short_cost += $transaction['cost_basis'];
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
        'total_gain_loss' => ($total_short_proceeds - $total_short_cost) + ($total_long_proceeds - $total_long_cost)
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
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: var(--white);
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--primary-blue);
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
    }

    .form8949-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .form8949-actions {
        display: flex;
        gap: 10px;
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
        <h1>📋 Form 8949 Generator</h1>
        <p>Generate IRS Form 8949 for cryptocurrency capital gains and losses</p>
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
                <h4>Short Term Transactions</h4>
                <div class="summary-value"><?php echo count($summary['short_term']); ?></div>
            </div>
            <div class="summary-card">
                <h4>Long Term Transactions</h4>
                <div class="summary-value"><?php echo count($summary['long_term']); ?></div>
            </div>
            <div class="summary-card">
                <h4>Total Proceeds</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_short_proceeds'] + $summary['total_long_proceeds'], 2); ?></div>
            </div>
            <div class="summary-card">
                <h4>Total Cost Basis</h4>
                <div class="summary-value">$<?php echo number_format($summary['total_short_cost'] + $summary['total_long_cost'], 2); ?></div>
            </div>
            <div class="summary-card">
                <h4>Net Gain/Loss</h4>
                <div class="summary-value <?php echo $summary['total_gain_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($summary['total_gain_loss'], 2); ?>
                </div>
            </div>
        </div>

        <!-- Form 8949 Display -->
        <div class="form8949-container">
            <div class="form8949-header">
                <h3>📋 Form 8949 - Sales and Other Dispositions of Capital Assets</h3>
                <div class="form8949-actions">
                    <button class="btn btn-success" onclick="exportToPDF()">📄 Export PDF</button>
                    <button class="btn btn-secondary" onclick="printForm()">🖨️ Print</button>
                </div>
            </div>

            <?php if (!empty($summary['short_term'])): ?>
                <h4 style="padding: 20px 25px 10px; margin: 0; color: var(--dark-gray);">Part I - Short-Term Capital Gains and Losses</h4>
                <div style="overflow-x: auto;">
                    <table class="form8949-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date Acquired</th>
                                <th style="width: 15%;">Date Sold</th>
                                <th style="width: 30%;">Description</th>
                                <th style="width: 12%;">Proceeds</th>
                                <th style="width: 12%;">Cost Basis</th>
                                <th style="width: 8%;">Code</th>
                                <th style="width: 8%;">Adjustment</th>
                                <th style="width: 12%;">Gain/Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['short_term'] as $index => $transaction): ?>
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
                <h4 style="padding: 20px 25px 10px; margin: 0; color: var(--dark-gray);">Part II - Long-Term Capital Gains and Losses</h4>
                <div style="overflow-x: auto;">
                    <table class="form8949-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Date Acquired</th>
                                <th style="width: 15%;">Date Sold</th>
                                <th style="width: 30%;">Description</th>
                                <th style="width: 12%;">Proceeds</th>
                                <th style="width: 12%;">Cost Basis</th>
                                <th style="width: 8%;">Code</th>
                                <th style="width: 8%;">Adjustment</th>
                                <th style="width: 12%;">Gain/Loss</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['long_term'] as $index => $transaction): ?>
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
                    <p>No cryptocurrency sales were found for the selected tax year.</p>
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

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        });
    }, 5000);
</script>

<?php include get_stylesheet_directory() . '/dashboard/footer-dashboard.php'; ?>