<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway M-Pesa
 **/

// Constants
define('MPESA_MIN_TIMEOUT', 30);
define('MPESA_MAX_TIMEOUT', 300);
define('MPESA_DEFAULT_TIMEOUT', 120);
define('MPESA_STATUS_PENDING', 1);
define('MPESA_STATUS_PAID', 2);
define('MPESA_STATUS_FAILED', 3);

// Required M-Pesa configuration keys
const MPESA_REQUIRED_CONFIGS = [
    'mpesa_consumer_key',
    'mpesa_consumer_secret',
    'mpesa_shortcode',
    'mpesa_passkey'
];

/**
 * Validate M-Pesa configuration
 */
function mpesa_validate_config() {
    global $config;
    
    // Check required configs
    foreach (MPESA_REQUIRED_CONFIGS as $key) {
        if (empty($config[$key])) {
            r2(U . 'order/package', 'e', "Please configure $key in Settings");
        }
    }

    // Ensure pending timeout has a valid value
    if (empty($config['mpesa_pending_timeout'])) {
        $config['mpesa_pending_timeout'] = MPESA_DEFAULT_TIMEOUT;
    } else {
        $timeout = intval($config['mpesa_pending_timeout']);
        $config['mpesa_pending_timeout'] = max(MPESA_MIN_TIMEOUT, min(MPESA_MAX_TIMEOUT, $timeout));
    }
}

/**
 * Display M-Pesa configuration page
 */
function mpesa_show_config() {
    global $ui;
    $ui->assign('_title', 'M-Pesa - Payment Gateway');
    $ui->display('mpesa.tpl');
}

/**
 * Save M-Pesa configuration
 */
function mpesa_save_config() {
    global $admin;
    
    $settings = [
        'mpesa_consumer_key' => _post('mpesa_consumer_key'),
        'mpesa_consumer_secret' => _post('mpesa_consumer_secret'),
        'mpesa_shortcode' => _post('mpesa_shortcode'),
        'mpesa_passkey' => _post('mpesa_passkey'),
        'mpesa_pending_timeout' => max(MPESA_MIN_TIMEOUT, min(MPESA_MAX_TIMEOUT, intval(_post('mpesa_pending_timeout'))))
    ];

    foreach ($settings as $key => $value) {
        update_or_create_config($key, $value);
    }

    _log('[' . $admin['username'] . ']: M-Pesa ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

/**
 * Helper function to update or create config
 */
function update_or_create_config($key, $value) {
    $d = ORM::for_table('tbl_appconfig')->where('setting', $key)->find_one();
    if ($d) {
        $d->value = $value;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = $key;
        $d->value = $value;
        $d->save();
    }
}

/**
 * Create M-Pesa transaction
 */
function mpesa_create_transaction($trx, $user) {
    global $config;
    
    check_pending_transaction($user, $trx);
    
    $request_data = prepare_mpesa_request($trx, $user);
    $token = mpesa_get_token();
    $headers = ['Authorization: Bearer ' . $token];

    _log("M-Pesa Request [TRX: {$trx['id']}]: " . json_encode($request_data), 'MPesa');

    $result = send_mpesa_request($request_data, $headers);
    handle_mpesa_response($result, $trx, $user);
}

/**
 * Check for pending transactions
 */
function check_pending_transaction($user, $trx) {
    global $config;
    
    $timeout = intval($config['mpesa_pending_timeout']);
    $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$timeout} seconds"));
    
    $pending = ORM::for_table('tbl_payment_gateway')
        ->where('user_id', $user['id'])
        ->where('payment_gateway', 'M-Pesa')
        ->where('status', MPESA_STATUS_PENDING)
        ->where_gt('created_date', $cutoff_time)
        ->find_one();
        
    if ($pending) {
        delete_transaction($trx['id']);
        r2(U . 'order/package', 'w', Lang::T("Please wait for your previous M-Pesa request to complete or timeout before trying again."));
    }
}

/**
 * Prepare M-Pesa request data
 */
function prepare_mpesa_request($trx, $user) {
    global $config;
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);

    return [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $trx['price'],
        'PartyA' => $user['phonenumber'],
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $user['phonenumber'],
        'CallBackURL' => U . 'callback/mpesa',
        'AccountReference' => $trx['id'],
        'TransactionDesc' => 'Payment for Order #' . $trx['id']
    ];
}

/**
 * Send M-Pesa request
 */
function send_mpesa_request($json, $headers) {
    return json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpush/v1/processrequest', $json, $headers), true);
}

/**
 * Handle M-Pesa response
 */
function handle_mpesa_response($result, $trx, $user) {
    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== '0') {
        handle_mpesa_error($result, $trx, $user);
    }

    update_transaction($trx['id'], $result);
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
}

/**
 * Handle M-Pesa error response
 */
function handle_mpesa_error($result, $trx, $user) {
    $error_msg = "M-Pesa payment failed\nTransaction ID: {$trx['id']}\nUser: {$user['username']}\n" .
                 "Phone: {$user['phonenumber']}\nAmount: {$trx['price']}\n\nResponse:\n" . 
                 json_encode($result, JSON_PRETTY_PRINT);
    
    _log("M-Pesa Error [TRX: {$trx['id']}]: " . $error_msg, 'MPesa');
    sendTelegram($error_msg);
    
    if (isset($result['errorCode']) && $result['errorCode'] === '500.001.1001') {
        delete_transaction($trx['id']);
        r2(U . 'order/package', 'w', Lang::T("There is a pending M-Pesa request on your phone. Please complete or cancel it first, then try again."));
    }
    
    delete_transaction($trx['id']);
    r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. Please try again."));
}

