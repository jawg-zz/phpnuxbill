<?php
declare(strict_types=1);

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 * Payment Gateway M-Pesa
 **/

// Constants
final class MPesaConfig {
    public const PAYMENT_METHOD = 'M-Pesa';
    public const CHANNEL = 'STK Push';
    public const SUCCESS_CODE = '0';
    public const PAID_STATUS = 2;
    public const LOG_TYPE = 'MPesa';
    
    private const REQUIRED_CONFIG = [
        'mpesa_consumer_key',
        'mpesa_consumer_secret',
        'mpesa_shortcode',
        'mpesa_passkey'
    ];

    public static function validate(): void {
        global $config;
        foreach (self::REQUIRED_CONFIG as $field) {
            if (empty($config[$field])) {
                sendTelegram("M-Pesa payment gateway not configured");
                r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup M-Pesa payment gateway, please tell admin"));
            }
        }
    }

    public static function getServer(): string {
        global $_app_stage;
        return $_app_stage == 'Live' 
            ? 'https://api.safaricom.co.ke/' 
            : 'https://sandbox.safaricom.co.ke/';
    }
}

// Main Gateway Functions
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
    global $config;
    
    MPesaConfig::validate();
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);

    $request = [
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

    try {
        $token = mpesa_get_token();
        $result = send_mpesa_request(
            'mpesa/stkpush/v1/processrequest',
            $request,
            ['Authorization: Bearer ' . $token]
        );

        if (!isset($result['ResponseCode']) || $result['ResponseCode'] !== MPesaConfig::SUCCESS_CODE) {
            throw new Exception(json_encode($result));
        }

        update_transaction($trx, [
            'gateway_trx_id' => $result['CheckoutRequestID'],
            'pg_request' => json_encode($result),
            'expired_date' => date('Y-m-d H:i:s', strtotime('+ 4 HOURS'))
        ]);

        _log("M-Pesa Transaction Created [TRX: {$trx['id']}] CheckoutRequestID: {$result['CheckoutRequestID']}", MPesaConfig::LOG_TYPE);
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
    } catch (Exception $e) {
        log_payment_failure($trx, $user, ['error' => $e->getMessage()]);
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction. Please try again in a few minutes."));
    }
}

function mpesa_payment_notification(): void {
    try {
        $callback = process_callback_input();
        if (!$callback) {
            exit('OK');
        }

        $result = $callback['Body']['stkCallback'];
        $trx = find_transaction($result);
        if (!$trx) {
            debug_transaction_search($result);
            exit('OK');
        }

        handle_payment_result($trx, $result);
    } catch (Exception $e) {
        mpesa_log('request_error', ['error' => $e->getMessage()], true);
    } finally {
        exit('OK');
    }
}

// Payment Processing Functions
function handle_payment_result($trx, $result, $user = null, $is_status_check = false): bool {
    try {
        if ($result['ResultCode'] === 0) {
            if (!$user) {
                $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
                if (!$user) {
                    throw new Exception('User not found');
                }
            }

            $success = process_payment_success($trx, $user, $result, !$is_status_check);
            
            if ($is_status_check) {
                $message = $success 
                    ? ['s', Lang::T("Transaction has been paid.")] 
                    : ['d', Lang::T("Failed to activate your Package, try again later.")];
                r2(U . "order/view/" . $trx['id'], ...$message);
            }
            
            return $success;
        }

        throw new Exception(json_encode($result));
    } catch (Exception $e) {
        log_payment_failure($trx, $user, ['error' => $e->getMessage()]);
        
        if ($is_status_check) {
            r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid"));
        }
        
        return false;
    }
}

/**
 * Process a successful M-Pesa payment
 * 
 * @param ORM $trx Transaction record from tbl_payment_gateway
 * @param array $user User record from tbl_customers
 * @param array $result M-Pesa STK Push callback response
 * @param bool $should_activate Whether to activate the package immediately
 * @return bool Success status
 * @throws Exception If payment validation or activation fails
 */
