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

    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->baseUrl = $this->getBaseUrl();
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
        $timestamp = date('YmdHis');
        $password = $this->generatePassword($timestamp);

        $payload = [
            'BusinessShortCode' => $this->config['shortcode'],
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->config['shortcode'],
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
            'BusinessShortCode' => $this->config['shortcode'],
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
            $this->config['consumer_key'] . ':' . 
            $this->config['consumer_secret']
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
            $this->config['shortcode'] . 
            $this->config['passkey'] . 
            $timestamp
        );
    }

    /**
     * Gets the appropriate API base URL
     */
    private function getBaseUrl(): string
    {
        return $this->config['environment'] === 'production'
            ? 'https://api.safaricom.co.ke/'
            : 'https://sandbox.safaricom.co.ke/';
    }

    /**
     * Validates the configuration
     */
    private function validateConfig(array $config): void
    {
        $required = ['consumer_key', 'consumer_secret', 'shortcode', 'passkey', 'environment'];
        
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new PaymentException(
                    "Missing required configuration: $field",
                    PaymentException::INVALID_CONFIGURATION,
                    ['field' => $field]
                );
            }
        }
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
    
    const PENDING_STATUS = 0;
    const PAID_STATUS = 1;
    const FAILED_STATUS = 2;
    const EXPIRED_STATUS = 3;
    
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
    
    /**
     * Additional payment context data
     */
    private array $context;
    
    /**
     * Creates a new PaymentException instance
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param array $context Additional context data
     * @param Throwable|null $previous Previous exception
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
}

