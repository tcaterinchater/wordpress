<?php
// =============================================================================
// TEMPLATE NAME: Get Started - 5-Step Setup Wizard
// -----------------------------------------------------------------------------
// Interactive wizard to guide users through the complete setup process
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        switch ($action) {
            case 'add_exchange':
                $exchange_name = $_POST['exchange_name'] ?? '';
                $cost_basis_method = $_POST['cost_basis_method'] ?? 'FIFO';
                
                if (empty($exchange_name)) {
                    throw new Exception('Exchange name is required');
                }
                
                // Check if exchange already exists
                $stmt = $pdo->prepare("SELECT id FROM vd_user_exchanges WHERE userid = 1 AND exchangename = ?");
                $stmt->execute([$exchange_name]);
                if ($stmt->fetch()) {
                    throw new Exception('Exchange already exists');
                }
                
                // Add exchange
                $stmt = $pdo->prepare("INSERT INTO vd_user_exchanges (userid, exchangename, cost_basis_method, created_at) VALUES (1, ?, ?, NOW())");
                $stmt->execute([$exchange_name, $cost_basis_method]);
                
                $response['success'] = true;
                $response['message'] = 'Exchange added successfully';
                $response['data']['exchange_id'] = $pdo->lastInsertId();
                break;
                
            case 'add_wallet':
                $wallet_name = $_POST['wallet_name'] ?? '';
                $wallet_address = $_POST['wallet_address'] ?? '';
                $wallet_type = $_POST['wallet_type'] ?? '';
                
                if (empty($wallet_name) || empty($wallet_address)) {
                    throw new Exception('Wallet name and address are required');
                }
                
                // Add wallet (assuming we have a wallets table)
                $stmt = $pdo->prepare("INSERT INTO vd_user_wallets (userid, wallet_name, wallet_address, wallet_type, created_at) VALUES (1, ?, ?, ?, NOW())");
                $stmt->execute([$wallet_name, $wallet_address, $wallet_type]);
                
                $response['success'] = true;
                $response['message'] = 'Wallet added successfully';
                $response['data']['wallet_id'] = $pdo->lastInsertId();
                break;
                
            case 'add_transaction':
                $transaction_data = $_POST['transaction_data'] ?? [];
                
                if (empty($transaction_data)) {
                    throw new Exception('Transaction data is required');
                }
                
                // Add transaction
                $stmt = $pdo->prepare("INSERT INTO vd_user_transactions (userid, datetime, w_status, w_symbol, w_total, b_symbol, b_total, w_note, b_note, status, created_at) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $transaction_data['datetime'],
                    $transaction_data['w_status'],
                    $transaction_data['w_symbol'],
                    $transaction_data['w_total'],
                    $transaction_data['b_symbol'],
                    $transaction_data['b_total'],
                    $transaction_data['w_note'],
                    $transaction_data['b_note'],
                    $transaction_data['status']
                ]);
                
                $response['success'] = true;
                $response['message'] = 'Transaction added successfully';
                $response['data']['transaction_id'] = $pdo->lastInsertId();
                break;
                
            case 'get_exchanges':
                $stmt = $pdo->prepare("SELECT * FROM vd_user_exchanges WHERE userid = 1 ORDER BY created_at DESC");
                $stmt->execute();
                $exchanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['data']['exchanges'] = $exchanges;
                break;
                
            case 'get_wallets':
                $stmt = $pdo->prepare("SELECT * FROM vd_user_wallets WHERE userid = 1 ORDER BY created_at DESC");
                $stmt->execute();
                $wallets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['data']['wallets'] = $wallets;
                break;
                
            case 'get_transactions':
                $stmt = $pdo->prepare("SELECT * FROM vd_user_transactions WHERE userid = 1 ORDER BY datetime DESC LIMIT 50");
                $stmt->execute();
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $response['success'] = true;
                $response['data']['transactions'] = $transactions;
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Available exchanges
$exchanges = [
    'Binance' => 'Binance',
    'Coinbase Pro' => 'Coinbase Pro',
    'Coinbase Pro (Legacy)' => 'Coinbase Pro (Legacy)',
    'Coinbase Advanced' => 'Coinbase Advanced',
    'Kraken' => 'Kraken',
    'Bitfinex' => 'Bitfinex',
    'KuCoin' => 'KuCoin',
    'Gate.io' => 'Gate.io',
    'Huobi Global' => 'Huobi Global',
    'OKX' => 'OKX',
    'Bybit' => 'Bybit',
    'FTX (Legacy)' => 'FTX (Legacy)',
    'Gemini' => 'Gemini',
    'Bittrex' => 'Bittrex',
    'Poloniex' => 'Poloniex',
    'Crypto.com Exchange' => 'Crypto.com Exchange',
    'Other Exchange' => 'Other Exchange'
];

