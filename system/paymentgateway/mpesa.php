<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway M-Pesa
 **/

function mpesa_validate_config()
{
    global $config;
    $required = ['mpesa_consumer_key', 'mpesa_consumer_secret', 'mpesa_shortcode', 'mpesa_passkey'];
    
    foreach ($required as $field) {
        if (empty($config[$field])) {
            sendTelegram("M-Pesa payment gateway not configured");
            r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup M-Pesa payment gateway, please tell admin"));
        }
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
    $fields = [
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_shortcode',
        'mpesa_passkey'
    ];

    foreach ($fields as $field) {
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

    _log('[' . $admin['username'] . ']: M-Pesa ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

function mpesa_create_transaction($trx, $user)
{
    global $config;
    
    try {
        $mpesa = get_mpesa_gateway();
        
        // Validate phone number
        $phone = validate_phone_number($user['phonenumber']);
        
        // Create STK Push request
        $stkPushResult = $mpesa->initiateSTKPush(
            amount: $trx['price'],
            phone: $phone,
            accountRef: $trx['id'],
            transactionDesc: 'Payment for Order #' . $trx['id'],
            callbackUrl: U . 'callback/mpesa'
        );

        // Save transaction details
        update_transaction_record($trx['id'], [
            'gateway_trx_id' => $stkPushResult['CheckoutRequestID'],
            'pg_request' => json_encode($stkPushResult),
            'expired_date' => date('Y-m-d H:i:s', strtotime('+ 4 HOURS'))
        ]);

        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
        
    } catch (Exception $e) {
        log_error('mpesa_create_transaction', $e->getMessage(), [
            'trx_id' => $trx['id'],
            'user' => $user['username']
        ]);
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. Please try again."));
    }
}

function mpesa_get_status($trx, $user)
{
    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid."));
    }

    try {
        $mpesa = get_mpesa_gateway();
        $result = $mpesa->queryTransactionStatus($trx['gateway_trx_id']);

        if ($result['ResultCode'] === '0') {
            process_successful_payment($trx, $user);
            r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
        } else {
            r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
        }
        
    } catch (Exception $e) {
        log_error('mpesa_get_status', $e->getMessage(), [
            'trx_id' => $trx['id'],
            'user' => $user['username']
        ]);
        r2(U . "order/view/" . $trx['id'], 'e', Lang::T("Failed to check transaction status."));
    }
}

function mpesa_payment_notification()
{
    try {
        $input = file_get_contents('php://input');
        $callback = json_decode($input, true);

        if (!isset($callback['Body']['stkCallback'])) {
            throw new Exception('Invalid callback data');
        }

        $result = $callback['Body']['stkCallback'];
        $trx_id = $result['BillRefNumber'];
        
        if ($result['ResultCode'] === 0) {
            $trx = get_transaction($trx_id);
            if (!$trx) {
                throw new Exception('Transaction not found: ' . $trx_id);
            }

            $user = get_user($trx['user_id']);
            if (!$user) {
                throw new Exception('User not found for transaction: ' . $trx_id);
            }

            process_successful_payment($trx, $user, $result);
        } else {
            log_error('mpesa_callback_failed', $result['ResultDesc'], [
                'trx_id' => $trx_id,
                'result' => $result
            ]);
        }
        
        http_response_code(200);
        die('OK');
        
    } catch (Exception $e) {
        log_error('mpesa_callback_error', $e->getMessage(), [
            'input' => $input ?? null
        ]);
        http_response_code(400);
        die('Error');
    }
}

// Helper functions
function get_mpesa_gateway()
{
    global $config, $_app_stage;
    return new MPesaGateway([
        'consumer_key' => $config['mpesa_consumer_key'],
        'consumer_secret' => $config['mpesa_consumer_secret'],
        'shortcode' => $config['mpesa_shortcode'],
        'passkey' => $config['mpesa_passkey'],
        'environment' => $_app_stage == 'Live' ? 'production' : 'sandbox'
    ]);
}

function validate_phone_number($phone)
{
    $phone = preg_replace('/\D/', '', $phone);
    if (preg_match('/^(?:254|0)?(7\d{8})$/', $phone, $matches)) {
        return '254' . $matches[1];
    }
    throw new Exception('Invalid phone number format');
}

function update_transaction_record($id, array $data)
{
    $trx = ORM::for_table('tbl_payment_gateway')->find_one($id);
    foreach ($data as $key => $value) {
        $trx->set($key, $value);
    }
    $trx->save();
}

function get_transaction($id)
{
    return ORM::for_table('tbl_payment_gateway')
        ->where('id', $id)
        ->find_one();
}

function get_user($id)
{
    return ORM::for_table('tbl_customers')->find_one($id);
}

function process_successful_payment($trx, $user, $mpesaResult = null)
{
    if ($trx['status'] == 2) {
        return; // Already processed
    }

    // Activate the package
    if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], 'mpesa', 'M-Pesa')) {
        throw new Exception('Failed to activate package');
    }

    // Update transaction record
    update_transaction_record($trx['id'], [
        'pg_paid_response' => $mpesaResult ? json_encode($mpesaResult) : null,
        'payment_method' => 'M-Pesa',
        'payment_channel' => 'STK Push',
        'paid_date' => date('Y-m-d H:i:s'),
        'status' => 2
    ]);
}

function log_error($action, $message, array $context = [])
{
    Log::put('MPESA', $action . ': ' . $message, '', json_encode([
        'message' => $message,
        'context' => $context
    ]));
    sendTelegram("M-Pesa $action: $message\n\n" . json_encode($context, JSON_PRETTY_PRINT));
}
