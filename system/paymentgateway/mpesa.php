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

function mpesa_create_transaction($trx, $user)
{
    global $config;
    
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

    $token = mpesa_get_token();
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];

    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpush/v1/processrequest', $json, $headers), true);

    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== '0') {
        sendTelegram("M-Pesa payment failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
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

    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
}

function mpesa_get_status($trx, $user)
{
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
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];

    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpushquery/v1/query', $json, $headers), true);

    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid."));
    }

    if (isset($result['ResultCode']) && $result['ResultCode'] === '0') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }

        $trx->pg_paid_response = json_encode($result);
        $trx->payment_method = 'M-Pesa';
        $trx->payment_channel = 'STK Push';
        $trx->paid_date = date('Y-m-d H:i:s');
        $trx->status = 2;
        $trx->save();

        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } else {
        r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
    }
}

function mpesa_payment_notification()
{
    $input = file_get_contents('php://input');
    $callback = json_decode($input, true);

    if (isset($callback['Body']['stkCallback'])) {
        $result = $callback['Body']['stkCallback'];
        $trx_id = $result['BillRefNumber'];
        
        if ($result['ResultCode'] === 0) {
            $trx = ORM::for_table('tbl_payment_gateway')
                ->where('id', $trx_id)
                ->find_one();
                
            if ($trx) {
                $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
                if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa')) {
                    sendTelegram("Failed to activate package for transaction: " . $trx_id);
                }
                
                $trx->pg_paid_response = json_encode($result);
                $trx->payment_method = 'M-Pesa';
                $trx->payment_channel = 'STK Push';
                $trx->paid_date = date('Y-m-d H:i:s');
                $trx->status = 2;
                $trx->save();
            }
        } else {
            sendTelegram("M-PESA payment failed for transaction: " . $trx_id . "\n" . json_encode($result, JSON_PRETTY_PRINT));
        }
    }
    
    die('OK');
}

function mpesa_get_token()
{
    global $config;
    
    $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
    $headers = ['Authorization: Basic ' . $credentials];
    
    $result = json_decode(Http::get(mpesa_get_server() . 'oauth/v1/generate?grant_type=client_credentials', $headers), true);
    
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