// Available wallets
$wallets = [
    'BTC' => 'Bitcoin',
    'ETH' => 'Ethereum',
    'XRP' => 'Ripple',
    'LTC' => 'Litecoin',
    'ADA' => 'Cardano',
    'DOT' => 'Polkadot',
    'USDT' => 'Tether',
    'USDC' => 'USD Coin',
    'BNB' => 'Binance Coin',
    'DOGE' => 'Dogecoin',
    'BCH' => 'Bitcoin Cash',
    'SOL' => 'Solana',
    'MATIC' => 'Polygon',
    'AVAX' => 'Avalanche',
    'LINK' => 'Chainlink',
    'UNI' => 'Uniswap',
    'ATOM' => 'Cosmos',
    'NEAR' => 'NEAR Protocol',
    'FTM' => 'Fantom',
    'ALGO' => 'Algorand'
];

// Cost basis methods
$cost_basis_methods = [
    'FIFO' => 'First In, First Out',
    'LIFO' => 'Last In, First Out',
    'HIFO' => 'Highest In, First Out',
    'Average Cost Basis' => 'Average Cost Basis'
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
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Header */
    .page-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 40px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0, 67, 255, 0.15);
        text-align: center;
    }

    .page-header h1 {
        font-size: 2.5rem;
        margin-bottom: 15px;
        color: var(--white);
    }

    .page-header p {
        opacity: 0.9;
        font-size: 1.2rem;
        margin-bottom: 0;
    }

    /* Progress Bar */
    .progress-container {
        background: var(--white);
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
    }

    .progress-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .progress-title {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .progress-step {
        font-size: 1rem;
        color: var(--medium-gray);
        font-weight: 500;
    }

    .progress-bar {
        width: 100%;
        height: 8px;
        background: #e0e0e0;
        border-radius: 4px;
        overflow: hidden;
        margin-bottom: 20px;
    }

    .progress-fill {
        height: 100%;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        border-radius: 4px;
        transition: width 0.3s ease;
        width: 20%;
    }

    .progress-steps {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .step-indicator {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        flex: 1;
        position: relative;
    }

    .step-indicator:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 15px;
        left: 50%;
        width: 100%;
        height: 2px;
        background: #e0e0e0;
        z-index: 1;
    }

    .step-indicator.completed:not(:last-child)::after {
        background: var(--primary-blue);
    }

    .step-circle {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: #e0e0e0;
        color: var(--medium-gray);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.9rem;
        position: relative;
        z-index: 2;
        transition: all 0.3s ease;
    }

    .step-indicator.active .step-circle {
        background: var(--primary-blue);
        color: var(--white);
    }

    .step-indicator.completed .step-circle {
        background: var(--success-green);
        color: var(--white);
    }

    .step-label {
        font-size: 0.8rem;
        color: var(--medium-gray);
        text-align: center;
        font-weight: 500;
    }

    .step-indicator.active .step-label {
        color: var(--primary-blue);
        font-weight: 600;
    }

    .step-indicator.completed .step-label {
        color: var(--success-green);
        font-weight: 600;
    }

    /* Step Content */
    .step-content {
        background: var(--white);
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
        min-height: 400px;
    }

    .step-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 25px;
    }

    .step-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }

    .step-title {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--dark-gray);
        margin: 0;
    }

    .step-description {
        color: var(--medium-gray);
        font-size: 1rem;
        margin: 5px 0 0 0;
    }

    /* Forms */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: var(--dark-gray);
        margin-bottom: 8px;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
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

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-help {
        font-size: 0.8rem;
        color: var(--medium-gray);
        margin-top: 5px;
    }

    /* Buttons */
    .btn {
        padding: 12px 24px;
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

    .btn-secondary {
        background: var(--medium-gray);
        color: var(--white);
    }

    .btn-secondary:hover {
        background: var(--dark-gray);
    }

    .btn-success {
        background: var(--success-green);
        color: var(--white);
    }

    .btn-success:hover {
        background: #218838;
    }

    .btn-outline {
        background: transparent;
        color: var(--primary-blue);
        border: 2px solid var(--primary-blue);
    }

    .btn-outline:hover {
        background: var(--primary-blue);
        color: var(--white);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }

    /* Button Groups */
    .btn-group {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 30px;
    }

    /* Lists */
    .item-list {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .item-list h4 {
        color: var(--dark-gray);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .item-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }

    .item-card {
        background: var(--white);
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        transition: all 0.2s ease;
    }

    .item-card:hover {
        border-color: var(--primary-blue);
        box-shadow: 0 2px 8px rgba(0, 67, 255, 0.1);
    }

    .item-name {
        font-weight: 600;
        color: var(--dark-gray);
        margin-bottom: 5px;
    }

    .item-details {
        font-size: 0.8rem;
        color: var(--medium-gray);
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

    /* Loading States */
    .loading {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid var(--primary-blue);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Transaction Table */
    .transaction-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .transaction-table th,
    .transaction-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #e0e0e0;
        font-size: 0.9rem;
    }

    .transaction-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .transaction-table tbody tr:hover {
        background: rgba(0, 67, 255, 0.02);
    }

    .transaction-table .number {
        text-align: right;
        font-family: 'Courier New', monospace;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--medium-gray);
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 15px;
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

        .form-row {
            grid-template-columns: 1fr;
        }

        .item-grid {
            grid-template-columns: 1fr;
        }

        .btn-group {
            flex-direction: column;
        }

        .progress-steps {
            flex-wrap: wrap;
            gap: 10px;
        }

        .step-indicator {
            flex: none;
            min-width: 80px;
        }

        .step-indicator:not(:last-child)::after {
            display: none;
        }
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1>🚀 Get Started with CryptoTax</h1>
        <p>Follow these 5 simple steps to set up your cryptocurrency tax reporting</p>
    </div>

    <!-- Progress Bar -->
    <div class="progress-container">
        <div class="progress-header">
            <div class="progress-title">Setup Progress</div>
            <div class="progress-step">Step <span id="current-step">1</span> of 5</div>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="progress-fill"></div>
        </div>
        <div class="progress-steps">
            <div class="step-indicator active" data-step="1">
                <div class="step-circle">1</div>
                <div class="step-label">Add Exchanges</div>
            </div>
            <div class="step-indicator" data-step="2">
                <div class="step-circle">2</div>
                <div class="step-label">Add Wallets</div>
            </div>
            <div class="step-indicator" data-step="3">
                <div class="step-circle">3</div>
                <div class="step-label">Add Transactions</div>
            </div>
            <div class="step-indicator" data-step="4">
                <div class="step-circle">4</div>
                <div class="step-label">Review Transactions</div>
            </div>
            <div class="step-indicator" data-step="5">
                <div class="step-circle">5</div>
                <div class="step-label">Generate Form 8949</div>
            </div>
        </div>
    </div>

    <!-- Step Content -->
    <div class="step-content">
        <!-- Step 1: Add Exchanges -->
        <div class="step" id="step-1">
            <div class="step-header">
                <div class="step-icon">🏦</div>
                <div>
                    <h2 class="step-title">Add Your Exchanges</h2>
                    <p class="step-description">Connect your cryptocurrency exchanges to import transaction data</p>
                </div>
            </div>

            <form id="exchange-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="exchange_name">Exchange Name</label>
                        <select name="exchange_name" id="exchange_name" class="form-control" required>
                            <option value="">Select an exchange</option>
                            <?php foreach ($exchanges as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Choose the exchange you want to add</div>
                    </div>
                    <div class="form-group">
                        <label for="cost_basis_method">Cost Basis Method</label>
                        <select name="cost_basis_method" id="cost_basis_method" class="form-control">
                            <?php foreach ($cost_basis_methods as $value => $label): ?>
                                <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Method for calculating cost basis</div>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-outline" onclick="addExchange()">
                        ➕ Add Exchange
                    </button>
                </div>
            </form>

            <div class="item-list" id="exchanges-list">
                <h4>Added Exchanges</h4>
                <div class="item-grid" id="exchanges-grid">
                    <div class="empty-state">
                        <div class="empty-state-icon">🏦</div>
                        <h3>No exchanges added yet</h3>
                        <p>Add your first exchange to get started</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Add Wallets -->
        <div class="step" id="step-2" style="display: none;">
            <div class="step-header">
                <div class="step-icon">💼</div>
                <div>
                    <h2 class="step-title">Add Your Wallets</h2>
                    <p class="step-description">Add your cryptocurrency wallets to track holdings</p>
                </div>
            </div>

            <form id="wallet-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="wallet_name">Wallet Name</label>
                        <input type="text" name="wallet_name" id="wallet_name" class="form-control" placeholder="e.g., My Bitcoin Wallet" required>
                        <div class="form-help">Give your wallet a descriptive name</div>
                    </div>
                    <div class="form-group">
                        <label for="wallet_type">Wallet Type</label>
                        <select name="wallet_type" id="wallet_type" class="form-control">
                            <?php foreach ($wallets as $symbol => $name): ?>
                                <option value="<?php echo $symbol; ?>"><?php echo $name; ?> (<?php echo $symbol; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Select the cryptocurrency type</div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="wallet_address">Wallet Address</label>
                    <input type="text" name="wallet_address" id="wallet_address" class="form-control" placeholder="Enter wallet address" required>
                    <div class="form-help">Enter the public address of your wallet</div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-outline" onclick="addWallet()">
                        ➕ Add Wallet
                    </button>
                </div>
            </form>

            <div class="item-list" id="wallets-list">
                <h4>Added Wallets</h4>
                <div class="item-grid" id="wallets-grid">
                    <div class="empty-state">
                        <div class="empty-state-icon">💼</div>
                        <h3>No wallets added yet</h3>
                        <p>Add your first wallet to get started</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Add Transactions -->
        <div class="step" id="step-3" style="display: none;">
            <div class="step-header">
                <div class="step-icon">📝</div>
                <div>
                    <h2 class="step-title">Add Transactions</h2>
                    <p class="step-description">Add your cryptocurrency transactions manually or import from exchanges</p>
                </div>
            </div>

            <form id="transaction-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="transaction_date">Transaction Date</label>
                        <input type="datetime-local" name="transaction_date" id="transaction_date" class="form-control" required>
                        <div class="form-help">When did this transaction occur?</div>
                    </div>
                    <div class="form-group">
                        <label for="transaction_type">Transaction Type</label>
                        <select name="transaction_type" id="transaction_type" class="form-control" required>
                            <option value="">Select type</option>
                            <option value="buy">Buy</option>
                            <option value="sell">Sell</option>
                            <option value="trade">Trade</option>
                        </select>
                        <div class="form-help">What type of transaction is this?</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="asset_bought">Asset Bought</label>
                        <select name="asset_bought" id="asset_bought" class="form-control">
                            <option value="">Select asset</option>
                            <?php foreach ($wallets as $symbol => $name): ?>
                                <option value="<?php echo $symbol; ?>"><?php echo $name; ?> (<?php echo $symbol; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">Cryptocurrency you bought</div>
                    </div>
                    <div class="form-group">
                        <label for="amount_bought">Amount Bought</label>
                        <input type="number" name="amount_bought" id="amount_bought" class="form-control" step="0.00000001" placeholder="0.00000000">
                        <div class="form-help">Amount of cryptocurrency bought</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="asset_sold">Asset Sold</label>
                        <select name="asset_sold" id="asset_sold" class="form-control">
                            <option value="">Select asset</option>
                            <option value="USD">USD</option>
                            <?php foreach ($wallets as $symbol => $name): ?>
                                <option value="<?php echo $symbol; ?>"><?php echo $name; ?> (<?php echo $symbol; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-help">What you sold (USD or cryptocurrency)</div>
                    </div>
                    <div class="form-group">
                        <label for="amount_sold">Amount Sold</label>
                        <input type="number" name="amount_sold" id="amount_sold" class="form-control" step="0.00000001" placeholder="0.00000000">
                        <div class="form-help">Amount of cryptocurrency or USD sold</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="transaction_notes">Notes</label>
                    <textarea name="transaction_notes" id="transaction_notes" class="form-control" rows="3" placeholder="Optional notes about this transaction"></textarea>
                    <div class="form-help">Add any additional notes about this transaction</div>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-outline" onclick="addTransaction()">
                        ➕ Add Transaction
                    </button>
                </div>
            </form>

            <div class="item-list" id="transactions-list">
                <h4>Added Transactions</h4>
                <div id="transactions-grid">
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <h3>No transactions added yet</h3>
                        <p>Add your first transaction to get started</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Review Transactions -->
        <div class="step" id="step-4" style="display: none;">
            <div class="step-header">
                <div class="step-icon">🔍</div>
                <div>
                    <h2 class="step-title">Review Transactions</h2>
                    <p class="step-description">Review and verify your transaction data before generating tax forms</p>
                </div>
            </div>

            <div class="item-list">
                <h4>Transaction Summary</h4>
                <div id="transaction-summary">
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <h3>No transactions to review</h3>
                        <p>Add some transactions first to review them</p>
                    </div>
                </div>
            </div>

            <div class="item-list">
                <h4>Recent Transactions</h4>
                <div id="recent-transactions">
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <h3>No transactions found</h3>
                        <p>Add some transactions to see them here</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 5: Generate Form 8949 -->
        <div class="step" id="step-5" style="display: none;">
            <div class="step-header">
                <div class="step-icon">📋</div>
                <div>
                    <h2 class="step-title">Generate Form 8949</h2>
                    <p class="step-description">Generate your IRS Form 8949 for capital gains and losses reporting</p>
                </div>
            </div>

            <div class="alert alert-info">
                ℹ️ Your setup is complete! You can now generate Form 8949 and other tax reports.
            </div>

            <div class="item-list">
                <h4>Ready to Generate Reports</h4>
                <p>You have successfully completed the setup process. You can now:</p>
                <ul>
                    <li>Generate Form 8949 for capital gains and losses</li>
                    <li>Create taxable income reports</li>
                    <li>Generate capital gains analysis</li>
                    <li>Export data in CSV or PDF format</li>
                </ul>
            </div>

            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="generateForm8949()">
                    📋 Generate Form 8949
                </button>
                <button type="button" class="btn btn-outline" onclick="goToReports()">
                    📊 Go to Reports
                </button>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="btn-group">
        <button type="button" class="btn btn-secondary" id="prev-btn" onclick="previousStep()" disabled>
            ← Previous
        </button>
        <button type="button" class="btn btn-primary" id="next-btn" onclick="nextStep()">
            Next →
        </button>
    </div>
</div>

<script>
    // Global variables
    let currentStep = 1;
    const totalSteps = 5;
    let exchanges = [];
    let wallets = [];
    let transactions = [];

    // Initialize the wizard
    document.addEventListener('DOMContentLoaded', function() {
        updateProgress();
        loadData();
        
        // Set default transaction date to today
        const now = new Date();
        const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        document.getElementById('transaction_date').value = localDateTime;
    });

    // Update progress bar and step indicators
    function updateProgress() {
        const progressFill = document.getElementById('progress-fill');
        const currentStepElement = document.getElementById('current-step');
        
        // Update progress bar
        const progress = (currentStep / totalSteps) * 100;
        progressFill.style.width = progress + '%';
        
        // Update current step text
        currentStepElement.textContent = currentStep;
        
        // Update step indicators
        document.querySelectorAll('.step-indicator').forEach((indicator, index) => {
            const stepNumber = index + 1;
            indicator.classList.remove('active', 'completed');
            
            if (stepNumber === currentStep) {
                indicator.classList.add('active');
            } else if (stepNumber < currentStep) {
                indicator.classList.add('completed');
            }
        });
        
        // Update navigation buttons
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        
        prevBtn.disabled = currentStep === 1;
        
        if (currentStep === totalSteps) {
            nextBtn.textContent = 'Complete Setup';
            nextBtn.onclick = completeSetup;
        } else {
            nextBtn.textContent = 'Next →';
            nextBtn.onclick = nextStep;
        }
    }

    // Show specific step
    function showStep(stepNumber) {
        // Hide all steps
        document.querySelectorAll('.step').forEach(step => {
            step.style.display = 'none';
        });
        
        // Show current step
        document.getElementById(`step-${stepNumber}`).style.display = 'block';
        
        // Update current step
        currentStep = stepNumber;
        updateProgress();
    }

    // Next step
    function nextStep() {
        if (currentStep < totalSteps) {
            showStep(currentStep + 1);
        }
    }

    // Previous step
    function previousStep() {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    }

    // Complete setup
    function completeSetup() {
        alert('Setup complete! Redirecting to Form 8949 generator...');
        window.location.href = 'form-8949-generator.php';
    }

    // Load existing data
    function loadData() {
        loadExchanges();
        loadWallets();
        loadTransactions();
    }

    // Load exchanges
    function loadExchanges() {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_exchanges'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                exchanges = data.data.exchanges;
                updateExchangesList();
            }
        })
        .catch(error => console.error('Error loading exchanges:', error));
    }

    // Load wallets
    function loadWallets() {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_wallets'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                wallets = data.data.wallets;
                updateWalletsList();
            }
        })
        .catch(error => console.error('Error loading wallets:', error));
    }

    // Load transactions
    function loadTransactions() {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_transactions'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                transactions = data.data.transactions;
                updateTransactionsList();
                updateTransactionSummary();
            }
        })
        .catch(error => console.error('Error loading transactions:', error));
    }

    // Add exchange
    function addExchange() {
        const form = document.getElementById('exchange-form');
        const formData = new FormData(form);
        formData.append('action', 'add_exchange');
        
        const submitBtn = form.querySelector('button[type="button"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading"></div> Adding...';
        submitBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                form.reset();
                loadExchanges();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error adding exchange:', error);
            showAlert('error', 'An error occurred while adding the exchange');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    // Add wallet
    function addWallet() {
        const form = document.getElementById('wallet-form');
        const formData = new FormData(form);
        formData.append('action', 'add_wallet');
        
        const submitBtn = form.querySelector('button[type="button"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading"></div> Adding...';
        submitBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                form.reset();
                loadWallets();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error adding wallet:', error);
            showAlert('error', 'An error occurred while adding the wallet');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    // Add transaction
    function addTransaction() {
        const form = document.getElementById('transaction-form');
        const formData = new FormData(form);
        
        // Prepare transaction data
        const transactionData = {
            datetime: formData.get('transaction_date'),
            w_status: formData.get('transaction_type') === 'buy' ? 'Buy' : 'Sale',
            w_symbol: formData.get('asset_bought'),
            w_total: parseFloat(formData.get('amount_bought')) || 0,
            b_symbol: formData.get('asset_sold'),
            b_total: parseFloat(formData.get('amount_sold')) || 0,
            w_note: formData.get('transaction_notes') || '',
            b_note: formData.get('transaction_notes') || '',
            status: formData.get('transaction_type') === 'buy' ? 'Buy' : 'Sale'
        };
        
        const submitBtn = form.querySelector('button[type="button"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<div class="loading"></div> Adding...';
        submitBtn.disabled = true;
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_transaction&transaction_data=${encodeURIComponent(JSON.stringify(transactionData))}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                form.reset();
                // Reset transaction date to today
                const now = new Date();
                const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
                document.getElementById('transaction_date').value = localDateTime;
                loadTransactions();
            } else {
                showAlert('error', data.message);
            }
        })
        .catch(error => {
            console.error('Error adding transaction:', error);
            showAlert('error', 'An error occurred while adding the transaction');
        })
        .finally(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    }

    // Update exchanges list
    function updateExchangesList() {
        const grid = document.getElementById('exchanges-grid');
        
        if (exchanges.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">🏦</div>
                    <h3>No exchanges added yet</h3>
                    <p>Add your first exchange to get started</p>
                </div>
            `;
        } else {
            grid.innerHTML = exchanges.map(exchange => `
                <div class="item-card">
                    <div class="item-name">${exchange.exchangename}</div>
                    <div class="item-details">Method: ${exchange.cost_basis_method}</div>
                </div>
            `).join('');
        }
    }

    // Update wallets list
    function updateWalletsList() {
        const grid = document.getElementById('wallets-grid');
        
        if (wallets.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">💼</div>
                    <h3>No wallets added yet</h3>
                    <p>Add your first wallet to get started</p>
                </div>
            `;
        } else {
            grid.innerHTML = wallets.map(wallet => `
                <div class="item-card">
                    <div class="item-name">${wallet.wallet_name}</div>
                    <div class="item-details">${wallet.wallet_type} - ${wallet.wallet_address.substring(0, 20)}...</div>
                </div>
            `).join('');
        }
    }

    // Update transactions list
    function updateTransactionsList() {
        const grid = document.getElementById('transactions-grid');
        
        if (transactions.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📝</div>
                    <h3>No transactions added yet</h3>
                    <p>Add your first transaction to get started</p>
                </div>
            `;
        } else {
            grid.innerHTML = `
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Asset Bought</th>
                            <th>Amount</th>
                            <th>Asset Sold</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${transactions.map(transaction => `
                            <tr>
                                <td>${new Date(transaction.datetime).toLocaleDateString()}</td>
                                <td>${transaction.w_status}</td>
                                <td>${transaction.w_symbol}</td>
                                <td class="number">${parseFloat(transaction.w_total).toFixed(8)}</td>
                                <td>${transaction.b_symbol}</td>
                                <td class="number">${parseFloat(transaction.b_total).toFixed(8)}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }
    }

    // Update transaction summary
    function updateTransactionSummary() {
        const summary = document.getElementById('transaction-summary');
        
        if (transactions.length === 0) {
            summary.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <h3>No transactions to review</h3>
                    <p>Add some transactions first to review them</p>
                </div>
            `;
        } else {
            const totalTransactions = transactions.length;
            const buyTransactions = transactions.filter(t => t.w_status === 'Buy').length;
            const sellTransactions = transactions.filter(t => t.status === 'Sale').length;
            const totalVolume = transactions.reduce((sum, t) => sum + parseFloat(t.b_total) + parseFloat(t.w_total), 0);
            
            summary.innerHTML = `
                <div class="item-grid">
                    <div class="item-card">
                        <div class="item-name">Total Transactions</div>
                        <div class="item-details">${totalTransactions}</div>
                    </div>
                    <div class="item-card">
                        <div class="item-name">Buy Transactions</div>
                        <div class="item-details">${buyTransactions}</div>
                    </div>
                    <div class="item-card">
                        <div class="item-name">Sell Transactions</div>
                        <div class="item-details">${sellTransactions}</div>
                    </div>
                    <div class="item-card">
                        <div class="item-name">Total Volume</div>
                        <div class="item-details">$${totalVolume.toFixed(2)}</div>
                    </div>
                </div>
            `;
        }
    }

    // Show alert
    function showAlert(type, message) {
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.innerHTML = `
            <span>${type === 'success' ? '✓' : '✗'}</span>
            ${message}
        `;
        
        // Insert at the top of the step content
        const stepContent = document.querySelector('.step-content');
        stepContent.insertBefore(alert, stepContent.firstChild);
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }

    // Generate Form 8949
    function generateForm8949() {
        window.location.href = 'form-8949-generator.php';
    }

    // Go to reports
    function goToReports() {
        window.location.href = 'reports-generator.php';
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