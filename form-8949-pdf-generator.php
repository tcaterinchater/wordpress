<?php
// =============================================================================
// TEMPLATE NAME: Form 8949 PDF Generator
// -----------------------------------------------------------------------------
// Generates PDF version of Form 8949 using TCPDF library
// =============================================================================

// Include TCPDF library (you'll need to install this via Composer)
// require_once('vendor/autoload.php');

// For demonstration, we'll create a simple PDF generator
// In production, use TCPDF, FPDF, or similar library

class Form8949PDFGenerator {
    
    public function generatePDF($form_data, $summary, $tax_year, $cost_basis_method) {
        // This is a simplified PDF generation
        // In production, use a proper PDF library like TCPDF
        
        $pdf_content = $this->generatePDFContent($form_data, $summary, $tax_year, $cost_basis_method);
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="form8949_' . $tax_year . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // In production, this would generate actual PDF binary data
        echo $pdf_content;
    }
    
    private function generatePDFContent($form_data, $summary, $tax_year, $cost_basis_method) {
        $html = $this->generateHTMLForPDF($form_data, $summary, $tax_year, $cost_basis_method);
        
        // For demonstration, return HTML that can be printed as PDF
        // In production, use TCPDF to convert HTML to PDF
        return $html;
    }
    
    private function generateHTMLForPDF($form_data, $summary, $tax_year, $cost_basis_method) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Form 8949 - Tax Year <?php echo $tax_year; ?></title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10px;
                    line-height: 1.2;
                    margin: 0;
                    padding: 20px;
                }
                
                .form-header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 10px;
                }
                
                .form-title {
                    font-size: 16px;
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                
                .form-subtitle {
                    font-size: 12px;
                    margin-bottom: 10px;
                }
                
                .tax-year {
                    font-size: 14px;
                    font-weight: bold;
                }
                
                .section-header {
                    background-color: #f0f0f0;
                    padding: 8px;
                    font-weight: bold;
                    margin: 20px 0 10px 0;
                    border: 1px solid #000;
                }
                
                .form-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                
                .form-table th,
                .form-table td {
                    border: 1px solid #000;
                    padding: 4px;
                    text-align: left;
                    font-size: 9px;
                }
                
                .form-table th {
                    background-color: #f0f0f0;
                    font-weight: bold;
                    text-align: center;
                }
                
                .form-table .number {
                    text-align: right;
                }
                
                .totals-section {
                    margin-top: 20px;
                    padding: 10px;
                    border: 1px solid #000;
                    background-color: #f9f9f9;
                }
                
                .totals-grid {
                    display: table;
                    width: 100%;
                }
                
                .total-item {
                    display: table-cell;
                    text-align: center;
                    padding: 5px;
                }
                
                .total-label {
                    font-size: 9px;
                    font-weight: bold;
                }
                
                .total-value {
                    font-size: 11px;
                    font-weight: bold;
                }
                
                .method-info {
                    background-color: #e6f3ff;
                    padding: 10px;
                    margin: 20px 0;
                    border: 1px solid #0066cc;
                }
                
                .page-break {
                    page-break-before: always;
                }
                
                @media print {
                    body { margin: 0; }
                    .page-break { page-break-before: always; }
                }
            </style>
        </head>
        <body>
            <div class="form-header">
                <div class="form-title">Form 8949</div>
                <div class="form-subtitle">Sales and Other Dispositions of Capital Assets</div>
                <div class="tax-year">Tax Year: <?php echo $tax_year; ?></div>
            </div>
            
            <div class="method-info">
                <strong>Cost Basis Method:</strong> <?php echo $cost_basis_method; ?><br>
                <strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?>
            </div>
            
            <?php if (!empty($summary['short_term'])): ?>
                <div class="section-header">
                    Part I - Short-Term Capital Gains and Losses (Assets held 1 year or less)
                </div>
                
                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Date Acquired</th>
                            <th style="width: 12%;">Date Sold</th>
                            <th style="width: 25%;">Description</th>
                            <th style="width: 12%;">Proceeds</th>
                            <th style="width: 12%;">Cost Basis</th>
                            <th style="width: 8%;">Code</th>
                            <th style="width: 8%;">Adjustment</th>
                            <th style="width: 12%;">Gain/Loss</th>
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
                            <td class="number">$<?php echo number_format($transaction['gain_loss'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
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
                            <div class="total-value">$<?php echo number_format($summary['total_short_gain_loss'], 2); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($summary['long_term'])): ?>
                <div class="page-break"></div>
                
                <div class="section-header">
                    Part II - Long-Term Capital Gains and Losses (Assets held more than 1 year)
                </div>
                
                <table class="form-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Date Acquired</th>
                            <th style="width: 12%;">Date Sold</th>
                            <th style="width: 25%;">Description</th>
                            <th style="width: 12%;">Proceeds</th>
                            <th style="width: 12%;">Cost Basis</th>
                            <th style="width: 8%;">Code</th>
                            <th style="width: 8%;">Adjustment</th>
                            <th style="width: 12%;">Gain/Loss</th>
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
                            <td class="number">$<?php echo number_format($transaction['gain_loss'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
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
                            <div class="total-value">$<?php echo number_format($summary['total_long_gain_loss'], 2); ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="page-break"></div>
            
            <div class="section-header">
                Summary
            </div>
            
            <div class="totals-section">
                <div class="totals-grid">
                    <div class="total-item">
                        <div class="total-label">Total Short Term Proceeds</div>
                        <div class="total-value">$<?php echo number_format($summary['total_short_proceeds'], 2); ?></div>
                    </div>
                    <div class="total-item">
                        <div class="total-label">Total Short Term Cost Basis</div>
                        <div class="total-value">$<?php echo number_format($summary['total_short_cost'], 2); ?></div>
                    </div>
                    <div class="total-item">
                        <div class="total-label">Short Term Gain/Loss</div>
                        <div class="total-value">$<?php echo number_format($summary['total_short_gain_loss'], 2); ?></div>
                    </div>
                </div>
                
                <div class="totals-grid" style="margin-top: 10px;">
                    <div class="total-item">
                        <div class="total-label">Total Long Term Proceeds</div>
                        <div class="total-value">$<?php echo number_format($summary['total_long_proceeds'], 2); ?></div>
                    </div>
                    <div class="total-item">
                        <div class="total-label">Total Long Term Cost Basis</div>
                        <div class="total-value">$<?php echo number_format($summary['total_long_cost'], 2); ?></div>
                    </div>
                    <div class="total-item">
                        <div class="total-label">Long Term Gain/Loss</div>
                        <div class="total-value">$<?php echo number_format($summary['total_long_gain_loss'], 2); ?></div>
                    </div>
                </div>
                
                <div class="totals-grid" style="margin-top: 15px; border-top: 2px solid #000; padding-top: 10px;">
                    <div class="total-item">
                        <div class="total-label" style="font-size: 11px;">TOTAL GAIN/LOSS</div>
                        <div class="total-value" style="font-size: 14px;">$<?php echo number_format($summary['total_gain_loss'], 2); ?></div>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 30px; font-size: 8px; color: #666;">
                <p><strong>Disclaimer:</strong> This form is generated for informational purposes only. 
                Please consult with a tax professional before filing with the IRS. 
                The calculations are based on the selected cost basis method (<?php echo $cost_basis_method; ?>) 
                and may need adjustment based on your specific tax situation.</p>
                
                <p><strong>Generated by:</strong> CryptoTax Form 8949 Generator<br>
                <strong>Date:</strong> <?php echo date('F j, Y g:i A'); ?><br>
                <strong>Tax Year:</strong> <?php echo $tax_year; ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

// Handle PDF generation request
if (isset($_GET['action']) && $_GET['action'] === 'generate_pdf') {
    // This would typically come from a session or database
    // For demo purposes, we'll show how to use the generator
    
    $generator = new Form8949PDFGenerator();
    
    // Example data (in production, this would come from your form processing)
    $example_data = [
        [
            'date_acquired' => 'Various',
            'date_sold' => '03/15/2024',
            'description' => 'Sale of 0.5 BTC',
            'proceeds' => 25000.00,
            'cost_basis' => 20000.00,
            'adjustment_code' => '',
            'adjustment_amount' => 0,
            'gain_loss' => 5000.00,
            'asset' => 'BTC',
            'amount' => 0.5,
            'is_short_term' => true
        ]
    ];
    
    $example_summary = [
        'short_term' => $example_data,
        'long_term' => [],
        'total_short_proceeds' => 25000.00,
        'total_short_cost' => 20000.00,
        'total_short_gain_loss' => 5000.00,
        'total_long_proceeds' => 0,
        'total_long_cost' => 0,
        'total_long_gain_loss' => 0,
        'total_gain_loss' => 5000.00
    ];
    
    $generator->generatePDF($example_data, $example_summary, '2024', 'FIFO');
    exit;
}
?>