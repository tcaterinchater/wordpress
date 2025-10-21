<?php
// =============================================================================
// TEMPLATE NAME: Reports Generator
// -----------------------------------------------------------------------------
// Generate CSV and PDF reports for all tax forms and calculations
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
$generated_reports = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'generate_reports') {
        $report_types = $_POST['report_types'] ?? [];
        $tax_year = $_POST['tax_year'] ?? date('Y');
        $export_format = $_POST['export_format'] ?? 'csv';
        $include_summaries = isset($_POST['include_summaries']);
        $include_breakdowns = isset($_POST['include_breakdowns']);
        $cost_basis_method = $_POST['cost_basis_method'] ?? 'FIFO';
        
        if (empty($report_types)) {
            $error = "Please select at least one report type to generate.";
        } else {
            $generated_reports = generateReports($pdo, $report_types, $tax_year, $export_format, $include_summaries, $include_breakdowns, $cost_basis_method);
            $message = "Successfully generated " . count($generated_reports) . " report(s) for tax year {$tax_year}";
        }
    }
}

// Available report types
$report_types = [
    'form_8949' => [
        'name' => 'Form 8949 - Capital Gains and Losses',
        'description' => 'IRS Form 8949 for reporting capital gains and losses',
        'icon' => '📋'
    ],
    'taxable_income' => [
        'name' => 'Taxable Income Report',
        'description' => 'Report of taxable income from crypto activities',
        'icon' => '💰'
    ],
    'capital_gains' => [
        'name' => 'Capital Gains Analysis',
        'description' => 'Detailed capital gains and losses analysis',
        'icon' => '📈'
    ],
    'transaction_summary' => [
        'name' => 'Transaction Summary',
        'description' => 'Complete transaction history and summary',
        'icon' => '📊'
    ],
    'asset_performance' => [
        'name' => 'Asset Performance Report',
        'description' => 'Performance analysis by cryptocurrency',
        'icon' => '💎'
    ],
    'tax_summary' => [
        'name' => 'Tax Summary Report',
        'description' => 'Comprehensive tax summary for the year',
        'icon' => '🏛️'
    ]
];

// Generate reports function
function generateReports($pdo, $report_types, $tax_year, $export_format, $include_summaries, $include_breakdowns, $cost_basis_method) {
    $generated_reports = [];
    $start_date = $tax_year . '-01-01 00:00:00';
    $end_date = $tax_year . '-12-31 23:59:59';
    
    foreach ($report_types as $report_type) {
        try {
            $report_data = generateReportData($pdo, $report_type, $tax_year, $start_date, $end_date, $cost_basis_method);
            
            if ($export_format === 'csv') {
                $file_path = generateCSVReport($report_type, $report_data, $tax_year, $include_summaries, $include_breakdowns);
            } else {
                $file_path = generatePDFReport($report_type, $report_data, $tax_year, $include_summaries, $include_breakdowns);
            }
            
            $generated_reports[] = [
                'type' => $report_type,
                'name' => $report_data['name'],
                'file_path' => $file_path,
                'file_size' => filesize($file_path),
                'generated_at' => date('Y-m-d H:i:s'),
                'format' => strtoupper($export_format)
            ];
            
        } catch (Exception $e) {
            error_log("Error generating {$report_type} report: " . $e->getMessage());
        }
    }
    
    return $generated_reports;
}

// Generate report data
function generateReportData($pdo, $report_type, $tax_year, $start_date, $end_date, $cost_basis_method) {
    $report_data = [
        'name' => '',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => [],
        'summary' => [],
        'breakdown' => []
    ];
    
    switch ($report_type) {
        case 'form_8949':
            $report_data = generateForm8949Data($pdo, $tax_year, $start_date, $end_date, $cost_basis_method);
            break;
        case 'taxable_income':
            $report_data = generateTaxableIncomeData($pdo, $tax_year, $start_date, $end_date);
            break;
        case 'capital_gains':
            $report_data = generateCapitalGainsData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method);
            break;
        case 'transaction_summary':
            $report_data = generateTransactionSummaryData($pdo, $tax_year, $start_date, $end_date);
            break;
        case 'asset_performance':
            $report_data = generateAssetPerformanceData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method);
            break;
        case 'tax_summary':
            $report_data = generateTaxSummaryData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method);
            break;
    }
    
    return $report_data;
}

