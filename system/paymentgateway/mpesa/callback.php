<?php
/**
 * M-Pesa Payment Gateway for PHPNuxBill
 * @author Spidmax Technologies
 * @copyright 2024
 */

require_once '../../config.php';
require_once '../../init.php';

$raw_post_data = file_get_contents('php://input');
$data = json_decode($raw_post_data, true);

_log('M-Pesa Raw Callback: ' . $raw_post_data);

if ($data) {
    try {
        require_once 'gateway.php';
        $mpesa = new Mpesa();
        $result = $mpesa->handleCallback($data);
        
        if ($result) {
            http_response_code(200);
            die(json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']));
        }
    } catch (Exception $e) {
        _log('M-Pesa Callback Error: ' . $e->getMessage());
    }
}

http_response_code(400);
die(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Failed']));