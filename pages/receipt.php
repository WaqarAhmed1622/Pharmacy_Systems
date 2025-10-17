<?php
    /**
     * Receipt Page
     * Displays and prints order receipt
     */

    require_once '../config/database.php';
    require_once '../includes/auth.php';
    require_once '../includes/functions.php';

    requireLogin();

    $orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

    if (!$orderId) {
        header('Location: pos.php');
        exit;
    }

    // Get order details
    $orderQuery = "SELECT o.*, u.full_name as cashier_name 
                FROM orders o 
                JOIN users u ON o.cashier_id = u.id 
                WHERE o.id = ?";
    $orderResult = executeQuery($orderQuery, 'i', [$orderId]);

    if (empty($orderResult)) {
        header('Location: pos.php');
        exit;
    }

    $order = $orderResult[0];

    // Get order items
    $itemsQuery = "SELECT oi.*, p.name as product_name, p.barcode 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
    $items = executeQuery($itemsQuery, 'i', [$orderId]);
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - <?php echo $order['order_number']; ?></title>
        
        <!-- Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <style>
            body {
                font-family: 'Inter', Arial, sans-serif;
            }
            
            .receipt {
                width: 350px;
                margin: 0 auto;
                padding: 25px;
                border: 2px solid #000;
                background: white;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            .receipt-header {
                text-align: center;
                border-bottom: 3px double #000;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            
            .receipt-header h2 {
                margin: 0 0 8px 0;
                font-size: 24px;
                font-weight: 700;
                letter-spacing: 2px;
            }
            
            .receipt-header .tagline {
                font-size: 11px;
                font-style: italic;
                margin-bottom: 8px;
                color: #333;
            }
            
            .receipt-header .address {
                font-size: 12px;
                line-height: 1.4;
                color: #444;
            }
            
            .receipt-info {
                margin-bottom: 20px;
                font-size: 13px;
                border: 1px solid #000;
                padding: 12px;
                background: #f9f9f9;
            }
            
            .receipt-info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 4px;
                padding: 2px 0;
            }
            
            .receipt-info-row:last-child {
                margin-bottom: 0;
            }
            
            .receipt-info-label {
                font-weight: 500;
                color: #333;
            }
            
            .receipt-info-value {
                font-weight: 600;
                color: #000;
            }
            
            .receipt-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 12px;
            }
            
            .receipt-table th {
                padding: 8px 4px;
                text-align: left;
                border-bottom: 2px solid #000;
                font-weight: 600;
                background: #000;
                color: white;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .receipt-table td {
                padding: 6px 4px;
                border-bottom: 1px dotted #999;
                vertical-align: top;
            }
            
            .receipt-table .qty {
                text-align: center;
                width: 40px;
                font-weight: 600;
            }
            
            .receipt-table .price,
            .receipt-table .total {
                text-align: right;
                width: 60px;
                font-family: 'Courier New', monospace;
                font-weight: 600;
            }
            
            .product-name {
                font-weight: 500;
                color: #000;
                line-height: 1.3;
            }
            
            .product-barcode {
                font-size: 10px;
                color: #666;
                font-family: 'Courier New', monospace;
                margin-top: 2px;
            }
            
            .receipt-totals {
                border-top: 3px double #000;
                padding-top: 12px;
                font-size: 13px;
                background: #f9f9f9;
                padding: 12px;
                border: 1px solid #000;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
                font-family: 'Courier New', monospace;
            }
            
            .total-row.grand-total {
                font-size: 16px;
                font-weight: 700;
                border-top: 2px solid #000;
                padding-top: 8px;
                margin-top: 8px;
                background: #000;
                color: white;
                padding: 8px 12px;
                margin: 8px -12px -12px -12px;
            }
            
            .receipt-footer {
                text-align: center;
                margin-top: 20px;
                font-size: 11px;
                border-top: 3px double #000;
                padding-top: 15px;
            }
            
            .thank-you {
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .footer-text {
                line-height: 1.4;
                color: #333;
                margin-bottom: 12px;
            }
            
            .timestamp {
                font-family: 'Courier New', monospace;
                font-size: 10px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 8px;
                margin-top: 12px;
            }
            
            .no-print {
                text-align: center;
                margin: 20px 0;
            }
            
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                }
                
                .receipt {
                    border: 2px solid #000;
                    box-shadow: none;
                    margin: 0;
                    padding: 20px;
                }
                
                .no-print {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <div class="no-print">
                <div class="text-center mb-3">
                    <button onclick="window.print()" class="btn btn-primary me-2">
                        <i class="fas fa-print"></i> Print Receipt
                    </button>
                    <a href="pos.php" class="btn btn-success me-2">
                        <i class="fas fa-plus"></i> New Sale
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
            </div>
            
            <div class="receipt">
                <!-- Header -->
                <div class="receipt-header">
                    <h2>Pharmacy System</h2>
                    <div class="tagline">Your Trusted Shopping Partner</div>
                    <div class="address">
                        123 Business Street<br>
                        City, State 12345<br>
                        Phone: (555) 123-4567
                    </div>
                </div>
                
                <!-- Order Information -->
                <div class="receipt-info">
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Receipt #:</span>
                        <span class="receipt-info-value"><?php echo $order['order_number']; ?></span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Date:</span>
                        <span class="receipt-info-value"><?php echo formatDate($order['order_date'], 'M d, Y H:i'); ?></span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Cashier:</span>
                        <span class="receipt-info-value"><?php echo sanitizeInput($order['cashier_name']); ?></span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Payment:</span>
                        <span class="receipt-info-value"><?php echo ucfirst($order['payment_method']); ?></span>
                    </div>
                </div>
                
                <!-- Items Table -->
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="qty">Qty</th>
                            <th class="price">Price</th>
                            <th class="total">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-name"><?php echo sanitizeInput($item['product_name']); ?></div>
                                    <div class="product-barcode"><?php echo sanitizeInput($item['barcode']); ?></div>
                                </td>
                                <td class="qty"><?php echo $item['quantity']; ?></td>
                                <td class="price">Rs <?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="total">Rs <?php echo number_format($item['total_price'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Totals -->
                <div class="receipt-totals">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span>Rs <?php echo number_format($order['subtotal'], 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Tax (10%):</span>
                        <span>Rs <?php echo number_format($order['tax_amount'], 2); ?></span>
                    </div>
                    <div class="total-row grand-total">
                        <span>TOTAL:</span>
                        <span>Rs <?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="receipt-footer">
                    <div class="thank-you">Thank You for Shopping!</div>
                    <div class="footer-text">
                        Please come again<br>
                        Return Policy: 30 days with receipt<br>
                        For support: support@martsystem.com<br>
                        <b>POWERED BY INVENTRA SOLUIONS</b>
                    </div>
                    <div class="timestamp"><?php echo date('Y-m-d H:i:s'); ?></div>
                </div>
            </div>
            
            <div class="no-print text-center mt-4">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Order Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Order Number:</strong><br>
                                        <?php echo $order['order_number']; ?>
                                    </div>
                                    <div class="col-6">
                                        <strong>Total Amount:</strong><br>
                                        <span class="text-success h5"><?php echo formatCurrency($order['total_amount']); ?></span>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <strong>Items:</strong><br>
                                        <?php echo count($items); ?> products
                                    </div>
                                    <div class="col-6">
                                        <strong>Payment Method:</strong><br>
                                        <?php echo ucfirst($order['payment_method']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Simple JavaScript (Offline) -->
        <script>
            // Auto-print on load (optional)
            // window.onload = function() { window.print(); }
            
            // Keyboard shortcut for printing
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        </script>
    </body>
    </html>