// Generate Form 8949 data
function generateForm8949Data($pdo, $tax_year, $start_date, $end_date, $cost_basis_method) {
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_date, $end_date];
    $where_sql = implode(' AND ', $where_conditions);
    
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE $where_sql 
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process transactions for Form 8949
    $form_8949_data = [];
    $purchases = [];
    $sales = [];
    
    foreach ($transactions as $transaction) {
        if ($transaction['w_status'] === 'Buy' && $transaction['w_total'] > 0) {
            $purchases[] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['w_symbol']),
                'amount' => $transaction['w_total'],
                'price_per_unit' => $transaction['b_total'] / $transaction['w_total'],
                'total_cost' => $transaction['b_total']
            ];
        }
        
        if ($transaction['status'] === 'Sale' && $transaction['b_total'] > 0) {
            $sales[] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['b_symbol']),
                'amount' => $transaction['b_total'],
                'price_per_unit' => $transaction['w_total'] / $transaction['b_total'],
                'total_proceeds' => $transaction['w_total']
            ];
        }
    }
    
    // Calculate Form 8949 entries
    foreach ($sales as $sale) {
        $cost_basis = calculateCostBasisForReport($sale['asset'], $sale['amount'], $purchases, $cost_basis_method);
        $gain_loss = $sale['total_proceeds'] - $cost_basis;
        
        $form_8949_data[] = [
            'date_acquired' => 'Various',
            'date_sold' => date('m/d/Y', strtotime($sale['date'])),
            'description' => $sale['asset'] . ' - ' . number_format($sale['amount'], 8),
            'proceeds' => $sale['total_proceeds'],
            'cost_basis' => $cost_basis,
            'gain_loss' => $gain_loss,
            'asset' => $sale['asset'],
            'amount_sold' => $sale['amount']
        ];
    }
    
    return [
        'name' => 'Form 8949 - Capital Gains and Losses',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $form_8949_data,
        'summary' => [
            'total_transactions' => count($form_8949_data),
            'total_proceeds' => array_sum(array_column($form_8949_data, 'proceeds')),
            'total_cost_basis' => array_sum(array_column($form_8949_data, 'cost_basis')),
            'total_gain_loss' => array_sum(array_column($form_8949_data, 'gain_loss'))
        ]
    ];
}

// Generate taxable income data
function generateTaxableIncomeData($pdo, $tax_year, $start_date, $end_date) {
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_date, $end_date];
    $where_sql = implode(' AND ', $where_conditions);
    
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE $where_sql 
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $taxable_income = [];
    $income_categories = [
        'Mining' => [],
        'Airdrop' => [],
        'Staking' => [],
        'Fork' => [],
        'Other' => []
    ];
    
    foreach ($transactions as $transaction) {
        $income_type = 'Other';
        $amount = 0;
        $asset = '';
        
        // Check for mining income
        if (stripos($transaction['w_note'], 'mining') !== false || 
            stripos($transaction['b_note'], 'mining') !== false) {
            $income_type = 'Mining';
            $amount = $transaction['w_total'];
            $asset = $transaction['w_symbol'];
        }
        // Check for airdrop
        elseif (stripos($transaction['w_note'], 'airdrop') !== false || 
                stripos($transaction['b_note'], 'airdrop') !== false) {
            $income_type = 'Airdrop';
            $amount = $transaction['w_total'];
            $asset = $transaction['w_symbol'];
        }
        // Check for staking
        elseif (stripos($transaction['w_note'], 'staking') !== false || 
                stripos($transaction['b_note'], 'staking') !== false) {
            $income_type = 'Staking';
            $amount = $transaction['w_total'];
            $asset = $transaction['w_symbol'];
        }
        // Check for fork
        elseif (stripos($transaction['w_note'], 'fork') !== false || 
                stripos($transaction['b_note'], 'fork') !== false) {
            $income_type = 'Fork';
            $amount = $transaction['w_total'];
            $asset = $transaction['w_symbol'];
        }
        
        if ($amount > 0) {
            $income_categories[$income_type][] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($asset),
                'amount' => $amount,
                'usd_value' => $transaction['b_total'],
                'notes' => $transaction['w_note'] . ' ' . $transaction['b_note']
            ];
        }
    }
    
    return [
        'name' => 'Taxable Income Report',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $income_categories,
        'summary' => [
            'total_income' => array_sum(array_map(function($category) {
                return array_sum(array_column($category, 'usd_value'));
            }, $income_categories)),
            'categories' => array_map(function($category) {
                return count($category);
            }, $income_categories)
        ]
    ];
}

