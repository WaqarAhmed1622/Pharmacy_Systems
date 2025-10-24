<?php
session_start();

if (!isset($_SESSION['order_settings'])) {
    $_SESSION['order_settings'] = [
        'order_type' => 'Takeaway',
        'delivery_charge' => 0
    ];
}

if (isset($_POST['order_type'])) {
    $_SESSION['order_settings']['order_type'] = $_POST['order_type'];
}

if (isset($_POST['delivery_charge'])) {
    $_SESSION['order_settings']['delivery_charge'] = (float)$_POST['delivery_charge'];
}

http_response_code(200);
echo json_encode(['status' => 'success', 'settings' => $_SESSION['order_settings']]);
