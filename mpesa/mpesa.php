<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 * M-Pesa Payment Gateway
 **/

function mpesa_validate_config()
{
    global $config;
    if (empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret'])) {
        r2(U . 'order/package', 'w', Lang::T("M-Pesa payment gateway not configured"));
    }
}

function mpesa_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'M-Pesa - Payment Gateway - ' . $config['CompanyName']);
    $ui->display('mpesa/views/settings.tpl');
}

function mpesa_save_config()
{
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

function mpesa_create_transaction($trx, $user)
{
    global $config, $_L;
    
    // Validate system configuration
    $requiredConfig = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_shortcode', 'mpesa_passkey'];
    foreach ($requiredConfig as $key) {
        if (empty($config[$key])) {
            throw new Exception($_L['Mpesa_Not_Configured']);
        }
    }

    // Validate phone number format
    $phone = preg_replace('/^0/', '254', $user['phonenumber']);
    if (!preg_match('/^2547[0-9]{8}$/', $phone)) {
        throw new Exception($_L['Invalid_Phone_Format']);
    }
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);
    
    $payload = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $trx['price'],
        'PartyA' => $phone,
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $phone,
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

function mpesa_get_status($trx, $user)
{
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
        $trx->status = 2;
        $trx->pg_paid_response = $response;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Payment successful"));
    } else {
        r2(U . "order/view/" . $trx['id'], 'e', $result['ResultDesc']);
    }
}

function get_mpesa_token()
{
    global $config, $_L;
    
    if(empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret'])) {
        throw new Exception($_L['Mpesa_Credentials_Missing']);
    }

    $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
    
    $httpCode = 0;
    $response = Http::postJsonData(
        'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
        [],
        ["Authorization: Basic $credentials"],
        $httpCode
    );
    
    if($httpCode !== 200) {
        _log("Mpesa Token Error [$httpCode]: " . substr($response, 0, 200), 'MPESA');
        throw new Exception($_L['API_Connection_Failed']);
    }
    
    $result = json_decode($response, true);
    
    if(!isset($result['access_token'])) {
        throw new Exception($_L['Invalid_API_Response']);
    }
    
    return $result['access_token'];
}