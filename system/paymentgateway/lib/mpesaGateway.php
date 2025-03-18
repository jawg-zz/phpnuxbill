<?php

/**
 * M-Pesa Payment Gateway API Client
 */
class MPesaGateway
{
    private array $config;
    private string $baseUrl;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1; // seconds
    private MPesaConfig $mpesaConfig;

    public function __construct(array $config)
    {
        $this->mpesaConfig = new MPesaConfig($config);
        $this->mpesaConfig->validate();
        $this->config = $config;
        $this->baseUrl = $this->mpesaConfig->getServer();
    }

    /**
     * Initiates an STK Push request
     */
    public function initiateSTKPush(
        float $amount,
        string $phone,
        string $accountRef,
        string $transactionDesc,
        string $callbackUrl
    ): array {
        // Validate amount and phone number
        $this->mpesaConfig->validateAmount($amount);
        $phone = $this->mpesaConfig->validatePhone($phone);
        
        $timestamp = date('YmdHis');
        $password = $this->generatePassword($timestamp);

        $payload = [
            'BusinessShortCode' => $this->mpesaConfig->getShortcode(),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => MPesaConfig::CUSTOMER_PAYBILL_ONLINE,
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->mpesaConfig->getShortcode(),
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountRef,
            'TransactionDesc' => $transactionDesc
        ];

        return $this->makeRequestWithRetry('mpesa/stkpush/v1/processrequest', $payload);
    }

    /**
     * Queries the status of an STK Push transaction
     */
    public function queryTransactionStatus(string $checkoutRequestId): array
    {
        $timestamp = date('YmdHis');
        $password = $this->generatePassword($timestamp);

        $payload = [
            'BusinessShortCode' => $this->mpesaConfig->getShortcode(),
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];

        return $this->makeRequestWithRetry('mpesa/stkpushquery/v1/query', $payload);
    }

    /**
     * Makes an authenticated request to the M-Pesa API with retry mechanism
     */
    private function makeRequestWithRetry(string $endpoint, array $payload, int $maxRetries = self::MAX_RETRIES): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $maxRetries) {
            try {
                return $this->makeRequest($endpoint, $payload);
            } catch (Exception $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts === $maxRetries) {
                    throw new PaymentException(
                        'API request failed after ' . $maxRetries . ' attempts',
                        PaymentException::API_CONNECTION_ERROR,
                        [
                            'endpoint' => $endpoint,
                            'attempts' => $attempts,
                            'last_error' => $e->getMessage()
                        ],
                        $e
                    );
                }
                
                sleep(self::RETRY_DELAY);
            }
        }

        throw $lastException;
    }

    /**
     * Makes an authenticated request to the M-Pesa API
     */
    private function makeRequest(string $endpoint, array $payload): array
    {
        $token = $this->getAccessToken();
        $headers = [
            'Authorization: Bearer ' . $token
        ];

        $response = Http::postJsonData(
            $this->baseUrl . $endpoint,
            $payload,
            $headers
        );

        return $this->handleResponse($response);
    }

    /**
     * Gets an OAuth access token
     */
    private function getAccessToken(): string
    {
        $credentials = base64_encode(
            $this->mpesaConfig->getConsumerKey() . ':' . 
            $this->mpesaConfig->getConsumerSecret()
        );

        $response = Http::getData(
            $this->baseUrl . 'oauth/v1/generate?grant_type=client_credentials',
            ['Authorization: Basic ' . $credentials]
        );

        $result = $this->handleResponse($response);

        if (!isset($result['access_token'])) {
            throw new PaymentException(
                'Failed to get access token',
                PaymentException::API_CONNECTION_ERROR,
                ['response' => $result]
            );
        }

        return $result['access_token'];
    }

    /**
     * Generates the M-Pesa API password
     */
    private function generatePassword(string $timestamp): string
    {
        return base64_encode(
            $this->mpesaConfig->getShortcode() . 
            $this->mpesaConfig->getPasskey() . 
            $timestamp
        );
    }

    /**
     * Handles API response
     */
    private function handleResponse(string $response): array
    {
        if (empty($response)) {
            throw new PaymentException(
                'Empty response from M-Pesa API',
                PaymentException::INVALID_RESPONSE,
                ['response' => $response]
            );
        }

        $result = json_decode($response, true);
        if (!$result) {
            throw new PaymentException(
                'Invalid JSON response',
                PaymentException::INVALID_RESPONSE,
                ['response' => $response]
            );
        }

        if (isset($result['errorCode'])) {
            throw new PaymentException(
                $result['errorMessage'] ?? 'Unknown API error',
                PaymentException::API_CONNECTION_ERROR,
                ['error_code' => $result['errorCode'], 'response' => $result]
            );
        }

        return $result;
    }
}