function process_payment_success($trx, $user, $result, $should_activate = true): bool {
    // Verify transaction hasn't been processed
    if ($trx->status === MPesaConfig::PAID_STATUS) {
        mpesa_log('payment_duplicate', [
            'trx' => $trx,
            'details' => ['message' => 'Transaction already processed']
        ]);
        return true;
    }

    // Validate M-Pesa payment details
    $items = $result['CallbackMetadata']['Item'] ?? [];
    $payment_data = [];
    
    foreach ($items as $item) {
        $payment_data[$item['Name']] = $item['Value'];
    }

    // Required fields according to Daraja API
    $required_fields = ['Amount', 'MpesaReceiptNumber', 'TransactionDate', 'PhoneNumber'];
    foreach ($required_fields as $field) {
        if (!isset($payment_data[$field])) {
            throw new Exception("Missing required M-Pesa field: {$field}");
        }
    }

    // Validate payment amount
    if ((float)$payment_data['Amount'] !== (float)$trx['price']) {
        throw new Exception("Amount mismatch: Expected {$trx['price']}, got {$payment_data['Amount']}");
    }

    // Validate phone number format (remove leading + if present)
    $paid_phone = ltrim($payment_data['PhoneNumber'], '+');
    $user_phone = ltrim($user['phonenumber'], '+');
    if ($paid_phone !== $user_phone) {
        mpesa_log('payment_phone_mismatch', [
            'trx' => $trx,
            'details' => [
                'expected' => $user_phone,
                'received' => $paid_phone
            ]
        ], true);
        // Continue processing as phone might be different but payment is valid
    }

    try {
        // Activate package if required
        if ($should_activate && !activate_user_package($trx, $user)) {
            throw new Exception('Package activation failed');
        }

        // Update transaction with M-Pesa details
        update_transaction($trx, [
            'pg_paid_response' => json_encode($result),
            'payment_method' => MPesaConfig::PAYMENT_METHOD,
            'payment_channel' => MPesaConfig::CHANNEL,
            'paid_date' => date('Y-m-d H:i:s'),
            'status' => MPesaConfig::PAID_STATUS,
            'gateway_reference' => $payment_data['MpesaReceiptNumber']
        ]);
        
        mpesa_log('payment_success', [
            'trx' => $trx,
            'details' => [
                'user' => $user['username'],
                'phone' => $user['phonenumber'],
                'amount' => $payment_data['Amount'],
                'receipt' => $payment_data['MpesaReceiptNumber'],
                'date' => $payment_data['TransactionDate']
            ]
        ]);

        return true;
    } catch (Exception $e) {
        mpesa_log('payment_processing_failed', [
            'trx' => $trx,
            'details' => [
                'error' => $e->getMessage(),
                'mpesa_data' => $payment_data
            ]
        ], true);
        throw $e;
    }
}

// Helper Functions
function process_callback_input(): ?array {
    $input = file_get_contents('php://input');
    mpesa_log('callback_received', [
        'details' => ['raw_input' => $input]
    ]);
    
    $callback = json_decode($input, true);
    
    if (!isset($callback['Body']['stkCallback'])) {
        throw new Exception('Invalid callback format');
    }
    
    $result = $callback['Body']['stkCallback'];
    if (!isset($result['BillRefNumber']) || empty($result['BillRefNumber'])) {
        throw new Exception('Missing or empty BillRefNumber');
    }
    
    return $callback;
}

function find_transaction($result): ?ORM {
    return ORM::for_table('tbl_payment_gateway')
        ->where_any_is([
            ['id' => $result['BillRefNumber']],
            ['gateway_trx_id' => $result['CheckoutRequestID'] ?? null]
        ])
        ->find_one();
}

function update_transaction($trx, array $data): void {
    foreach ($data as $key => $value) {
        $trx->$key = $value;
    }
    $trx->save();
}

