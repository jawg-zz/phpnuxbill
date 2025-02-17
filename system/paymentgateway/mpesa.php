<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway M-Pesa
 **/

function mpesa_validate_config()
{
    global $config;
    if (empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret']) || 
        empty($config['mpesa_shortcode']) || empty($config['mpesa_passkey'])) {
        sendTelegram("M-Pesa payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup M-Pesa payment gateway, please tell admin"));
    }
}

function mpesa_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'M-Pesa - Payment Gateway');
    $ui->display('mpesa.tpl');
}

function mpesa_save_config()
{
    global $admin;
    $mpesa_consumer_key = _post('mpesa_consumer_key');
    $mpesa_consumer_secret = _post('mpesa_consumer_secret');
    $mpesa_shortcode = _post('mpesa_shortcode');
    $mpesa_passkey = _post('mpesa_passkey');

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_key')->find_one();
    if($d){
        $d->value = $mpesa_consumer_key;
        $d->save();
    }else{
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_consumer_key';
        $d->value = $mpesa_consumer_key;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_consumer_secret')->find_one();
    if($d){
        $d->value = $mpesa_consumer_secret;
        $d->save();
    }else{
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_consumer_secret';
        $d->value = $mpesa_consumer_secret;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_shortcode')->find_one();
    if($d){
        $d->value = $mpesa_shortcode;
        $d->save();
    }else{
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_shortcode';
        $d->value = $mpesa_shortcode;
        $d->save();
    }

    $d = ORM::for_table('tbl_appconfig')->where('setting', 'mpesa_passkey')->find_one();
    if($d){
        $d->value = $mpesa_passkey;
        $d->save();
    }else{
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'mpesa_passkey';
        $d->value = $mpesa_passkey;
        $d->save();
    }

    _log('[' . $admin['username'] . ']: M-Pesa ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

function mpesa_create_transaction($trx, $user) {
    global $config, $_app_stage;
    
    // Add debug logs at the start
    _log("M-Pesa Debug [TRX: {$trx['id']}]:", 'MPesa');
    _log("- Environment: " . $_app_stage, 'MPesa');
    _log("- API URL: " . mpesa_get_server(), 'MPesa');
    _log("- Shortcode: " . $config['mpesa_shortcode'], 'MPesa');
    _log("- Shortcode Type: Paybill", 'MPesa');
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);
    
    $json = [
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

    // Debug log the request
    _log("M-Pesa Request [TRX: {$trx['id']}]: " . json_encode($json), 'MPesa');

    $token = mpesa_get_token();
    $headers = [
        'Authorization: Bearer ' . $token,
    ];

    // Debug log the token
    _log("M-Pesa Token [TRX: {$trx['id']}]: " . $token, 'MPesa');

    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpush/v1/processrequest', $json, $headers), true);

    // Debug log the response
    _log("M-Pesa Response [TRX: {$trx['id']}]: " . json_encode($result), 'MPesa');

    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== '0') {
        $error_msg = "M-Pesa payment failed\nTransaction ID: {$trx['id']}\nUser: {$user['username']}\nPhone: {$user['phonenumber']}\nAmount: {$trx['price']}\n\nResponse:\n" . json_encode($result, JSON_PRETTY_PRINT);
        _log("M-Pesa Error [TRX: {$trx['id']}]: " . $error_msg, 'MPesa');
        sendTelegram($error_msg);
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. Please try again."));
    }

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = $result['CheckoutRequestID'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime('+ 4 HOURS'));
    $d->save();

    _log("M-Pesa Transaction Created [TRX: {$trx['id']}] CheckoutRequestID: {$result['CheckoutRequestID']}", 'MPesa');
    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
}

function mpesa_get_status($trx, $user)
{
    global $config;

    _log("M-Pesa Status Check [TRX: {$trx['id']}] Started", 'MPesa');

    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);

    $json = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $trx['gateway_trx_id']
    ];

    $token = mpesa_get_token();
    $headers = [
        'Authorization: Bearer ' . $token,
    ];

    // Debug log the status check request
    _log("M-Pesa Status Check Request [TRX: {$trx['id']}]: " . json_encode($json), 'MPesa');

    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpushquery/v1/query', $json, $headers), true);

    // Debug log the status check response
    _log("M-Pesa Status Check Response [TRX: {$trx['id']}]: " . json_encode($result), 'MPesa');

    if ($trx['status'] == 2) {
        _log("M-Pesa Status Check [TRX: {$trx['id']}]: Transaction already paid", 'MPesa');
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid."));
    }

    if (isset($result['ResultCode']) && $result['ResultCode'] === '0') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
            _log("M-Pesa Package Activation Error [TRX: {$trx['id']}] User: {$user['username']}", 'MPesa');
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }

        $trx->pg_paid_response = json_encode($result);
        $trx->payment_method = 'M-Pesa';
        $trx->payment_channel = 'STK Push';
        $trx->paid_date = date('Y-m-d H:i:s');
        $trx->status = 2;
        $trx->save();

        _log("M-Pesa Status Check [TRX: {$trx['id']}]: Payment confirmed and package activated", 'MPesa');
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } else {
        _log("M-Pesa Status Check [TRX: {$trx['id']}]: Transaction still unpaid", 'MPesa');
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
    }
}

