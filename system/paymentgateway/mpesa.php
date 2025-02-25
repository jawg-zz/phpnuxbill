<?php
declare(strict_types=1);

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 * Payment Gateway M-Pesa
 **/

final class MPesaConfig {
    public const PAYMENT_METHOD = 'M-Pesa';
    public const CHANNEL = 'STK Push';
    public const SUCCESS_CODE = '0';
    public const PAID_STATUS = 2;
    public const FAILED_STATUS = 3;
    public const LOG_TYPE = 'MPesa';
    
    private const REQUIRED_CONFIG = [
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_shortcode',
        'mpesa_passkey'
    ];

    public static function getServer(): string {
        global $_app_stage;
        return $_app_stage == 'Live' 
            ? 'https://api.safaricom.co.ke/'
            : 'https://sandbox.safaricom.co.ke/';
    }

    public static function validate(): void {
        global $config;
        foreach (self::REQUIRED_CONFIG as $field) {
            if (empty($config[$field])) {
                throw new Exception("M-Pesa configuration missing: {$field}");
            }
        }
    }
}

function mpesa_show_config(): void {
    global $ui;
    $ui->assign('_title', 'M-Pesa - Payment Gateway');
    $ui->display('mpesa.tpl');
}

function mpesa_save_config(): void {
    global $admin;
    $settings = [
        'mpesa_consumer_key' => _post('mpesa_consumer_key'),
        'mpesa_consumer_secret' => _post('mpesa_consumer_secret'),
        'mpesa_shortcode' => _post('mpesa_shortcode'),
        'mpesa_passkey' => _post('mpesa_passkey')
    ];

    foreach ($settings as $setting => $value) {
        $d = ORM::for_table('tbl_appconfig')->where('setting', $setting)->find_one();
        if (!$d) {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = $setting;
        }
        $d->value = $value;
        $d->save();
    }

    _log('[' . $admin['username'] . ']: M-Pesa ' . Lang::T('Settings_Saved_Successfully'), 'Admin', $admin['id']);
    r2(U . 'paymentgateway/mpesa', 's', Lang::T('Settings_Saved_Successfully'));
}

function mpesa_create_transaction($trx, $user): void {
    try {
        MPesaConfig::validate();
        $phone = validate_phone_number($user['phonenumber']);
        $stk_request = create_stk_push_request($trx, $phone);
        $result = send_stk_push($stk_request);
        save_transaction_details($trx, $result);
        
        r2(U . "order/view/" . $trx['id'], 's', 
            Lang::T("Please check your phone to complete payment"));
            
    } catch (Exception $e) {
        mpesa_log('payment_initiation_failed', [
            'trx' => $trx,
            'details' => [
                'error' => $e->getMessage(),
                'user' => $user['username'],
                'phone' => $user['phonenumber']
            ]
        ], true);
        
        r2(U . 'order/package', 'e', $e->getMessage());
    }
}

function validate_phone_number(string $phone): string {
    $phone = preg_replace('/[\s+-]/', '', $phone);
    
    if (!preg_match('/^254\d{9}$/', $phone)) {
        throw new Exception(Lang::T("Invalid phone number format. Must be 254XXXXXXXXX"));
    }
    
    return $phone;
}

function create_stk_push_request($trx, string $phone): array {
    global $config;
    
    $timestamp = date('YmdHis');
    $password = base64_encode(
        $config['mpesa_shortcode'] . 
        $config['mpesa_passkey'] . 
        $timestamp
    );

    return [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $trx['price'],
        'PartyA' => $phone,
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $phone,
        'CallBackURL' => U . 'callback/mpesa',
        'AccountReference' => $trx['id'],
        'TransactionDesc' => 'Payment for Order #' . $trx['id']
    ];
}

function send_stk_push(array $request): array {
    $token = mpesa_get_token();
    $result = send_mpesa_request(
        'mpesa/stkpush/v1/processrequest',
        $request,
        ['Authorization: Bearer ' . $token]
    );

    if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== '0') {
        throw new Exception(
            Lang::T("Failed to initiate M-Pesa payment: ") . 
            ($result['ResponseDescription'] ?? 'Unknown error')
        );
    }

    return $result;
}

function save_transaction_details($trx, array $result): void {
    update_transaction($trx, [
        'gateway_trx_id' => $result['CheckoutRequestID'],
        'pg_request' => json_encode($result),
        'gateway' => 'mpesa',
        'payment_method' => MPesaConfig::PAYMENT_METHOD,
        'payment_channel' => MPesaConfig::CHANNEL,
        'status' => MPesaConfig::FAILED_STATUS // Default to failed until success callback
    ]);
}