// Generate capital gains data
function generateCapitalGainsData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method) {
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_date, $end_date];
    $where_sql = implode(' AND ', $where_conditions);
    
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE $where_sql 
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $purchases = [];
    $sales = [];
    
    foreach ($transactions as $transaction) {
        if ($transaction['w_status'] === 'Buy' && $transaction['w_total'] > 0) {
            $purchases[] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['w_symbol']),
                'amount' => $transaction['w_total'],
                'price_per_unit' => $transaction['b_total'] / $transaction['w_total'],
                'total_cost' => $transaction['b_total']
            ];
        }
        
        if ($transaction['status'] === 'Sale' && $transaction['b_total'] > 0) {
            $sales[] = [
                'date' => $transaction['datetime'],
                'asset' => strtoupper($transaction['b_symbol']),
                'amount' => $transaction['b_total'],
                'price_per_unit' => $transaction['w_total'] / $transaction['b_total'],
                'total_proceeds' => $transaction['w_total']
            ];
        }
    }
    
    $capital_gains = [];
    foreach ($sales as $sale) {
        $cost_basis = calculateCostBasisForReport($sale['asset'], $sale['amount'], $purchases, $cost_basis_method);
        $gain_loss = $sale['total_proceeds'] - $cost_basis;
        
        $sale_date = new DateTime($sale['date']);
        $is_long_term = true;
        
        foreach ($purchases as $purchase) {
            if ($purchase['asset'] === $sale['asset']) {
                $purchase_date = new DateTime($purchase['date']);
                $days_diff = $sale_date->diff($purchase_date)->days;
                if ($days_diff <= 365) {
                    $is_long_term = false;
                    break;
                }
            }
        }
        
        $capital_gains[] = [
            'date' => $sale['date'],
            'asset' => $sale['asset'],
            'amount_sold' => $sale['amount'],
            'proceeds' => $sale['total_proceeds'],
            'cost_basis' => $cost_basis,
            'gain_loss' => $gain_loss,
            'is_long_term' => $is_long_term,
            'method' => $cost_basis_method
        ];
    }
    
    return [
        'name' => 'Capital Gains Analysis',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $capital_gains,
        'summary' => [
            'total_transactions' => count($capital_gains),
            'total_proceeds' => array_sum(array_column($capital_gains, 'proceeds')),
            'total_cost_basis' => array_sum(array_column($capital_gains, 'cost_basis')),
            'total_gain_loss' => array_sum(array_column($capital_gains, 'gain_loss')),
            'short_term_gains' => array_sum(array_map(function($gain) {
                return $gain['is_long_term'] ? 0 : $gain['gain_loss'];
            }, $capital_gains)),
            'long_term_gains' => array_sum(array_map(function($gain) {
                return $gain['is_long_term'] ? $gain['gain_loss'] : 0;
            }, $capital_gains))
        ]
    ];
}

// Generate transaction summary data
function generateTransactionSummaryData($pdo, $tax_year, $start_date, $end_date) {
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_date, $end_date];
    $where_sql = implode(' AND ', $where_conditions);
    
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE $where_sql 
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $summary_data = [];
    $total_volume = 0;
    $asset_counts = [];
    
    foreach ($transactions as $transaction) {
        $summary_data[] = [
            'date' => $transaction['datetime'],
            'type' => $transaction['w_status'] . ' / ' . $transaction['status'],
            'asset_bought' => strtoupper($transaction['w_symbol']),
            'amount_bought' => $transaction['w_total'],
            'asset_sold' => strtoupper($transaction['b_symbol']),
            'amount_sold' => $transaction['b_total'],
            'total_value' => $transaction['b_total'] + $transaction['w_total'],
            'notes' => $transaction['w_note'] . ' ' . $transaction['b_note']
        ];
        
        $total_volume += $transaction['b_total'] + $transaction['w_total'];
        
        if ($transaction['w_symbol']) {
            $asset_counts[$transaction['w_symbol']] = ($asset_counts[$transaction['w_symbol']] ?? 0) + 1;
        }
        if ($transaction['b_symbol']) {
            $asset_counts[$transaction['b_symbol']] = ($asset_counts[$transaction['b_symbol']] ?? 0) + 1;
        }
    }
    
    return [
        'name' => 'Transaction Summary',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $summary_data,
        'summary' => [
            'total_transactions' => count($transactions),
            'total_volume' => $total_volume,
            'asset_counts' => $asset_counts
        ]
    ];
}

