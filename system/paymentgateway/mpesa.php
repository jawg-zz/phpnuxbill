<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway M-Pesa STK Push
 **/

function mpesa_validate_config()
{
    global $config;
    if (empty($config['mpesa_consumer_key']) || empty($config['mpesa_consumer_secret']) || empty($config['mpesa_passkey']) || empty($config['mpesa_shortcode'])) {
        sendTelegram("M-Pesa STK Push payment gateway not configured");
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup M-Pesa STK Push payment gateway, please tell admin"));
    }
}

function mpesa_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'M-Pesa STK Push - Payment Gateway');
    $ui->display('mpesa.tpl');
}

function mpesa_save_config()
{
    global $admin;
    $mpesa_consumer_key = _post('mpesa_consumer_key');
    $mpesa_consumer_secret = _post('mpesa_consumer_secret');
    $mpesa_passkey = _post('mpesa_passkey');
    $mpesa_shortcode = _post('mpesa_shortcode');

    $config_fields = [
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_passkey',
        'mpesa_shortcode'
    ];

    foreach ($config_fields as $field) {
        $value = _post($field);
        $d = ORM::for_table('tbl_appconfig')->where('setting', $field)->find_one();
        if ($d) {
            $d->value = $value;
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $field;
            $d->value = $value;
            $d->save();
        }
    }

    _log('[' . $admin['username'] . ']: M-Pesa STK Push ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

function mpesa_create_transaction($trx, $user)
{
    global $config;

    $amount = $trx['price'];
    $phoneNumber = $user['phonenumber'];
    $invoiceNumber = $trx['id'];
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);

    $json = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phoneNumber,
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $phoneNumber,
        'CallBackURL' => U . 'callback/mpesa',
        'AccountReference' => $invoiceNumber,
        'TransactionDesc' => 'Payment for Invoice ' . $invoiceNumber
    ];

    $headers = [
        'Authorization: Bearer ' . mpesa_get_token(),
        'Content-Type: application/json'
    ];

    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpush/v1/processrequest', $json, $headers), true);

    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== '0') {
        sendTelegram("M-Pesa STK Push payment failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. " . ($result['errorMessage'] ?? 'Unknown error')));
    }

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = $result['CheckoutRequestID'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime('+ 1 HOUR'));
    $d->save();

    r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please complete the payment on your phone."));
}

function mpesa_get_status($trx, $user)
{
    global $config;

    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid."));
    }

    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);

    $json = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $trx['gateway_trx_id']
    ];

    $headers = [
        'Authorization: Bearer ' . mpesa_get_token(),
        'Content-Type: application/json'
    ];

    $result = json_decode(Http::postJsonData(mpesa_get_server() . 'mpesa/stkpushquery/v1/query', $json, $headers), true);

    if (!isset($result['ResultCode'])) {
        sendTelegram("M-Pesa STK Push status check failed\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . "order/view/" . $trx['id'], 'e', Lang::T("Failed to check transaction status."));
    }

    if ($result['ResultCode'] == '0') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa STK Push')) {
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
    $callbackJSONData = file_get_contents('php://input');
    $callbackData = json_decode($callbackJSONData, true);

    $resultCode = $callbackData['Body']['stkCallback']['ResultCode'];
    $resultDesc = $callbackData['Body']['stkCallback']['ResultDesc'];
    $merchantRequestID = $callbackData['Body']['stkCallback']['MerchantRequestID'];
    $checkoutRequestID = $callbackData['Body']['stkCallback']['CheckoutRequestID'];

    if ($resultCode == 0) {
        $amount = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
        $mpesaReceiptNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
        $transactionDate = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][3]['Value'];
        $phoneNumber = $callbackData['Body']['stkCallback']['CallbackMetadata']['Item'][4]['Value'];

        $trx = ORM::for_table('tbl_payment_gateway')
            ->where('gateway_trx_id', $checkoutRequestID)
            ->find_one();

        if ($trx) {
            $user = ORM::for_table('tbl_customers')->find_one($trx['customer_id']);
            if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'M-Pesa STK Push')) {
                sendTelegram("Failed to activate package for user " . $user['username']);
            }
            $trx->status = 2;
            $trx->paid_date = date('Y-m-d H:i:s', strtotime($transactionDate));
            $trx->pg_paid_response = $callbackJSONData;
            $trx->save();
        }
    } else {
        sendTelegram("M-Pesa STK Push payment failed\n\nCheckoutRequestID: $checkoutRequestID\nResultCode: $resultCode\nResultDesc: $resultDesc");
    }

    // Respond to M-Pesa
    $response = ['ResultCode' => 0, 'ResultDesc' => 'Confirmation received successfully'];
    header('Content-Type: application/json');
    echo json_encode($response);
}

function mpesa_get_token()
{
    global $config;
    $url = mpesa_get_server() . 'oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
    $headers = ['Authorization: Basic ' . $credentials];
    $result = json_decode(Http::get($url, $headers), true);
    if (isset($result['access_token'])) {
        return $result['access_token'];
    } else {
        sendTelegram("M-Pesa failed to get access token\n\n" . json_encode($result, JSON_PRETTY_PRINT));
        r2(U . 'order/package', 'e', Lang::T("Failed to connect to M-Pesa. Please try again later."));
    }
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