/**
 * Configuration class for M-Pesa Gateway
 */
class MPesaConfig {
    private array $config;
    
    // Transaction Statuses
    const PENDING_STATUS = 0;
    const PAID_STATUS = 1;
    const FAILED_STATUS = 2;
    const EXPIRED_STATUS = 3;
    const CANCELLED_STATUS = 4;
    const REFUNDED_STATUS = 5;
    
    // M-Pesa Result Codes
    const MPESA_SUCCESS = '0';
    const MPESA_PENDING = '1032'; // Request cancelled by user
    const MPESA_INSUFFICIENT_FUNDS = '1037';
    const MPESA_TRANSACTION_FAILED = '1';
    const MPESA_DUPLICATE = '1031';
    const MPESA_INVALID_AMOUNT = '1033';
    const MPESA_INVALID_ACCOUNT = '1034';
    const MPESA_SYSTEM_ERROR = '1035';
    
    // Status Details
    private const STATUS_DETAILS = [
        self::PENDING_STATUS => [
            'text' => 'Pending',
            'icon' => 'fa-clock',
            'color' => 'warning',
            'description' => 'Waiting for payment confirmation from M-Pesa'
        ],
        self::PAID_STATUS => [
            'text' => 'Paid',
            'icon' => 'fa-check-circle',
            'color' => 'success',
            'description' => 'Payment has been successfully processed'
        ],
        self::FAILED_STATUS => [
            'text' => 'Failed',
            'icon' => 'fa-times-circle',
            'color' => 'danger',
            'description' => 'Payment processing failed'
        ],
        self::EXPIRED_STATUS => [
            'text' => 'Expired',
            'icon' => 'fa-hourglass-end',
            'color' => 'secondary',
            'description' => 'Payment request has expired'
        ],
        self::CANCELLED_STATUS => [
            'text' => 'Cancelled',
            'icon' => 'fa-ban',
            'color' => 'info',
            'description' => 'Payment request was cancelled'
        ],
        self::REFUNDED_STATUS => [
            'text' => 'Refunded',
            'icon' => 'fa-undo',
            'color' => 'primary',
            'description' => 'Payment has been refunded'
        ]
    ];
    
    // Transaction Types
    const CUSTOMER_PAYBILL_ONLINE = 'CustomerPayBillOnline';
    const CUSTOMER_BUYGOODS_ONLINE = 'CustomerBuyGoodsOnline';
    
    // Validation Constants
    const PHONE_REGEX = '/^(?:254|0)?(7\d{8})$/';
    const MAX_AMOUNT = 150000;
    const MIN_AMOUNT = 1;
    const EXPIRY_HOURS = 4;
    
    public function __construct(array $config) {
        $this->config = $config;
    }
    
    public function validate(): void {
        $required = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey'];
        
        foreach ($required as $field) {
            if (empty($this->config[$field])) {
                throw new PaymentException(
                    "Missing required configuration: $field",
                    PaymentException::INVALID_CONFIGURATION,
                    ['field' => $field]
                );
            }
        }
    }
    
    public function validateAmount(float $amount): void {
        if ($amount < self::MIN_AMOUNT || $amount > self::MAX_AMOUNT) {
            throw new PaymentException(
                "Amount must be between " . self::MIN_AMOUNT . " and " . self::MAX_AMOUNT,
                PaymentException::INVALID_AMOUNT,
                ['amount' => $amount]
            );
        }
    }
    
