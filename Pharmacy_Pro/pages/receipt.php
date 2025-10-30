<?php
    /**
     * Receipt Page
     * Displays and prints order receipt with discount
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

    // Get order items with item_discount
    $itemsQuery = "SELECT oi.*, p.name as product_name, p.barcode 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?";
    $items = executeQuery($itemsQuery, 'i', [$orderId]);

    // Calculate original subtotal and item discounts
    $originalSubtotal = 0;
    $totalItemDiscounts = 0;
    $subtotalAfterItemDiscounts = 0;

    foreach ($items as &$item) {
        $lineTotal = $item['unit_price'] * $item['quantity'];
        $originalSubtotal += $lineTotal;
        
        $itemDiscountPercent = isset($item['item_discount']) ? $item['item_discount'] : 0;
        $item['item_discount_amount'] = $lineTotal * ($itemDiscountPercent / 100);
        $item['line_total_after_discount'] = $lineTotal - $item['item_discount_amount'];
        
        $totalItemDiscounts += $item['item_discount_amount'];
        $subtotalAfterItemDiscounts += $item['line_total_after_discount'];
    }
    unset($item); // break the reference

    // Get discount and tax rates
    $discountRate = getSetting('discount_rate', 0) * 100;
    $taxRate = getSetting('tax_rate', 0.10) * 100;

    // Recalculate correct discount and tax amounts for display
    // Cart discount should be applied on subtotal AFTER item discounts
    $calculatedDiscountAmount = $subtotalAfterItemDiscounts * (getSetting('discount_rate', 0));
    $afterCartDiscount = $subtotalAfterItemDiscounts - $calculatedDiscountAmount;

    // Tax should be calculated on amount AFTER cart discount
    $calculatedTaxAmount = $afterCartDiscount * getSetting('tax_rate', 0.10);
    // Recalculate grand total
    $calculatedGrandTotal = $afterCartDiscount + $calculatedTaxAmount;

    // Calculate total savings
    $totalSavings = $totalItemDiscounts + $calculatedDiscountAmount;
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - <?php echo $order['order_number']; ?></title>
        
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        
        <style>
            body {
                font-family: 'Inter', Arial, sans-serif;
                background-color: #f5f5f5;
            }
            
            .receipt {
                width: 380px;
                margin: 0 auto;
                padding: 25px;
                border: 2px solid #000;
                background: white;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
                font-size: 12px;
                border: 1px solid #000;
                padding: 10px;
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
                margin-bottom: 15px;
                font-size: 11px;
            }
            
            .receipt-table th {
                padding: 6px 3px;
                text-align: left;
                border-bottom: 2px solid #000;
                font-weight: 600;
                background: #000;
                color: white;
                font-size: 10px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .receipt-table td {
                padding: 5px 3px;
                border-bottom: 1px dotted #999;
                vertical-align: top;
            }
            
            .receipt-table .qty {
                text-align: center;
                width: 35px;
                font-weight: 600;
            }
            
            .receipt-table .price,
            .receipt-table .total {
                text-align: right;
                width: 55px;
                font-family: 'Courier New', monospace;
                font-weight: 600;
            }
            .receipt-table .discount {
                text-align: center;
                width: 40px;
                font-size: 10px;
                font-weight: 600;
            }
            
            .product-name {
                font-weight: 500;
                color: #000;
                line-height: 1.2;
            }
            
            .product-barcode {
                font-size: 9px;
                color: #666;
                font-family: 'Courier New', monospace;
                margin-top: 1px;
            }
            
            .receipt-totals {
                border-top: 3px double #000;
                padding-top: 10px;
                font-size: 12px;
                background: #f9f9f9;
                padding: 10px;
                border: 1px solid #000;
                margin-bottom: 15px;
            }
            
            .total-row {
                display: flex;
                justify-content: space-between;
                padding: 3px 0;
                font-family: 'Courier New', monospace;
                font-size: 11px;
            }
            
            .total-row.discount {
                color: #d9534f;
                font-weight: 600;
            }
            
            .total-row.after-discount {
                border-top: 1px dotted #999;
                padding-top: 5px;
                margin-top: 3px;
            }
            
            .total-row.grand-total {
                font-size: 14px;
                font-weight: 700;
                border-top: 2px solid #000;
                padding-top: 8px;
                margin-top: 8px;
                background: #000;
                color: white;
                padding: 8px 10px;
                margin: 8px -10px -10px -10px;
            }
            
            .receipt-footer {
                text-align: center;
                margin-top: 15px;
                font-size: 10px;
                border-top: 3px double #000;
                padding-top: 12px;
            }
            
            .thank-you {
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 6px;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            
            .footer-text {
                line-height: 1.3;
                color: #333;
                margin-bottom: 8px;
            }
            .timestamp {
                font-family: 'Courier New', monospace;
                font-size: 9px;
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 6px;
                margin-top: 8px;
            }
            
            .no-print {
                text-align: center;
                margin: 20px 0;
            }
            
            .order-summary {
                background: white;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                    background-color: white;
                }
                
                .receipt {
                    border: 2px solid #000;
                    box-shadow: none;
                    margin: 0;
                    padding: 20px;
                    width: 100%;
                }
                
                .no-print {
                    display: none;
                }
                
                .container-fluid {
                    max-width: 100%;
                }
            }
        </style>
    </head>
    <body>
       <div class="container-fluid py-3">
    <!-- Top Buttons -->
    <div class="no-print">
        <div class="text-center mb-4">
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

    <div class="receipt shadow-sm p-4 rounded" style="max-width: 480px; margin: auto; background: #fff;">
        <!-- Header -->
        <div class="receipt-header text-center mb-3">
            <h2 class="fw-bold mb-1">Pharmacy System</h2>
            <div class="tagline text-muted mb-2">Your Trusted Shopping Partner</div>
            <div class="address small">
                123 Business Street<br>
                City, State 12345<br>
                Phone: (555) 123-4567
            </div>
        </div>

        <!-- Order Information -->
        <div class="receipt-info my-3 border-top border-bottom py-2">
            <div class="receipt-info-row"><strong>Receipt #:</strong> <?php echo $order['order_number']; ?></div>
            <div class="receipt-info-row"><strong>Date:</strong> <?php echo formatDate($order['order_date'], 'M d, Y H:i'); ?></div>
            <div class="receipt-info-row"><strong>Cashier:</strong> <?php echo sanitizeInput($order['cashier_name']); ?></div>
            <div class="receipt-info-row"><strong>Payment:</strong> <?php echo ucfirst($order['payment_method']); ?></div>
        </div>

        <!-- Items Table -->
        <table class="table table-sm receipt-table mb-3">
            <thead class="table-light">
                <tr>
                    <th>Item</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Price</th>
                    <th class="text-center">Disc%</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-bold"><?php echo sanitizeInput($item['product_name']); ?></div>
                            <small class="text-muted"><?php echo sanitizeInput($item['barcode']); ?></small>
                        </td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-end">Rs <?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-center">
                            <?php echo ($item['item_discount'] > 0) ? number_format($item['item_discount'], 1).'%' : '-'; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($item['item_discount'] > 0): ?>
                                <small class="text-muted text-decoration-line-through d-block">
                                    Rs <?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?>
                                </small>
                                <span class="text-success fw-bold">Rs <?php echo number_format($item['line_total_after_discount'], 2); ?></span>
                            <?php else: ?>
                                Rs <?php echo number_format($item['total_price'], 2); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Section -->
        <div class="receipt-totals mt-3">
        <!-- 1. Original subtotal -->
        <div class="total-row d-flex justify-content-between">
            <span>Original Subtotal:</span>
            <span>Rs <?php echo number_format($originalSubtotal, 2); ?></span>
        </div>

        <!-- 2. Item discounts -->
        <?php if ($totalItemDiscounts > 0): ?>
        <div class="total-row d-flex justify-content-between text-danger">
            <span>Item Discounts:</span>
            <span>-Rs <?php echo number_format($totalItemDiscounts, 2); ?></span>
        </div>
        <?php endif; ?>

        <!-- 3. Subtotal after item discounts -->
        <div class="total-row d-flex justify-content-between fw-semibold border-bottom pb-1">
            <span>Subtotal After Item Discounts:</span>
            <span>Rs <?php echo number_format($subtotalAfterItemDiscounts, 2); ?></span>
        </div>

        <!-- 4. Cart discount -->
        <?php if ($calculatedDiscountAmount > 0): ?>
        <div class="total-row d-flex justify-content-between text-danger mt-1">
            <span>Cart Discount (<?php echo number_format($discountRate, 1); ?>%):</span>
            <span>-Rs <?php echo number_format($calculatedDiscountAmount, 2); ?></span>
        </div>

        <!-- 5. After cart discount -->
        <div class="total-row d-flex justify-content-between fw-semibold border-bottom pb-1">
            <span>After Cart Discount:</span>
            <span>Rs <?php echo number_format($afterCartDiscount, 2); ?></span>
        </div>
        <?php endif; ?>

        <!-- 6. Tax -->
        <div class="total-row d-flex justify-content-between mt-1">
            <span>Tax (<?php echo number_format($taxRate, 1); ?>%):</span>
            <span>Rs <?php echo number_format($calculatedTaxAmount, 2); ?></span>
        </div>

        <!-- 8. Final total -->
        <div class="total-row d-flex justify-content-between fw-bold border-top mt-2 pt-2">
            <span>TOTAL:</span>
            <span>Rs <?php echo number_format($calculatedGrandTotal, 2); ?></span>
        </div>
    </div>

        <!-- Footer -->
        <div class="receipt-footer text-center mt-4">
            <div class="thank-you fw-bold mb-2">Thank You for Shopping!</div>

            <?php if ($totalItemDiscounts > 0 &&  $calculatedDiscountAmount> 0): ?>
                (Item: Rs <?php echo number_format($totalItemDiscounts, 2); ?> + Cart: Rs <?php echo number_format( $calculatedDiscountAmount, 2); ?>)
                <?php endif; ?>

                <?php if ($totalSavings > 0): ?>
                    <div class="savings-badge mx-auto" 
                        style="display:inline-block;background:#0a7d30;color:#e6f9e8;
                            border-radius:8px;padding:6px 12px;font-size:0.9rem;">
                        YOU SAVED Rs <?php echo number_format($totalSavings, 2); ?>!<br>
                        <small>
                            <?php if ($totalItemDiscounts > 0 &&  $calculatedDiscountAmount > 0): ?>
                                (Item: Rs <?php echo number_format($totalItemDiscounts, 2); ?> + Cart: Rs <?php echo sprintf('%.2f',  $calculatedDiscountAmount); ?>)
                            <?php elseif ($totalItemDiscounts > 0): ?>
                                (Item: Rs <?php echo number_format($totalItemDiscounts, 2); ?>)
                            <?php else: ?>
                                (Cart: Rs <?php echo sprintf('%.2f',  $calculatedDiscountAmount); ?>)
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>

            <div class="footer-text small mt-3 text-muted">
                Please come again<br>
                Return Policy: 30 days with receipt<br>
                For support: support@pharmacy.com<br>
                <b>POWERED BY INVENTRA SOLUTIONS</b>
            </div>
            <div class="timestamp small mt-2"><?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
    </div>

    <!-- Order Summary Card -->
    <div class="no-print mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card order-summary shadow-sm">
                    <div class="card-header bg-primary text-white text-center">
                        <h5 class="mb-0">Order Summary</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="row mb-3">
                            <div class="col-6 border-end">
                                <strong>Order Number</strong><br>
                                <span class="text-muted"><?php echo $order['order_number']; ?></span>
                            </div>
                            <div class="col-6">
                                <strong>Total Amount</strong><br>
                                <span class="text-success fw-bold"><?php echo formatCurrency($calculatedGrandTotal); ?></span>
                            </div>
                        </div>

                        <?php if ($totalSavings > 0): ?>
                            <div class="alert alert-success py-2 small">
                                <i class="fas fa-tag"></i> Discounts Applied<br>
                            <?php if ($totalItemDiscounts > 0 && $calculatedDiscountAmount > 0): ?>
                                    Item: <?php echo formatCurrency($totalItemDiscounts); ?> | Cart: <?php echo formatCurrency($calculatedDiscountAmount); ?><br>
                                    <b>Total Savings: <?php echo formatCurrency($totalSavings); ?></b>
                                <?php elseif ($totalItemDiscounts > 0): ?>
                                    Item Discounts: <?php echo formatCurrency($totalItemDiscounts); ?>
                                <?php else: ?>
                                Cart Discount: <?php echo formatCurrency($calculatedDiscountAmount); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-6 border-end">
                                <strong>Items</strong><br>
                                <span class="text-muted"><?php echo count($items); ?> products</span>
                            </div>
                            <div class="col-6">
                                <strong>Payment</strong><br>
                                <span class="text-muted"><?php echo ucfirst($order['payment_method']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <script>
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        </script>
    </body>
</html>