/**
 * Update transaction with M-Pesa response
 */
function update_transaction($trx_id, $result) {
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('id', $trx_id)
        ->find_one();
    if ($d) {
        $d->gateway_trx_id = $result['CheckoutRequestID'];
        $d->pg_request = json_encode($result);
        $d->expired_date = date('Y-m-d H:i:s', strtotime('+ 4 HOURS'));
        $d->save();
    }
}

/**
 * Delete transaction
 */
function delete_transaction($trx_id) {
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('id', $trx_id)
        ->find_one();
    if ($d) {
        $d->delete();
    }
}

/**
 * Get M-Pesa transaction status
 */
function mpesa_get_status($trx, $user) {
    if ($trx['status'] == MPESA_STATUS_PAID) {
        return ['success' => true, 'message' => Lang::T("Transaction has been paid.")];
    }

    if (is_transaction_timed_out($trx)) {
        mark_transaction_timed_out($trx['id']);
        return ['success' => false, 'message' => Lang::T('Transaction timed out. Please try again.')];
    }

    $result = query_mpesa_status($trx);
    return process_status_result($result, $trx, $user);
}

/**
 * Check if transaction has timed out
 */
function is_transaction_timed_out($trx) {
    global $config;
    $timeout = intval($config['mpesa_pending_timeout']);
    $cutoff_time = date('Y-m-d H:i:s', strtotime("-{$timeout} seconds"));
    return $trx['created_date'] < $cutoff_time && $trx['status'] == MPESA_STATUS_PENDING;
}

/**
 * Mark transaction as timed out
 */
function mark_transaction_timed_out($trx_id) {
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('id', $trx_id)
        ->find_one();
    if ($d) {
        $d->status = MPESA_STATUS_FAILED;
        $d->save();
    }
}

/**
 * Query M-Pesa status
 */
function query_mpesa_status($trx) {
    global $config;
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);
    
    $json = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $trx['gateway_trx_id']
    ];

    $token = mpesa_get_token();
    $headers = ['Authorization: Bearer ' . $token];
    
    return json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpushquery/v1/query', $json, $headers), true);
}

/**
 * Process M-Pesa status result
 */
function process_status_result($result, $trx, $user) {
    if (isset($result['ResultCode']) && $result['ResultCode'] === '0') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
            return ['success' => false, 'message' => Lang::T("Failed to activate your Package, try again later.")];
        }

        update_successful_transaction($trx, $result);
        return ['success' => true, 'message' => Lang::T("Transaction has been paid.")];
    }
    
    return ['success' => false, 'message' => Lang::T("Transaction still unpaid.")];
}

/**
 * Update successful transaction
 */
function update_successful_transaction($trx, $result) {
    $d = ORM::for_table('tbl_payment_gateway')
        ->where('id', $trx['id'])
        ->find_one();
    if ($d) {
        $d->pg_paid_response = json_encode($result);
        $d->payment_method = 'M-Pesa';
        $d->payment_channel = 'STK Push';
        $d->paid_date = date('Y-m-d H:i:s');
        $d->status = MPESA_STATUS_PAID;
        $d->save();
    }
}

/**
 * Handle M-Pesa payment notification
 */
function mpesa_payment_notification() {
    $input = file_get_contents('php://input');
    $callback = json_decode($input, true);

    if (isset($callback['Body']['stkCallback'])) {
        $result = $callback['Body']['stkCallback'];
        $trx_id = $result['BillRefNumber'];
        
        if ($result['ResultCode'] === 0) {
            process_successful_callback($trx_id, $result);
        } else {
            sendTelegram("M-PESA payment failed for transaction: " . $trx_id . "\n" . json_encode($result, JSON_PRETTY_PRINT));
        }
    }
    
    die('OK');
}

/**
 * Process successful callback
 */
function process_successful_callback($trx_id, $result) {
    $trx = ORM::for_table('tbl_payment_gateway')
        ->where('id', $trx_id)
        ->find_one();
        
    if ($trx) {
        $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
            sendTelegram("Failed to activate package for transaction: " . $trx_id);
        }
        
        update_successful_transaction($trx, $result);
    }
}

/**
 * Get M-Pesa API token
 */
function mpesa_get_token() {
    global $config;
    
    $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
    $headers = ['Authorization: Basic ' . $credentials];
    
    $result = json_decode(Http::getData(mpesa_get_server() . 'oauth/v1/generate?grant_type=client_credentials', $headers), true);
    
    if (isset($result['access_token'])) {
        return $result['access_token'];
    }
    
    sendTelegram("M-Pesa token generation failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
    r2(U . 'order/package', 'e', Lang::T("Failed to connect to M-Pesa. Please try again later."));
}

/**
 * Get M-Pesa API server URL
 */
function mpesa_get_server() {
    global $_app_stage;
    return $_app_stage == 'Live' 
        ? 'https://api.safaricom.co.ke/'
        : 'https://sandbox.safaricom.co.ke/';
}