    public function validatePhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone);
        if (!preg_match(self::PHONE_REGEX, $phone, $matches)) {
            throw PaymentException::invalidPhoneNumber($phone);
        }
        return '254' . $matches[1];
    }
    
    public function getTransactionStatus(string $resultCode): int {
        return match($resultCode) {
            self::MPESA_SUCCESS => self::PAID_STATUS,
            self::MPESA_PENDING => self::PENDING_STATUS,
            self::MPESA_INSUFFICIENT_FUNDS => self::FAILED_STATUS,
            default => self::FAILED_STATUS
        };
    }
    
    public function getStatusDetails(int $status): array {
        return self::STATUS_DETAILS[$status] ?? [
            'text' => 'Unknown',
            'icon' => 'fa-question-circle',
            'color' => 'secondary',
            'description' => 'Unknown payment status'
        ];
    }
    
    public function getStatusText(int $status): string {
        return $this->getStatusDetails($status)['text'];
    }
    
    public function getStatusIcon(int $status): string {
        return $this->getStatusDetails($status)['icon'];
    }
    
    public function getStatusColor(int $status): string {
        return $this->getStatusDetails($status)['color'];
    }
    
    public function getStatusDescription(int $status): string {
        return $this->getStatusDetails($status)['description'];
    }
    
    public function getResultCodeDetails(string $resultCode): array {
        return match($resultCode) {
            self::MPESA_SUCCESS => [
                'status' => self::PAID_STATUS,
                'message' => 'Payment successful',
                'description' => 'The payment has been processed successfully'
            ],
            self::MPESA_PENDING => [
                'status' => self::PENDING_STATUS,
                'message' => 'Payment pending',
                'description' => 'Waiting for payment confirmation'
            ],
            self::MPESA_INSUFFICIENT_FUNDS => [
                'status' => self::FAILED_STATUS,
                'message' => 'Insufficient funds',
                'description' => 'Your M-Pesa account has insufficient funds'
            ],
            self::MPESA_DUPLICATE => [
                'status' => self::FAILED_STATUS,
                'message' => 'Duplicate transaction',
                'description' => 'This transaction has already been processed'
            ],
            self::MPESA_INVALID_AMOUNT => [
                'status' => self::FAILED_STATUS,
                'message' => 'Invalid amount',
                'description' => 'The payment amount is invalid'
            ],
            self::MPESA_INVALID_ACCOUNT => [
                'status' => self::FAILED_STATUS,
                'message' => 'Invalid account',
                'description' => 'The account reference is invalid'
            ],
            self::MPESA_SYSTEM_ERROR => [
                'status' => self::FAILED_STATUS,
                'message' => 'System error',
                'description' => 'M-Pesa system is currently unavailable'
            ],
            default => [
                'status' => self::FAILED_STATUS,
                'message' => 'Transaction failed',
                'description' => 'The payment could not be processed'
            ]
        };
    }
    
    public function getConsumerKey(): string {
        return $this->config['consumer_key'];
    }
    
    public function getConsumerSecret(): string {
        return $this->config['consumer_secret'];
    }
    
    public function getShortcode(): string {
        return $this->config['shortcode'];
    }
    
    public function getPasskey(): string {
        return $this->config['passkey'];
    }
    
    public function getServer(): string {
        $environment = $this->config['environment'] ?? 'sandbox';
        return $environment === 'production'
            ? 'https://api.safaricom.co.ke/'
            : 'https://sandbox.safaricom.co.ke/';
    }
    
    public function getExpiryTime(): string {
        return date('Y-m-d H:i:s', strtotime('+ ' . self::EXPIRY_HOURS . ' HOURS'));
    }
}

/**
 * Custom exception for payment related errors
 */
class PaymentException extends Exception {
    // Error codes for different payment scenarios
    const INVALID_PHONE_NUMBER = 1001;
    const INVALID_CONFIGURATION = 1002;
    const API_CONNECTION_ERROR = 1003;
    const INVALID_RESPONSE = 1004;
    const TRANSACTION_NOT_FOUND = 1005;
    const PAYMENT_FAILED = 1006;
    const PACKAGE_ACTIVATION_ERROR = 1007;
    const INVALID_CALLBACK = 1008;
    const AMOUNT_MISMATCH = 1009;
    const TRANSACTION_EXPIRED = 1010;
    const INVALID_AMOUNT = 1011;
    const INSUFFICIENT_FUNDS = 1012;
    const REQUEST_CANCELLED = 1013;
    const DUPLICATE_TRANSACTION = 1014;
    const INVALID_ACCOUNT = 1015;
    
    /**
     * Additional payment context data
     */
    private array $context;
    
    /**
     * Creates a new PaymentException instance
     */
    public function __construct(
        string $message,
        int $code = 0,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }
    