function mpesa_payment_notification(): void {
    try {
        $callback = process_callback_input();
        $result = $callback['Body']['stkCallback'];
        
        $trx = find_transaction($result);
        if (!$trx) {
            throw new Exception('Transaction not found');
        }
        
        $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        handle_payment_result($trx, $result, $user);
        
    } catch (Exception $e) {
        mpesa_log('callback_error', [
            'details' => [
                'error' => $e->getMessage(),
                'callback_data' => $callback ?? 'none'
            ]
        ], true);
    }
}

function process_callback_input(): ?array {
    $input = file_get_contents('php://input');
    mpesa_log('callback_received', ['details' => ['raw_input' => $input]]);
    
    $callback = json_decode($input, true);
    if (!isset($callback['Body']['stkCallback'])) {
        throw new Exception('Invalid callback format');
    }
    
    return $callback;
}

function find_transaction($result): ?ORM {
    return ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $result['CheckoutRequestID'])
        ->find_one();
}

function handle_payment_result($trx, $result, $user): void {
    try {
        // First check if transaction is already processed
        if ($trx['status'] == MPesaConfig::PAID_STATUS) {
            mpesa_log('payment_already_processed', [
                'trx_id' => $trx['id'],
                'user' => $user['username']
            ]);
            return;
        }

        if ($result['ResultCode'] === 0) {
            // Extract payment details from callback
            $payment_details = $result['CallbackMetadata']['Item'];
            $amount = null;
            $mpesa_receipt = null;
            
            // Extract Amount and Receipt Number
            foreach ($payment_details as $item) {
                if ($item['Name'] === 'Amount') $amount = $item['Value'];
                if ($item['Name'] === 'MpesaReceiptNumber') $mpesa_receipt = $item['Value'];
            }

            // Verify payment amount matches transaction amount
            if ($amount != $trx['price']) {
                throw new Exception("Payment amount mismatch. Expected: {$trx['price']}, Received: {$amount}");
            }

            // First update transaction status
            update_transaction($trx, [
                'status' => MPesaConfig::PAID_STATUS,
                'pg_paid_response' => json_encode($result),
                'paid_date' => date('Y-m-d H:i:s'),
                'payment_method' => MPesaConfig::PAYMENT_METHOD,
                'payment_channel' => MPesaConfig::CHANNEL,
                'gateway_trx_id' => $mpesa_receipt
            ]);

            // Then proceed with package subscription
            if (!Package::rechargeUser(
                $user['id'], 
                $trx['routers'], 
                $trx['plan_id'], 
                $trx['gateway'], 
                MPesaConfig::CHANNEL
            )) {
                throw new Exception("Payment successful but failed to activate package");
            }
            
            // Log successful payment
            mpesa_log('payment_successful', [
                'trx_id' => $trx['id'],
                'user' => $user['username'],
                'amount' => $amount,
                'receipt' => $mpesa_receipt
            ]);

            Message::sendTelegram(
                "Payment Received from M-Pesa\n" .
                "Amount: {$amount}\n" .
                "Receipt: {$mpesa_receipt}\n" .
                "Customer: {$user['fullname']}\n" .
                "Transaction ID: {$trx['id']}"
            );
        } else {
            // Handle failed payment
            update_transaction($trx, [
                'status' => MPesaConfig::FAILED_STATUS,
                'pg_paid_response' => json_encode($result)
            ]);

            mpesa_log('payment_failed', [
                'trx_id' => $trx['id'],
                'user' => $user['username'],
                'result_code' => $result['ResultCode'],
                'result_desc' => $result['ResultDesc']
            ]);
        }
    } catch (Exception $e) {
        mpesa_log('payment_processing_error', [
            'trx' => $trx,
            'details' => ['error' => $e->getMessage()]
        ], true);
        
        // Ensure transaction is marked as failed in case of errors
        update_transaction($trx, [
            'status' => MPesaConfig::FAILED_STATUS,
            'pg_paid_response' => json_encode([
                'error' => $e->getMessage(),
                'original_result' => $result
            ])
        ]);
    }
}

function update_transaction($trx, array $data): void {
    foreach ($data as $key => $value) {
        $trx->$key = $value;
    }
    $trx->save();
}

