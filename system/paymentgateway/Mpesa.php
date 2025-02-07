<?php
/**
 * PHP Mikrotik Billing (https://ibnux.github.io/phpmixbill/)
 * M-Pesa Payment Gateway
 */

// Validate configuration
function mpesa_validate_config() {
    global $config;
    if (empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret'])) {
        r2(U . 'order/package', 'w', Lang::T("M-Pesa payment gateway not configured"));
    }
}

// Show configuration UI
function mpesa_show_config() {
    global $ui, $config;
    $ui->assign('_title', 'M-Pesa - Payment Gateway - ' . $config['CompanyName']);
    $ui->display('mpesa.tpl');
}

// Save configuration
function mpesa_save_config() {
    global $admin, $_L;
    $keys = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_shortcode', 'mpesa_passkey'];
    
    foreach ($keys as $key) {
        $value = _post($key);
        $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
        if ($d) {
            $d->value = $value;
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $key;
            $d->value = $value;
        }
        $d->save();
    }
    
    _log('[' . $admin['username'] . ']: M-Pesa ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', $_L['Settings_Saved_Successfully']);
}

// Create M-Pesa transaction
function mpesa_create_transaction($trx, $user) {
    // Capture Mikrotik session parameters
    $trx->mac = $_REQUEST['mac'] ?? '';
    $trx->ip = $_REQUEST['ip'] ?? '';
    $trx->username = $_REQUEST['username'] ?? '';
    global $config;
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);
    
    $payload = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $trx['price'],
        'PartyA' => $user['phonenumber'],
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $user['phonenumber'],
        'CallBackURL' => U . 'ipn/mpesa',
        'AccountReference' => $trx['id'],
        'TransactionDesc' => $trx['plan_name']
    ];

    $response = Http::postJsonData(
        'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
        $payload,
        [
            'Authorization: Bearer ' . get_mpesa_token(),
            'Content-Type: application/json'
        ]
    );

    $result = json_decode($response, true);
    if (isset($result['errorCode'])) {
        r2(U . 'order/package', 'e', Lang::T("Payment initiation failed"));
    }

    $trx->gateway_trx_id = $result['CheckoutRequestID'];
    $trx->pg_request = $response;
    $trx->save();
    
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment initiated successfully"));
}

// Get payment status
function mpesa_get_status($trx, $user) {
    global $config;
    
    $response = Http::postJsonData(
        'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query',
        [
            'BusinessShortCode' => $config['mpesa_shortcode'],
            'Password' => base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . date('YmdHis')),
            'Timestamp' => date('YmdHis'),
            'CheckoutRequestID' => $trx['gateway_trx_id']
        ],
        [
            'Authorization: Bearer ' . get_mpesa_token(),
            'Content-Type: application/json'
        ]
    );

    $result = json_decode($response, true);
    if ($result['ResultCode'] == '0') {
        Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], 'M-Pesa', 'STK Push');
        // Mikrotik Hotspot Authorization
        if (!empty($trx['mac']) && !empty($trx['ip'])) {
            $expiry = time() + ($trx['plan_validity'] * 86400);
            shell_exec("radiusctl add {$trx['mac']} {$trx['ip']} {$trx['username']} {$expiry}");
        }
        $trx->status = 2;
        $trx->pg_paid_response = $response;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment successful"));
    } else {
        r2(U . "order/view/" . $trx['id'], 'e', $result['ResultDesc']);
    }
}

// Handle M-Pesa callback
function mpesa_payment_notification() {
    $data = json_decode(file_get_contents('php://input'), true);
    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $data['Body']['stkCallback']['CheckoutRequestID'])
        ->find_one();

    if ($trx && $data['Body']['stkCallback']['ResultCode'] == 0) {
        $trx->status = 2;
        $trx->pg_paid_response = json_encode($data);
        $trx->save();
        Package::rechargeUser($trx['user_id'], $trx['routers'], $trx['plan_id'], 'M-Pesa', 'STK Push');
        // Mikrotik Hotspot Authorization
        if (!empty($trx['mac']) && !empty($trx['ip'])) {
            $expiry = time() + ($trx['plan_validity'] * 86400);
            shell_exec("radiusctl add {$trx['mac']} {$trx['ip']} {$trx['username']} {$expiry}");
        }
    }
    
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
}

// Helper function to get M-Pesa token
function get_mpesa_token() {
    global $config;
    $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
    
    $response = Http::postJsonData(
        'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
        [],
        ["Authorization: Basic $credentials"]
    );
    
    $result = json_decode($response, true);
    return $result['access_token'];
}