// Generate asset performance data
function generateAssetPerformanceData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method) {
    $where_conditions = ['userid = 1', 'datetime >= ?', 'datetime <= ?'];
    $params = [$start_date, $end_date];
    $where_sql = implode(' AND ', $where_conditions);
    
    $sql = "SELECT * FROM vd_user_transactions 
            WHERE $where_sql 
            ORDER BY datetime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $asset_performance = [];
    $asset_data = [];
    
    foreach ($transactions as $transaction) {
        if ($transaction['w_status'] === 'Buy' && $transaction['w_total'] > 0) {
            $asset = strtoupper($transaction['w_symbol']);
            if (!isset($asset_data[$asset])) {
                $asset_data[$asset] = [
                    'purchases' => [],
                    'sales' => [],
                    'total_bought' => 0,
                    'total_sold' => 0,
                    'total_cost' => 0,
                    'total_proceeds' => 0
                ];
            }
            
            $asset_data[$asset]['purchases'][] = [
                'date' => $transaction['datetime'],
                'amount' => $transaction['w_total'],
                'price_per_unit' => $transaction['b_total'] / $transaction['w_total'],
                'total_cost' => $transaction['b_total']
            ];
            
            $asset_data[$asset]['total_bought'] += $transaction['w_total'];
            $asset_data[$asset]['total_cost'] += $transaction['b_total'];
        }
        
        if ($transaction['status'] === 'Sale' && $transaction['b_total'] > 0) {
            $asset = strtoupper($transaction['b_symbol']);
            if (!isset($asset_data[$asset])) {
                $asset_data[$asset] = [
                    'purchases' => [],
                    'sales' => [],
                    'total_bought' => 0,
                    'total_sold' => 0,
                    'total_cost' => 0,
                    'total_proceeds' => 0
                ];
            }
            
            $asset_data[$asset]['sales'][] = [
                'date' => $transaction['datetime'],
                'amount' => $transaction['b_total'],
                'price_per_unit' => $transaction['w_total'] / $transaction['b_total'],
                'total_proceeds' => $transaction['w_total']
            ];
            
            $asset_data[$asset]['total_sold'] += $transaction['b_total'];
            $asset_data[$asset]['total_proceeds'] += $transaction['w_total'];
        }
    }
    
    foreach ($asset_data as $asset => $data) {
        $cost_basis = calculateCostBasisForReport($asset, $data['total_sold'], $data['purchases'], $cost_basis_method);
        $gain_loss = $data['total_proceeds'] - $cost_basis;
        $gain_loss_percentage = $cost_basis > 0 ? ($gain_loss / $cost_basis) * 100 : 0;
        
        $asset_performance[] = [
            'asset' => $asset,
            'total_bought' => $data['total_bought'],
            'total_sold' => $data['total_sold'],
            'total_cost' => $data['total_cost'],
            'total_proceeds' => $data['total_proceeds'],
            'cost_basis' => $cost_basis,
            'gain_loss' => $gain_loss,
            'gain_loss_percentage' => $gain_loss_percentage,
            'purchase_count' => count($data['purchases']),
            'sale_count' => count($data['sales'])
        ];
    }
    
    return [
        'name' => 'Asset Performance Report',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => $asset_performance,
        'summary' => [
            'total_assets' => count($asset_performance),
            'total_volume' => array_sum(array_column($asset_performance, 'total_proceeds')),
            'total_gain_loss' => array_sum(array_column($asset_performance, 'gain_loss'))
        ]
    ];
}

// Generate tax summary data
function generateTaxSummaryData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method) {
    // Get all data for comprehensive tax summary
    $form_8949_data = generateForm8949Data($pdo, $tax_year, $start_date, $end_date, $cost_basis_method);
    $taxable_income_data = generateTaxableIncomeData($pdo, $tax_year, $start_date, $end_date);
    $capital_gains_data = generateCapitalGainsData($pdo, $tax_year, $start_date, $end_date, $cost_basis_method);
    
    return [
        'name' => 'Tax Summary Report',
        'tax_year' => $tax_year,
        'generated_at' => date('Y-m-d H:i:s'),
        'data' => [
            'form_8949' => $form_8949_data,
            'taxable_income' => $taxable_income_data,
            'capital_gains' => $capital_gains_data
        ],
        'summary' => [
            'total_capital_gains' => $form_8949_data['summary']['total_gain_loss'],
            'total_taxable_income' => $taxable_income_data['summary']['total_income'],
            'total_transactions' => $form_8949_data['summary']['total_transactions'],
            'cost_basis_method' => $cost_basis_method
        ]
    ];
}

// Calculate cost basis for reports
function calculateCostBasisForReport($asset, $amount_sold, $purchases, $method) {
    $asset_purchases = array_filter($purchases, function($purchase) use ($asset) {
        return $purchase['asset'] === $asset;
    });
    
    if (empty($asset_purchases)) {
        return $amount_sold * 0.8; // Fallback estimate
    }
    
    switch ($method) {
        case 'FIFO':
            return calculateFIFOCostBasisForReport($asset_purchases, $amount_sold);
        case 'LIFO':
            return calculateLIFOCostBasisForReport($asset_purchases, $amount_sold);
        case 'HIFO':
            return calculateHIFOCostBasisForReport($asset_purchases, $amount_sold);
        case 'Average Cost Basis':
            return calculateAverageCostBasisForReport($asset_purchases, $amount_sold);
        default:
            return calculateFIFOCostBasisForReport($asset_purchases, $amount_sold);
    }
}

