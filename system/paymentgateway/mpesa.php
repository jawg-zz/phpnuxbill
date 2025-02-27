<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 **/

interface PaymentGatewayInterface {
    public function findTransaction(string $gatewayTrxId): ?array;
    public function updateTransaction(string $gatewayTrxId, array $data): void;
    public function logPaymentAction(string $action, array $data, bool $notifyAdmin = false): void;
}

interface MpesaConfigInterface {
    public function getServer(): string;
    public function getCredentials(): array;
    public function validate(): void;
}

class ORMAdapter implements PaymentGatewayInterface {
    public function findTransaction(string $gatewayTrxId): ?array {
        return ORM::for_table('tbl_payment_gateway')
            ->where('gateway_trx_id', $gatewayTrxId)
            ->find_array();
    }

    public function updateTransaction(string $gatewayTrxId, array $data): void {
        $trx = ORM::for_table('tbl_payment_gateway')
            ->where('gateway_trx_id', $gatewayTrxId)
            ->find_one();
        
        if ($trx) {
            foreach ($data as $key => $value) {
                $trx->$key = $value;
            }
            $trx->save();
        }
    }

    public function logPaymentAction(string $action, array $data, bool $notifyAdmin = false): void {
        Log::put($action, 'MPESA', 0, json_encode($data));
        if ($notifyAdmin) {
            Message::sendTelegram("M-Pesa $action\n\n".json_encode($data, JSON_PRETTY_PRINT));
        }
    }
}

class MPesaConfig implements MpesaConfigInterface {
    private array $config;
    
    // Add constants for status and payment information
    const PAYMENT_METHOD = 'M-Pesa';
    const CHANNEL = 'mobile_money';
    const PENDING_STATUS = 'pending';
    const PAID_STATUS = 'paid';
    const FAILED_STATUS = 'failed';

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function getServer(): string {
        return isset($this->config['mpesa_sandbox']) && $this->config['mpesa_sandbox'] ?
            'https://sandbox.safaricom.co.ke/' :
            'https://api.safaricom.co.ke/';
    }

    public function getCredentials(): array {
        return [
            'consumer_key' => $this->config['mpesa_consumer_key'] ?? '',
            'consumer_secret' => $this->config['mpesa_consumer_secret'] ?? '',
            'shortcode' => $this->config['mpesa_shortcode'] ?? '',
            'passkey' => $this->config['mpesa_passkey'] ?? ''
        ];
    }
    
    public static function getConfig(): array {
        global $config;
        
        $mpesa_config = [
            'consumer_key' => $config['mpesa_consumer_key'] ?? 'NOT SET',
            'consumer_secret' => $config['mpesa_consumer_secret'] ? 'SET (hidden)' : 'NOT SET',
            'shortcode' => $config['mpesa_shortcode'] ?? 'NOT SET',
            'passkey' => $config['mpesa_passkey'] ? 'SET (hidden)' : 'NOT SET',
            'sandbox' => $config['mpesa_sandbox'] ?? false
        ];
        
        return $mpesa_config;
    }
    
    public function validate(): void {
        $credentials = $this->getCredentials();
        $required = [
            'consumer_key' => 'Consumer Key',
            'consumer_secret' => 'Consumer Secret',
            'shortcode' => 'Shortcode',
            'passkey' => 'Passkey'
        ];
        
        foreach ($required as $key => $name) {
            if (empty($credentials[$key])) {
                throw new \Exception("M-Pesa $name is not configured");
            }
        }
    }
}

function mpesa_show_config(): void {
    global $ui;
    $ui->assign('mpesa_config', MPesaConfig::getConfig());
    $ui->assign('_title', 'M-Pesa - Payment Gateway');
    $ui->display('mpesa.tpl');
}