function mpesa_payment_notification()
{
    $input = file_get_contents('php://input');
    
    // Debug log the raw callback
    _log("M-Pesa Callback Raw: " . $input, 'MPesa');
    
    $callback = json_decode($input, true);

    if (isset($callback['Body']['stkCallback'])) {
        $result = $callback['Body']['stkCallback'];
        
        // Debug the full callback structure
        _log("M-Pesa Callback Structure: " . json_encode($result, JSON_PRETTY_PRINT), 'MPesa');
        
        // Check if BillRefNumber exists
        if (!isset($result['BillRefNumber'])) {
            _log("M-Pesa Error: Missing BillRefNumber in callback", 'MPesa');
            die('OK');
        }
        
        $trx_id = $result['BillRefNumber'];
        
        // Validate transaction ID
        if (empty($trx_id)) {
            _log("M-Pesa Error: Empty transaction ID", 'MPesa');
            die('OK');
        }
        
        _log("M-Pesa Callback [TRX: $trx_id]: " . json_encode($result), 'MPesa');
        
        // Look up transaction first
        $trx = ORM::for_table('tbl_payment_gateway')
            ->where('id', $trx_id)
            ->find_one();
            
        if (!$trx) {
            // Try looking up by CheckoutRequestID
            if (isset($result['CheckoutRequestID'])) {
                $trx = ORM::for_table('tbl_payment_gateway')
                    ->where('gateway_trx_id', $result['CheckoutRequestID'])
                    ->find_one();
            }
        }
        
        if (!$trx) {
            _log("M-Pesa Error: Transaction not found. ID: $trx_id, CheckoutRequestID: " . 
                 ($result['CheckoutRequestID'] ?? 'none'), 'MPesa');
            
            // Debug query
            $query = ORM::for_table('tbl_payment_gateway')
                ->where('id', $trx_id)
                ->build_select();
            _log("M-Pesa Debug - Search Query: " . $query, 'MPesa');
            
            die('OK');
        }
        
        if ($result['ResultCode'] === 0) {
            $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
            if (!$user) {
                _log("M-Pesa Error: User not found for transaction $trx_id", 'MPesa');
                die('OK');
            }
            
            if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
                $error_msg = "Failed to activate package\nTransaction ID: $trx_id\n" .
                            "User: {$user['username']}\nRouter: {$trx['routers']}\n" .
                            "Plan: {$trx['plan_id']}";
                _log("M-Pesa Package Activation Error [TRX: $trx_id]: " . $error_msg, 'MPesa');
                sendTelegram($error_msg);
            } else {
                _log("M-Pesa Package Activated [TRX: $trx_id] for user {$user['username']}", 'MPesa');
            }
            
            $trx->pg_paid_response = json_encode($result);
            $trx->payment_method = 'M-Pesa';
            $trx->payment_channel = 'STK Push';
            $trx->paid_date = date('Y-m-d H:i:s');
            $trx->status = 2;
            $trx->save();
        } else {
            $error_msg = "M-PESA payment failed\nTransaction ID: $trx_id\n" .
                        "Result Code: {$result['ResultCode']}\n" .
                        "Result Description: {$result['ResultDesc']}";
            _log("M-Pesa Payment Failed [TRX: $trx_id]: " . $error_msg, 'MPesa');
            sendTelegram($error_msg);
        }
    } else {
        _log("M-Pesa Invalid Callback Format: " . $input, 'MPesa');
    }
    
    die('OK');
}

function mpesa_get_token()
{
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

function mpesa_get_server()
{
    global $_app_stage;
    if ($_app_stage == 'Live') {
        return 'https://api.safaricom.co.ke/';
    } else {
        return 'https://sandbox.safaricom.co.ke/';
    }
}