// FIFO Cost Basis Calculation for reports
function calculateFIFOCostBasisForReport($purchases, $amount_sold) {
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

// LIFO Cost Basis Calculation for reports
function calculateLIFOCostBasisForReport($purchases, $amount_sold) {
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

// HIFO Cost Basis Calculation for reports
function calculateHIFOCostBasisForReport($purchases, $amount_sold) {
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

// Average Cost Basis Calculation for reports
function calculateAverageCostBasisForReport($purchases, $amount_sold) {
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

// Generate CSV report
function generateCSVReport($report_type, $report_data, $tax_year, $include_summaries, $include_breakdowns) {
    $filename = "{$report_type}_{$tax_year}_" . date('Y-m-d_H-i-s') . ".csv";
    $filepath = sys_get_temp_dir() . '/' . $filename;
    
    $file = fopen($filepath, 'w');
    
    // Write header
    fputcsv($file, ['Report: ' . $report_data['name']]);
    fputcsv($file, ['Tax Year: ' . $tax_year]);
    fputcsv($file, ['Generated: ' . $report_data['generated_at']]);
    fputcsv($file, []);
    
    // Write data based on report type
    switch ($report_type) {
        case 'form_8949':
            fputcsv($file, ['Date Acquired', 'Date Sold', 'Description', 'Proceeds', 'Cost Basis', 'Gain/Loss', 'Asset', 'Amount Sold']);
            foreach ($report_data['data'] as $row) {
                fputcsv($file, [
                    $row['date_acquired'],
                    $row['date_sold'],
                    $row['description'],
                    $row['proceeds'],
                    $row['cost_basis'],
                    $row['gain_loss'],
                    $row['asset'],
                    $row['amount_sold']
                ]);
            }
            break;
            
        case 'taxable_income':
            foreach ($report_data['data'] as $category => $transactions) {
                fputcsv($file, [$category . ' Income']);
                fputcsv($file, ['Date', 'Asset', 'Amount', 'USD Value', 'Notes']);
                foreach ($transactions as $transaction) {
                    fputcsv($file, [
                        $transaction['date'],
                        $transaction['asset'],
                        $transaction['amount'],
                        $transaction['usd_value'],
                        $transaction['notes']
                    ]);
                }
                fputcsv($file, []);
            }
            break;
            
        case 'capital_gains':
            fputcsv($file, ['Date', 'Asset', 'Amount Sold', 'Proceeds', 'Cost Basis', 'Gain/Loss', 'Long-term', 'Method']);
            foreach ($report_data['data'] as $row) {
                fputcsv($file, [
                    $row['date'],
                    $row['asset'],
                    $row['amount_sold'],
                    $row['proceeds'],
                    $row['cost_basis'],
                    $row['gain_loss'],
                    $row['is_long_term'] ? 'Yes' : 'No',
                    $row['method']
                ]);
            }
            break;
            
        case 'transaction_summary':
            fputcsv($file, ['Date', 'Type', 'Asset Bought', 'Amount Bought', 'Asset Sold', 'Amount Sold', 'Total Value', 'Notes']);
            foreach ($report_data['data'] as $row) {
                fputcsv($file, [
                    $row['date'],
                    $row['type'],
                    $row['asset_bought'],
                    $row['amount_bought'],
                    $row['asset_sold'],
                    $row['amount_sold'],
                    $row['total_value'],
                    $row['notes']
                ]);
            }
            break;
            
        case 'asset_performance':
            fputcsv($file, ['Asset', 'Total Bought', 'Total Sold', 'Total Cost', 'Total Proceeds', 'Cost Basis', 'Gain/Loss', 'Gain/Loss %', 'Purchase Count', 'Sale Count']);
            foreach ($report_data['data'] as $row) {
                fputcsv($file, [
                    $row['asset'],
                    $row['total_bought'],
                    $row['total_sold'],
                    $row['total_cost'],
                    $row['total_proceeds'],
                    $row['cost_basis'],
                    $row['gain_loss'],
                    $row['gain_loss_percentage'],
                    $row['purchase_count'],
                    $row['sale_count']
                ]);
            }
            break;
            
        case 'tax_summary':
            fputcsv($file, ['Tax Summary for ' . $tax_year]);
            fputcsv($file, ['Total Capital Gains', $report_data['summary']['total_capital_gains']]);
            fputcsv($file, ['Total Taxable Income', $report_data['summary']['total_taxable_income']]);
            fputcsv($file, ['Total Transactions', $report_data['summary']['total_transactions']]);
            fputcsv($file, ['Cost Basis Method', $report_data['summary']['cost_basis_method']]);
            break;
    }
    
    // Add summary if requested
    if ($include_summaries && !empty($report_data['summary'])) {
        fputcsv($file, []);
        fputcsv($file, ['Summary']);
        foreach ($report_data['summary'] as $key => $value) {
            fputcsv($file, [ucwords(str_replace('_', ' ', $key)), $value]);
        }
    }
    
    fclose($file);
    return $filepath;
}

// Generate PDF report
function generatePDFReport($report_type, $report_data, $tax_year, $include_summaries, $include_breakdowns) {
    $filename = "{$report_type}_{$tax_year}_" . date('Y-m-d_H-i-s') . ".html";
    $filepath = sys_get_temp_dir() . '/' . $filename;
    
    $html = generatePDFHTML($report_type, $report_data, $tax_year, $include_summaries, $include_breakdowns);
    
    file_put_contents($filepath, $html);
    return $filepath;
}

// Generate PDF HTML
function generatePDFHTML($report_type, $report_data, $tax_year, $include_summaries, $include_breakdowns) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . $report_data['name'] . ' - ' . $tax_year . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #0043FF; margin-bottom: 10px; }
        .header p { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .summary { background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .summary h3 { color: #0043FF; margin-top: 0; }
        .number { text-align: right; }
        .positive { color: #28a745; }
        .negative { color: #dc3545; }
        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . $report_data['name'] . '</h1>
        <p>Tax Year: ' . $tax_year . ' | Generated: ' . $report_data['generated_at'] . '</p>
    </div>';
    
    // Add content based on report type
    switch ($report_type) {
        case 'form_8949':
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Date Acquired</th>
                        <th>Date Sold</th>
                        <th>Description</th>
                        <th class="number">Proceeds</th>
                        <th class="number">Cost Basis</th>
                        <th class="number">Gain/Loss</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($report_data['data'] as $row) {
                $gain_loss_class = $row['gain_loss'] >= 0 ? 'positive' : 'negative';
                $html .= '<tr>
                    <td>' . $row['date_acquired'] . '</td>
                    <td>' . $row['date_sold'] . '</td>
                    <td>' . $row['description'] . '</td>
                    <td class="number">$' . number_format($row['proceeds'], 2) . '</td>
                    <td class="number">$' . number_format($row['cost_basis'], 2) . '</td>
                    <td class="number ' . $gain_loss_class . '">$' . number_format($row['gain_loss'], 2) . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        case 'taxable_income':
            foreach ($report_data['data'] as $category => $transactions) {
                if (!empty($transactions)) {
                    $html .= '<h3>' . $category . ' Income</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Asset</th>
                                <th class="number">Amount</th>
                                <th class="number">USD Value</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>';
                    foreach ($transactions as $transaction) {
                        $html .= '<tr>
                            <td>' . date('m/d/Y', strtotime($transaction['date'])) . '</td>
                            <td>' . $transaction['asset'] . '</td>
                            <td class="number">' . number_format($transaction['amount'], 8) . '</td>
                            <td class="number">$' . number_format($transaction['usd_value'], 2) . '</td>
                            <td>' . $transaction['notes'] . '</td>
                        </tr>';
                    }
                    $html .= '</tbody></table>';
                }
            }
            break;
            
        case 'capital_gains':
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Asset</th>
                        <th class="number">Amount Sold</th>
                        <th class="number">Proceeds</th>
                        <th class="number">Cost Basis</th>
                        <th class="number">Gain/Loss</th>
                        <th>Term</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($report_data['data'] as $row) {
                $gain_loss_class = $row['gain_loss'] >= 0 ? 'positive' : 'negative';
                $html .= '<tr>
                    <td>' . date('m/d/Y', strtotime($row['date'])) . '</td>
                    <td>' . $row['asset'] . '</td>
                    <td class="number">' . number_format($row['amount_sold'], 8) . '</td>
                    <td class="number">$' . number_format($row['proceeds'], 2) . '</td>
                    <td class="number">$' . number_format($row['cost_basis'], 2) . '</td>
                    <td class="number ' . $gain_loss_class . '">$' . number_format($row['gain_loss'], 2) . '</td>
                    <td>' . ($row['is_long_term'] ? 'Long-term' : 'Short-term') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        case 'transaction_summary':
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Asset Bought</th>
                        <th class="number">Amount Bought</th>
                        <th>Asset Sold</th>
                        <th class="number">Amount Sold</th>
                        <th class="number">Total Value</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($report_data['data'] as $row) {
                $html .= '<tr>
                    <td>' . date('m/d/Y', strtotime($row['date'])) . '</td>
                    <td>' . $row['type'] . '</td>
                    <td>' . $row['asset_bought'] . '</td>
                    <td class="number">' . number_format($row['amount_bought'], 8) . '</td>
                    <td>' . $row['asset_sold'] . '</td>
                    <td class="number">' . number_format($row['amount_sold'], 8) . '</td>
                    <td class="number">$' . number_format($row['total_value'], 2) . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        case 'asset_performance':
            $html .= '<table>
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th class="number">Total Bought</th>
                        <th class="number">Total Sold</th>
                        <th class="number">Total Cost</th>
                        <th class="number">Total Proceeds</th>
                        <th class="number">Gain/Loss</th>
                        <th class="number">Gain/Loss %</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($report_data['data'] as $row) {
                $gain_loss_class = $row['gain_loss'] >= 0 ? 'positive' : 'negative';
                $html .= '<tr>
                    <td>' . $row['asset'] . '</td>
                    <td class="number">' . number_format($row['total_bought'], 8) . '</td>
                    <td class="number">' . number_format($row['total_sold'], 8) . '</td>
                    <td class="number">$' . number_format($row['total_cost'], 2) . '</td>
                    <td class="number">$' . number_format($row['total_proceeds'], 2) . '</td>
                    <td class="number ' . $gain_loss_class . '">$' . number_format($row['gain_loss'], 2) . '</td>
                    <td class="number ' . $gain_loss_class . '">' . number_format($row['gain_loss_percentage'], 2) . '%</td>
                </tr>';
            }
            $html .= '</tbody></table>';
            break;
            
        case 'tax_summary':
            $html .= '<div class="summary">
                <h3>Tax Summary for ' . $tax_year . '</h3>
                <p><strong>Total Capital Gains:</strong> $' . number_format($report_data['summary']['total_capital_gains'], 2) . '</p>
                <p><strong>Total Taxable Income:</strong> $' . number_format($report_data['summary']['total_taxable_income'], 2) . '</p>
                <p><strong>Total Transactions:</strong> ' . $report_data['summary']['total_transactions'] . '</p>
                <p><strong>Cost Basis Method:</strong> ' . $report_data['summary']['cost_basis_method'] . '</p>
            </div>';
            break;
    }
    
    // Add summary if requested
    if ($include_summaries && !empty($report_data['summary'])) {
        $html .= '<div class="summary">
            <h3>Summary</h3>';
        foreach ($report_data['summary'] as $key => $value) {
            $html .= '<p><strong>' . ucwords(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</p>';
        }
        $html .= '</div>';
    }
    
    $html .= '<div class="footer">
        <p>Generated by CryptoTax on ' . date('F j, Y \a\t g:i A') . '</p>
    </div>
</body>
</html>';
    
    return $html;
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

    /* Report Type Selection */
    .report-types {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid rgba(0, 67, 255, 0.1);
    }

    .report-types h4 {
        color: var(--dark-gray);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .report-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
    }

    .report-type-item {
        background: var(--white);
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .report-type-item:hover {
        border-color: var(--primary-blue);
        background: rgba(0, 67, 255, 0.02);
    }

    .report-type-item.selected {
        border-color: var(--primary-blue);
        background: rgba(0, 67, 255, 0.05);
    }

    .report-type-item input[type="checkbox"] {
        margin-right: 10px;
    }

    .report-type-header {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .report-type-icon {
        font-size: 1.2rem;
        margin-right: 8px;
    }

    .report-type-name {
        font-weight: 600;
        color: var(--dark-gray);
        font-size: 1rem;
    }

    .report-type-description {
        color: var(--medium-gray);
        font-size: 0.9rem;
        line-height: 1.4;
    }

    /* Options Section */
    .options-section {
        background: linear-gradient(135deg, rgba(0, 67, 255, 0.05) 0%, rgba(163, 112, 241, 0.05) 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid rgba(0, 67, 255, 0.1);
    }

    .options-section h4 {
        color: var(--dark-gray);
        margin-bottom: 15px;
        font-size: 1.1rem;
    }

    .options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .option-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px;
        background: var(--white);
        border-radius: 8px;
        border: 1px solid #e0e0e0;
        transition: all 0.2s ease;
    }

    .option-item:hover {
        border-color: var(--primary-blue);
        background: rgba(0, 67, 255, 0.02);
    }

    .option-item input[type="checkbox"] {
        margin: 0;
    }

    .option-item label {
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

    /* Generated Reports */
    .reports-section {
        background: var(--white);
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        margin-bottom: 30px;
        overflow: hidden;
    }

    .reports-header {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--white);
        padding: 20px 25px;
    }

    .reports-header h3 {
        margin: 0;
        color: var(--white);
        font-size: 1.3rem;
    }

    .reports-content {
        padding: 25px;
    }

    .report-item {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        transition: all 0.2s ease;
    }

    .report-item:hover {
        background: var(--white);
        border-color: var(--primary-blue);
        box-shadow: 0 4px 15px rgba(0, 67, 255, 0.1);
    }

    .report-item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }

    .report-item-name {
        font-weight: 600;
        color: var(--primary-blue);
        font-size: 1.1rem;
    }

    .report-item-format {
        background: var(--primary-blue);
        color: var(--white);
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 0.8rem;
        font-weight: bold;
    }

    .report-item-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 10px;
    }

    .report-detail-item {
        text-align: center;
    }

    .report-detail-label {
        font-size: 0.8rem;
        color: var(--medium-gray);
        margin-bottom: 5px;
    }

    .report-detail-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--dark-gray);
    }

    .report-actions {
        display: flex;
        gap: 10px;
        margin-top: 15px;
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

        .report-type-grid {
            grid-template-columns: 1fr;
        }

        .options-grid {
            grid-template-columns: 1fr;
        }

        .report-item-details {
            grid-template-columns: 1fr;
        }

        .report-actions {
            flex-direction: column;
        }
    }
</style>

<div class="container">
    <!-- Header -->
    <div class="page-header">
        <h1>📊 Reports Generator</h1>
        <p>Generate comprehensive CSV and PDF reports for all your tax forms and calculations</p>
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

    <!-- Report Generation Form -->
    <div class="form-container">
        <div class="form-header">
            <h3>⚙️ Generate Reports</h3>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="generate_reports">
            
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
                    <label for="export_format">Export Format</label>
                    <select name="export_format" id="export_format" class="form-control">
                        <option value="csv" <?php echo ($_POST['export_format'] ?? 'csv') == 'csv' ? 'selected' : ''; ?>>CSV (Spreadsheet)</option>
                        <option value="pdf" <?php echo ($_POST['export_format'] ?? '') == 'pdf' ? 'selected' : ''; ?>>PDF (Document)</option>
                    </select>
                    <div class="form-help">Choose the format for your reports</div>
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
                    <div class="form-help">Method for calculating cost basis</div>
                </div>
            </div>

            <!-- Report Type Selection -->
            <div class="report-types">
                <h4>📋 Select Report Types</h4>
                <div class="report-type-grid">
                    <?php foreach ($report_types as $type => $info): ?>
                        <div class="report-type-item">
                            <div class="report-type-header">
                                <input type="checkbox" 
                                       name="report_types[]" 
                                       value="<?php echo $type; ?>" 
                                       id="report_<?php echo $type; ?>"
                                       <?php echo in_array($type, $_POST['report_types'] ?? []) ? 'checked' : ''; ?>>
                                <span class="report-type-icon"><?php echo $info['icon']; ?></span>
                                <span class="report-type-name"><?php echo $info['name']; ?></span>
                            </div>
                            <div class="report-type-description">
                                <?php echo $info['description']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Options -->
            <div class="options-section">
                <h4>⚙️ Report Options</h4>
                <div class="options-grid">
                    <div class="option-item">
                        <input type="checkbox" name="include_summaries" id="include_summaries" 
                               <?php echo isset($_POST['include_summaries']) ? 'checked' : 'checked'; ?>>
                        <label for="include_summaries">Include Summary Data</label>
                    </div>
                    <div class="option-item">
                        <input type="checkbox" name="include_breakdowns" id="include_breakdowns" 
                               <?php echo isset($_POST['include_breakdowns']) ? 'checked' : 'checked'; ?>>
                        <label for="include_breakdowns">Include Detailed Breakdowns</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                📊 Generate Reports
            </button>
        </form>
    </div>

    <?php if (!empty($generated_reports)): ?>
        <!-- Generated Reports -->
        <div class="reports-section">
            <div class="reports-header">
                <h3>📄 Generated Reports</h3>
            </div>
            <div class="reports-content">
                <?php foreach ($generated_reports as $report): ?>
                    <div class="report-item">
                        <div class="report-item-header">
                            <div class="report-item-name"><?php echo htmlspecialchars($report['name']); ?></div>
                            <div class="report-item-format"><?php echo $report['format']; ?></div>
                        </div>
                        <div class="report-item-details">
                            <div class="report-detail-item">
                                <div class="report-detail-label">Generated</div>
                                <div class="report-detail-value"><?php echo date('M j, Y g:i A', strtotime($report['generated_at'])); ?></div>
                            </div>
                            <div class="report-detail-item">
                                <div class="report-detail-label">File Size</div>
                                <div class="report-detail-value"><?php echo formatFileSize($report['file_size']); ?></div>
                            </div>
                            <div class="report-detail-item">
                                <div class="report-detail-label">Type</div>
                                <div class="report-detail-value"><?php echo ucwords(str_replace('_', ' ', $report['type'])); ?></div>
                            </div>
                        </div>
                        <div class="report-actions">
                            <a href="<?php echo $report['file_path']; ?>" download class="btn btn-success">
                                📥 Download
                            </a>
                            <button class="btn btn-secondary" onclick="previewReport('<?php echo $report['file_path']; ?>', '<?php echo $report['format']; ?>')">
                                👁️ Preview
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<script>
    // Select all functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Select all reports
        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn btn-secondary';
        selectAllBtn.textContent = 'Select All Reports';
        selectAllBtn.style.marginBottom = '10px';
        
        const reportTypes = document.querySelector('.report-types');
        if (reportTypes) {
            reportTypes.insertBefore(selectAllBtn, reportTypes.querySelector('.report-type-grid'));
            
            selectAllBtn.addEventListener('click', function() {
                const checkboxes = document.querySelectorAll('input[name="report_types[]"]');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                });
                
                selectAllBtn.textContent = allChecked ? 'Select All Reports' : 'Deselect All Reports';
            });
        }

        // Toggle report type items
        const reportTypeItems = document.querySelectorAll('.report-type-item');
        reportTypeItems.forEach(item => {
            item.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox') {
                    const checkbox = this.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                }
            });
        });

        // Update selected state
        const checkboxes = document.querySelectorAll('input[name="report_types[]"]');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const item = this.closest('.report-type-item');
                if (this.checked) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        });

        // Initialize selected state
        checkboxes.forEach(checkbox => {
            const item = checkbox.closest('.report-type-item');
            if (checkbox.checked) {
                item.classList.add('selected');
            }
        });
    });

    // Preview report functionality
    function previewReport(filePath, format) {
        if (format === 'CSV') {
            // For CSV, open in new tab
            window.open(filePath, '_blank');
        } else {
            // For PDF/HTML, open in new tab
            window.open(filePath, '_blank');
        }
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