function mpesa_save_config(): void {
    global $admin;
    $settings = [
        'mpesa_consumer_key' => _post('mpesa_consumer_key'),
        'mpesa_consumer_secret' => _post('mpesa_consumer_secret'),
        'mpesa_shortcode' => _post('mpesa_shortcode'),
        'mpesa_passkey' => _post('mpesa_passkey'),
        'mpesa_sandbox' => _post('mpesa_sandbox') ? '1' : '0'
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
        global $config;
        $mpesaConfig = new MPesaConfig($config);
        $mpesaConfig->validate();
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

/**
 * Validates and formats a phone number to the required format
 * 
 * @param string $phone The phone number to validate
 * @return string The formatted phone number
 * @throws Exception If the phone number is invalid
 */
function validate_phone_number(string $phone): string {
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Check if it's a valid Kenyan phone number
    if (preg_match('/^(?:254|0)?(7\d{8})$/', $phone, $matches)) {
        return '254' . $matches[1];
    }
    
    throw new Exception(Lang::T('Invalid phone number format. Please use a valid Kenyan phone number.'));
}

/**
 * Creates the STK push request payload
 * 
 * @param array $trx The transaction object
 * @param string $phone The formatted phone number
 * @return array The STK push request payload
 */
function create_stk_push_request($trx, $phone): array {
    global $config;
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'] . $config['mpesa_passkey'] . $timestamp);
    
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
        'AccountReference' => 'PHPNuxBill-' . $trx['id'],
        'TransactionDesc' => 'Internet Package: ' . $trx['plan_name']
    ];
}

/**
 * Sends the STK push request to M-Pesa API
 * 
 * @param array $request The STK push request payload
 * @return array The response from M-Pesa API
 * @throws Exception If the request fails
 */
function send_stk_push($request): array {
    $token = mpesa_get_token();
    
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    
    $result = send_mpesa_request('mpesa/stkpush/v1/processrequest', $request, $headers);
    
    if (!isset($result['CheckoutRequestID'])) {
        throw new Exception('Failed to initiate M-Pesa payment: ' . ($result['errorMessage'] ?? 'Unknown error'));
    }
    
    return $result;
}

/**
 * Saves the transaction details after STK push
 * 
 * @param array $trx The transaction object
 * @param array $result The response from M-Pesa API
 */
function save_transaction_details($trx, $result): void {
    // Get existing pg_request data to preserve hotspot_data if it exists
    $pg_data = json_decode($trx['pg_request'], true) ?: [];
    
    // Add M-Pesa result to pg_data
    $pg_data['mpesa_result'] = $result;
    
    update_transaction($trx, [
        'gateway_trx_id' => $result['CheckoutRequestID'],
        'pg_request' => json_encode($pg_data),
        'gateway' => 'mpesa',
        'payment_method' => MPesaConfig::PAYMENT_METHOD,
        'payment_channel' => MPesaConfig::CHANNEL,
        'status' => MPesaConfig::PENDING_STATUS
    ]);
}

/**
 * Handles the M-Pesa payment notification callback
 */
function mpesa_payment_notification(): void {
    try {
        $callback = process_callback_input();
        if (!$callback) {
            throw new Exception('Invalid callback data');
        }
        
        $result = $callback['Body']['stkCallback'];
        
        $trx = find_transaction($result['CheckoutRequestID']);
        if (!$trx) {
            throw new Exception('Transaction not found: ' . $result['CheckoutRequestID']);
        }
        
        $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
        
        handle_payment_result($trx, $result, $user ? $user->as_array() : null);
        
        // Return a success response to M-Pesa
        header('Content-Type: application/json');
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
        
    } catch (Exception $e) {
        mpesa_log('callback_error', [
            'details' => [
                'error' => $e->getMessage(),
                'callback_data' => $callback ?? 'none'
            ]
        ], true);
        
        // Return an error response to M-Pesa
        header('Content-Type: application/json');
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Processes the callback input from M-Pesa
 * 
 * @return array|null The processed callback data or null if invalid
 */
function process_callback_input(): ?array {
    $input = file_get_contents('php://input');
    mpesa_log('callback_received', ['details' => ['raw_input' => $input]]);
    
    $callback = json_decode($input, true);
    if (!$callback || !isset($callback['Body']['stkCallback'])) {
        return null;
    }
    
    return $callback;
}

/**
 * Finds a transaction by the CheckoutRequestID
 * 
 * @param string $checkoutRequestId The CheckoutRequestID from M-Pesa
 * @return array|null The transaction object or null if not found
 */
function find_transaction(string $checkoutRequestId): ?array {
    $result = ORM::for_table('tbl_payment_gateway')
        ->where('gateway_trx_id', $checkoutRequestId)
        ->find_one();
    return $result ? $result->as_array() : null;
}

/**
 * Handles the payment result from M-Pesa
 * 
 * @param array $trx The transaction object
 * @param array $result The STK callback result
 * @param array|null $user The user object
 * @throws Exception If there's an error processing the payment
 */
function handle_payment_result($trx, $result, $user = null): void {
    try {
        // First check if transaction is already processed
        if ($trx['status'] == MPesaConfig::PAID_STATUS) {
            mpesa_log('payment_already_processed', [
                'trx_id' => $trx['id'],
                'user' => $user ? $user['username'] : 'guest'
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
                if ($item['Name'] === 'Amount') {
                    $amount = $item['Value'];
                } else if ($item['Name'] === 'MpesaReceiptNumber') {
                    $mpesa_receipt = $item['Value'];
                }
            }
            
            // Update transaction status
            update_transaction($trx, [
                'payment_ref' => $mpesa_receipt,
                'pg_response' => json_encode($result),
                'status' => MPesaConfig::PAID_STATUS
            ]);
            
            // Check if this is a hotspot login transaction
            $pg_data = json_decode($trx['pg_request'], true) ?: [];
            $hotspot_data = $pg_data['hotspot_data'] ?? [];
            
            if (!empty($hotspot_data['hotspot_login'])) {
                // For hotspot login, we don't need to activate a package
                mpesa_log('hotspot_payment_successful', [
                    'trx_id' => $trx['id'],
                    'amount' => $amount,
                    'receipt' => $mpesa_receipt
                ]);
                return;
            }

            // For regular transactions, proceed with package subscription if user exists
            if ($user && !Package::rechargeUser(
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
                'user' => $user ? $user['username'] : 'guest',
                'amount' => $amount,
                'receipt' => $mpesa_receipt
            ]);
        } else {
            // Payment failed
            update_transaction($trx, [
                'pg_response' => json_encode($result),
                'status' => MPesaConfig::FAILED_STATUS
            ]);
            
            mpesa_log('payment_failed', [
                'trx_id' => $trx['id'],
                'user' => $user ? $user['username'] : 'guest',
                'result_code' => $result['ResultCode'],
                'result_desc' => $result['ResultDesc']
            ]);
        }
    } catch (Exception $e) {
        mpesa_log('payment_processing_error', [
            'trx_id' => $trx['id'],
            'error' => $e->getMessage()
        ], true);
        throw $e;
    }
}

/**
 * Updates a transaction with the given data
 * 
 * @param array $trx The transaction object
 * @param array $data The data to update
 */
function update_transaction($trx, array $data): void {
    $orm = ORM::for_table('tbl_payment_gateway')->find_one($trx['id']);
    foreach ($data as $key => $value) {
        $orm->$key = $value;
    }
    $orm->save();
}

/**
 * Gets an access token from M-Pesa API
 * 
 * @return string The access token
 * @throws Exception If the token request fails
 */
function mpesa_get_token(): string {
    global $config;
    
    try {
        $credentials = base64_encode(
            $config['mpesa_consumer_key'] . ':' . 
            $config['mpesa_consumer_secret']
        );
        
        $headers = [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ];
        
        $response = Http::getData(
            (new MPesaConfig($config))->getServer() . 'oauth/v1/generate?grant_type=client_credentials',
            $headers
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

/**
 * Sends a request to M-Pesa API
 * 
 * @param string $endpoint The API endpoint
 * @param array $data The request data
 * @param array $headers The request headers
 * @return array The response from M-Pesa API
 * @throws Exception If the request fails
 */
function send_mpesa_request(string $endpoint, array $data, array $headers): array {
    global $config;
    $mpesaConfig = new MPesaConfig($config);
    $response = Http::postJsonData($mpesaConfig->getServer() . $endpoint, $data, $headers);
    $result = json_decode($response, true);
    
    if (!$result) {
        throw new Exception('Invalid response: ' . $response);
    }
    
    return $result;
}

/**
 * Logs M-Pesa related actions
 * 
 * @param string $action The action to log
 * @param array $data The data to log
 * @param bool $admin_notify Whether to notify the admin
 */
function mpesa_log($action, array $data, bool $admin_notify = false): void {
    Log::put($action, 'MPESA', 0, json_encode($data));
    if ($admin_notify) {
        Message::sendTelegram("M-Pesa $action\n\n" . json_encode($data, JSON_PRETTY_PRINT));
    }
}