function mpesa_get_token(): string {
    global $config;
    
    try {
        $credentials = base64_encode(
            $config['mpesa_consumer_key'] . ':' . 
            $config['mpesa_consumer_secret']
        );
        
        $response = Http::getData(
            MPesaConfig::getServer() . 'oauth/v1/generate?grant_type=client_credentials',
            ['Authorization: Basic ' . $credentials]
        );
        
        if (empty($response)) {
            throw new Exception('Empty response from M-Pesa API');
        }
        
        $result = json_decode($response, true);
        if (!$result || !isset($result['access_token'])) {
            throw new Exception('Invalid token response: ' . $response);
        }
        
        return $result['access_token'];
    } catch (Exception $e) {
        mpesa_log('token_error', [
            'details' => [
                'error' => $e->getMessage(),
                'consumer_key' => substr($config['mpesa_consumer_key'], 0, 4) . '****'
            ]
        ], true);
        throw new Exception(Lang::T('Failed to connect to M-Pesa. Please try again.'));
    }
}

function send_mpesa_request(string $endpoint, array $data, array $headers): array {
    $response = Http::postJsonData(MPesaConfig::getServer() . $endpoint, $data, $headers);
    $result = json_decode($response, true);
    
    if (!$result) {
        throw new Exception('Invalid response: ' . $response);
    }
    
    return $result;
}

function mpesa_log($action, array $data, bool $admin_notify = false): void {
    Log::put($action, MPesaConfig::LOG_TYPE, 0, json_encode($data));
    if ($admin_notify) {
        sendTelegram("M-Pesa $action\n\n" . json_encode($data, JSON_PRETTY_PRINT));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_transaction':
            $plan = ORM::for_table('tbl_plans')->find_one($_POST['plan_id']);
            if (!$plan) {
                // Redirect back to login page with error
                header('Location: ' . U . 'login/mlogin?error=Plan+not+found');
                exit;
            }

            // Create transaction record
            $trx = ORM::for_table('tbl_payment_gateway')->create();
            $trx->plan_id = $_POST['plan_id'];
            $trx->plan_name = $plan['name_plan'];
            $trx->price = $plan['price'];
            $trx->phone_number = $_POST['phone_number'];
            $trx->gateway = 'mpesa';
            $trx->status = 1; // Pending
            
            // Store Mikrotik login parameters
            $trx->link_login = $_POST['link_login'] ?? '';
            $trx->link_orig = $_POST['link_orig'] ?? '';
            $trx->mac = $_POST['mac'] ?? '';
            $trx->ip = $_POST['ip'] ?? '';
            
            $trx->save();

            // Create user array for mpesa function
            $user = ['phonenumber' => $_POST['phone_number']];
            
            // Initiate M-Pesa payment
            try {
                MPesaConfig::validate();
                $phone = validate_phone_number($user['phonenumber']);
                $stk_request = create_stk_push_request($trx, $phone);
                $result = send_stk_push($stk_request);
                save_transaction_details($trx, $result);
                
                // Redirect to login page with order_id for polling
                header('Location: ' . U . 'login/mlogin?order_id=' . $trx['id']);
                exit;
            } catch (Exception $e) {
                mpesa_log('payment_initiation_failed', [
                    'trx' => $trx,
                    'details' => [
                        'error' => $e->getMessage(),
                        'user' => 'guest',
                        'phone' => $user['phonenumber']
                    ]
                ]);
                
                // Redirect back to login page with error
                header('Location: ' . U . 'login/mlogin?error=' . urlencode($e->getMessage()));
                exit;
            }
            break;

        case 'get_status':
            $trx = ORM::for_table('tbl_payment_gateway')
                ->find_one($_POST['order_id']);
            if (!$trx) {
                // Redirect back to login page with error
                header('Location: ' . U . 'login/mlogin?error=Transaction+not+found');
                exit;
            }

            $user = ['phonenumber' => $trx['phone_number']];
            
            // If payment is successful, redirect to Mikrotik login
            if ($trx['status'] == MPesaConfig::PAID_STATUS) {
                // Redirect to Mikrotik login with credentials
                $redirect_url = $trx['link_login'] . '?username=' . $trx['plan_id'] . '&password=' . $trx['id'];
                header('Location: ' . $redirect_url);
                exit;
            }
            
            // Otherwise, redirect back to login page for continued polling
            header('Location: ' . U . 'login/mlogin?order_id=' . $trx['id']);
            exit;
            break;
    }
}