function log_payment_failure($trx, $user = null, $result = null): void {
    $details = ['transaction_id' => $trx['id']];
    
    if ($user) {
        $details += [
            'user' => $user['username'],
            'phone' => $user['phonenumber'],
            'amount' => $trx['price']
        ];
    }

    if ($result) {
        $details['response'] = $result;
    }

    mpesa_log('payment_failed', [
        'trx' => $trx,
        'details' => $details
    ], true);
}

function activate_user_package($trx, $user): bool {
    return Package::rechargeUser(
        $user['id'], 
        $trx['routers'], 
        $trx['plan_id'], 
        $trx['gateway'], 
        MPesaConfig::PAYMENT_METHOD
    );
}

function mpesa_get_token(): string {
    global $config;
    
    try {
        $credentials = base64_encode($config['mpesa_consumer_key'] . ':' . $config['mpesa_consumer_secret']);
        $headers = ['Authorization: Basic ' . $credentials];
        
        $response = Http::getdata(
            MPesaConfig::getServer() . 'oauth/v1/generate?grant_type=client_credentials',
            $headers
        );
        
        if (empty($response)) {
            throw new Exception('Empty response from M-Pesa API');
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception('Invalid JSON response: ' . $response);
        }
        
        if (!isset($result['access_token'])) {
            throw new Exception('Access token not found in response: ' . $response);
        }
        
        return $result['access_token'];
    } catch (Exception $e) {
        mpesa_log('token_error', [
            'details' => [
                'error' => $e->getMessage(),
                'consumer_key' => substr($config['mpesa_consumer_key'], 0, 4) . '****',
                'response' => $response ?? 'none'
            ]
        ], true);
        throw new Exception('Failed to generate M-Pesa token: ' . $e->getMessage());
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

function debug_transaction_search($result): void {
    mpesa_log('transaction_not_found', [
        'details' => [
            'bill_reference' => $result['BillRefNumber'],
            'checkout_request_id' => $result['CheckoutRequestID'] ?? 'none',
            'search_query' => ORM::for_table('tbl_payment_gateway')
                ->where('id', $result['BillRefNumber'])
                ->build_select()
        ]
    ], true);
}

function log_status_check($trx, $result): void {
    mpesa_log('status_check', [
        'trx' => $trx,
        'details' => [
            'result' => $result
        ]
    ]);
}

/**
 * Unified logging function for M-Pesa operations that follows PSR-3 logging standards
 * 
 * @param string $message_key Predefined message key or custom message
 * @param array $context Additional context data following PSR-3 context standards
 * @param bool $notify Whether to send Telegram notification
 * @return string The formatted log message
 */
function mpesa_log($message_key, array $context = [], bool $notify = false): string {
    // Predefined messages for consistency
    $messages = [
        'transaction_not_found' => 'Transaction not found in database',
        'status_check' => 'M-Pesa Status Check',
        'payment_success' => 'Payment successful',
        'payment_failed' => 'Payment failed',
        'request_error' => 'API request error',
        'token_error' => 'Failed to generate M-Pesa token',
        'callback_received' => 'M-Pesa callback received',
        'callback_error' => 'Error processing M-Pesa callback',
        'transaction_created' => 'New M-Pesa transaction created',
        'config_updated' => 'M-Pesa configuration updated'
    ];
    
    $base_message = $messages[$message_key] ?? $message_key;
    
    // Format transaction ID if present
    $trx_id = $context['trx']['id'] ?? ($context['trx_id'] ?? null);
    $trx_prefix = $trx_id ? "[TRX: $trx_id] " : '';
    
    // Build message with context
    $log_message = $trx_prefix . $base_message;
    if (!empty($context['details'])) {
        $log_message .= "\n" . Log::arrayToText($context['details']);
    }

    // Log to system using standard _log function
    _log($log_message, MPesaConfig::LOG_TYPE);

    // Optional Telegram notification
    if ($notify) {
        Message::sendTelegram($log_message);
    }

    return $log_message;
}
