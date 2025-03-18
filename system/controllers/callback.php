<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

if (!defined('_VALID_ACCESS')) {
    die('Direct access to this location is not allowed.');
}

$routes = explode('/', $req);
$gateway = $routes[1] ?? null;

switch ($gateway) {
    case 'mpesa':
        try {
            include $PAYMENTGATEWAY_PATH . DIRECTORY_SEPARATOR . 'mpesa.php';
            mpesa_payment_notification();
        } catch (Exception $e) {
            Log::put('MPESA', 'Callback Error: ' . $e->getMessage(), '', json_encode([
                'trace' => $e->getTraceAsString()
            ]));
            http_response_code(500);
            die('Error processing callback');
        }
        break;
        
    default:
        http_response_code(404);
        die('Invalid payment gateway');
}
