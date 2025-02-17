<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway M-Pesa
 **/

// Constants for M-Pesa configuration
const MPESA_CONFIG_KEYS = [
    'mpesa_consumer_key',
    'mpesa_consumer_secret',
    'mpesa_shortcode',
    'mpesa_passkey'
];

const MPESA_DEFAULT_TIMEOUT = 120;
const MPESA_MIN_TIMEOUT = 30;
const MPESA_MAX_TIMEOUT = 300;

/**
 * Validates M-Pesa configuration settings
 */
function mpesa_validate_config()
{
    global $config;
    
    foreach (MPESA_CONFIG_KEYS as $key) {
        if (empty($config[$key])) {
            r2(U . 'order/package', 'e', "Please configure {$key} in Settings");
        }
    }

    // Set and validate timeout
    if (empty($config['mpesa_pending_timeout'])) {
        $config['mpesa_pending_timeout'] = MPESA_DEFAULT_TIMEOUT;
    } else {
        $timeout = intval($config['mpesa_pending_timeout']);
        $config['mpesa_pending_timeout'] = max(MPESA_MIN_TIMEOUT, min($timeout, MPESA_MAX_TIMEOUT));
    }
}

/**
 * Displays M-Pesa configuration page
 */
function mpesa_show_config()
{
    global $ui;
    $ui->assign('_title', 'M-Pesa - Payment Gateway');
    $ui->display('mpesa.tpl');
}

/**
 * Updates or creates a configuration entry
 */
function update_config_entry($setting, $value)
{
    $d = ORM::for_table('tbl_appconfig')->where('setting', $setting)->find_one();
    if (!$d) {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = $setting;
    }
    $d->value = $value;
    $d->save();
}

/**
 * Saves M-Pesa configuration settings
 */
function mpesa_save_config()
{
    global $admin;
    
    $config_values = [
        'mpesa_consumer_key' => _post('mpesa_consumer_key'),
        'mpesa_consumer_secret' => _post('mpesa_consumer_secret'),
        'mpesa_shortcode' => _post('mpesa_shortcode'),
        'mpesa_passkey' => _post('mpesa_passkey'),
        'mpesa_pending_timeout' => _post('mpesa_pending_timeout')
    ];

    foreach ($config_values as $key => $value) {
        update_config_entry($key, $value);
    }
    
    _log('[' . $admin['username'] . ']: M-Pesa ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

/**
 * Checks for pending M-Pesa transactions
 */
function check_pending_mpesa_transaction($user_id, $cutoff_time)
{
    return ORM::for_table('tbl_payment_gateway')
        ->where('user_id', $user_id)
        ->where('payment_gateway', 'M-Pesa')
        ->where('status', 1)
        ->where_gt('created_date', $cutoff_time)
        ->find_one();
}

/**
 * Prepares M-Pesa transaction payload
 */
function prepare_mpesa_payload($trx, $user, $config, $timestamp)
{
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
 * Handles M-Pesa transaction errors
 */
function handle_mpesa_error($trx, $user, $result)
{
    $error_msg = "M-Pesa payment failed\nTransaction ID: {$trx['id']}\nUser: {$user['username']}"
        . "\nPhone: {$user['phonenumber']}\nAmount: {$trx['price']}\n\nResponse:\n"
        . json_encode($result, JSON_PRETTY_PRINT);
    
    _log("M-Pesa Error [TRX: {$trx['id']}]: " . $error_msg, 'MPesa');
    sendTelegram($error_msg);

    $d = ORM::for_table('tbl_payment_gateway')->where('id', $trx['id'])->find_one();
    $d->delete();

    if (isset($result['errorCode']) && $result['errorCode'] === '500.001.1001') {
        r2(U . 'order/package', 'w', Lang::T("There is a pending M-Pesa request on your phone. Please complete or cancel it first, then try again."));
    }
    
    r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. Please try again."));
}

/**
 * Creates a new M-Pesa transaction
 */
function mpesa_create_transaction($trx, $user)
{
    global $config;
    
    $timeout = intval($config['mpesa_pending_timeout']);
    $cutoff_time = date('Y-m-d H:i:s', strtotime("-$timeout seconds"));
    
    // Check for pending transactions
    $pending = check_pending_mpesa_transaction($user['id'], $cutoff_time);
    if ($pending) {
        $d = ORM::for_table('tbl_payment_gateway')->where('id', $trx['id'])->find_one();
        $d->delete();
        r2(U . 'order/package', 'w', Lang::T("Please wait for your previous M-Pesa request to complete or timeout before trying again."));
    }
    
    $timestamp = date('YmdHis');
    $payload = prepare_mpesa_payload($trx, $user, $config, $timestamp);
    
    _log("M-Pesa Request [TRX: {$trx['id']}]: " . json_encode($payload), 'MPesa');
    
    $token = mpesa_get_token();
    $headers = ['Authorization: Bearer ' . $token];
    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpush/v1/processrequest', $payload, $headers), true);
    
    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== '0') {
        handle_mpesa_error($trx, $user, $result);
    }
    
    // Update transaction record
    $d = ORM::for_table('tbl_payment_gateway')->where('id', $trx['id'])->find_one();
    $d->gateway_trx_id = $result['CheckoutRequestID'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime('+ 4 HOURS'));
    $d->save();
    
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
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