    /**
     * Gets the additional context data
     */
    public function getContext(): array {
        return $this->context;
    }
    
    /**
     * Creates an exception for invalid phone number
     */
    public static function invalidPhoneNumber(string $phone): self {
        return new self(
            Lang::T('Invalid phone number format. Please use a valid Kenyan phone number.'),
            self::INVALID_PHONE_NUMBER,
            ['phone' => $phone]
        );
    }
    
    /**
     * Creates an exception for invalid configuration
     */
    public static function invalidConfiguration(string $field): self {
        return new self(
            "Missing required configuration: $field",
            self::INVALID_CONFIGURATION,
            ['field' => $field]
        );
    }
    
    /**
     * Creates an exception for API connection errors
     */
    public static function apiConnectionError(string $details, ?Throwable $previous = null): self {
        return new self(
            Lang::T('Failed to connect to M-Pesa. Please try again.'),
            self::API_CONNECTION_ERROR,
            ['details' => $details],
            $previous
        );
    }
    
    /**
     * Creates an exception for invalid API responses
     */
    public static function invalidResponse(string $response): self {
        return new self(
            'Invalid response from M-Pesa API',
            self::INVALID_RESPONSE,
            ['response' => $response]
        );
    }
    
    /**
     * Creates an exception for transaction not found
     */
    public static function transactionNotFound(string $transactionId): self {
        return new self(
            "Transaction not found: $transactionId",
            self::TRANSACTION_NOT_FOUND,
            ['transaction_id' => $transactionId]
        );
    }
    
    /**
     * Creates an exception for payment failures
     */
    public static function paymentFailed(string $reason, array $result): self {
        return new self(
            $reason,
            self::PAYMENT_FAILED,
            ['result' => $result]
        );
    }
    
    /**
     * Creates an exception for package activation errors
     */
    public static function packageActivationError(string $details, ?Throwable $previous = null): self {
        return new self(
            "Failed to activate package: $details",
            self::PACKAGE_ACTIVATION_ERROR,
            ['details' => $details],
            $previous
        );
    }
    
    /**
     * Creates an exception for invalid callbacks
     */
    public static function invalidCallback(): self {
        return new self(
            'Invalid callback data received',
            self::INVALID_CALLBACK
        );
    }

    /**
     * Creates an exception for amount mismatch
     */
    public static function amountMismatch(float $expected, float $received): self {
        return new self(
            "Payment amount mismatch. Expected: $expected, Received: $received",
            self::AMOUNT_MISMATCH,
            ['expected' => $expected, 'received' => $received]
        );
    }

    /**
     * Creates an exception for expired transactions
     */
    public static function transactionExpired(string $transactionId): self {
        return new self(
            "Transaction expired: $transactionId",
            self::TRANSACTION_EXPIRED,
            ['transaction_id' => $transactionId]
        );
    }
    
    /**
     * Creates an exception for invalid amount
     */
    public static function invalidAmount(float $amount, float $min, float $max): self {
        return new self(
            "Invalid amount. Must be between $min and $max",
            self::INVALID_AMOUNT,
            ['amount' => $amount, 'min' => $min, 'max' => $max]
        );
    }
    
    /**
     * Creates an exception for insufficient funds
     */
    public static function insufficientFunds(string $phone): self {
        return new self(
            Lang::T('Insufficient funds in M-Pesa account'),
            self::INSUFFICIENT_FUNDS,
            ['phone' => $phone]
        );
    }
    
    /**
     * Creates an exception for cancelled requests
     */
    public static function requestCancelled(string $transactionId): self {
        return new self(
            Lang::T('M-Pesa payment request cancelled by user'),
            self::REQUEST_CANCELLED,
            ['transaction_id' => $transactionId]
        );
    }
    
    /**
     * Creates an exception for duplicate transactions
     */
    public static function duplicateTransaction(string $transactionId): self {
        return new self(
            "Duplicate transaction detected: $transactionId",
            self::DUPLICATE_TRANSACTION,
            ['transaction_id' => $transactionId]
        );
    }
    
    /**
     * Creates an exception for invalid account
     */
    public static function invalidAccount(string $account): self {
        return new self(
            "Invalid account reference: $account",
            self::INVALID_ACCOUNT,
            ['account' => $account]
        );
    }
}

