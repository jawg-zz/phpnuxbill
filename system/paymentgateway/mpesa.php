<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway M-Pesa
 **/

// Include the MPesaGateway class from lib directory
require_once __DIR__ . '/lib/mpesaGateway.php';

function mpesa_validate_config()
{
    global $config;
    try {
        $mpesaConfig = new MPesaConfig([
            'consumer_key' => $config['mpesa_consumer_key'] ?? '',
            'consumer_secret' => $config['mpesa_consumer_secret'] ?? '',
            'shortcode' => $config['mpesa_shortcode'] ?? '',
            'passkey' => $config['mpesa_passkey'] ?? '',
            'environment' => $_app_stage ?? 'Live' == 'Live' ? 'production' : 'sandbox'
        ]);
        
        $mpesaConfig->validate();
    } catch (PaymentException $e) {
        sendTelegram("M-Pesa payment gateway not configured: " . $e->getMessage());
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
    global $config, $_app_stage;
    
    try {
        // Create MPesa gateway instance
        $mpesa = new MPesaGateway([
            'consumer_key' => $config['mpesa_consumer_key'],
            'consumer_secret' => $config['mpesa_consumer_secret'],
            'shortcode' => $config['mpesa_shortcode'],
            'passkey' => $config['mpesa_passkey'],
            'environment' => $_app_stage == 'Live' ? 'production' : 'sandbox'
        ]);
        
        // Process phone number
        $phone = preg_replace('/\D/', '', $user['phonenumber']);
        if (!preg_match('/^(?:254|0)?(7\d{8})$/', $phone, $matches)) {
            throw PaymentException::invalidPhoneNumber($phone);
        }
        $phone = '254' . $matches[1];
        
        // Create STK Push request
        $stkPushResult = $mpesa->initiateSTKPush(
            $trx['price'],
            $phone,
            $trx['id'],
            'Payment for Order #' . $trx['id'],
            U . 'callback/mpesa'
        );

        // Save transaction details
        $transaction = ORM::for_table('tbl_payment_gateway')->find_one($trx['id']);
        $transaction->gateway_trx_id = $stkPushResult['CheckoutRequestID'];
        $transaction->pg_request = json_encode($stkPushResult);
        $transaction->expired_date = date('Y-m-d H:i:s', strtotime('+ 4 HOURS'));
        $transaction->status = MPesaConfig::PENDING_STATUS;
        $transaction->save();

        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Please check your phone to complete payment"));
        
    } catch (PaymentException $e) {
        log_error('mpesa_create_transaction', $e->getMessage(), [
            'trx_id' => $trx['id'],
            'user' => $user['username'],
            'context' => $e->getContext()
        ]);
        
        $errorMessage = match($e->getCode()) {
            PaymentException::INVALID_PHONE_NUMBER => Lang::T("Invalid phone number format. Please use a valid Kenyan phone number."),
            PaymentException::API_CONNECTION_ERROR => Lang::T("Failed to connect to M-Pesa. Please try again."),
            default => Lang::T("Failed to create transaction. Please try again.")
        };
        
        r2(U . 'order/package', 'e', $errorMessage);
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
    global $config, $_app_stage;
    
    if ($trx['status'] == MPesaConfig::PAID_STATUS) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid."));
    }

    try {
        // Check if transaction has expired
        if (is_transaction_expired($trx)) {
            r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction has expired. Please create a new one."));
        }

        $mpesa = new MPesaGateway([
            'consumer_key' => $config['mpesa_consumer_key'],
            'consumer_secret' => $config['mpesa_consumer_secret'],
            'shortcode' => $config['mpesa_shortcode'],
            'passkey' => $config['mpesa_passkey'],
            'environment' => $_app_stage == 'Live' ? 'production' : 'sandbox'
        ]);
        
        $result = $mpesa->queryTransactionStatus($trx['gateway_trx_id']);

        if ($result['ResultCode'] === '0') {
            process_successful_payment($trx, $user, $result);
            r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
        } else {
            r2(U . "order/view/" . $trx['id'], 'w', Lang::T("Transaction still unpaid."));
        }
        
    } catch (PaymentException $e) {
        log_error('mpesa_get_status', $e->getMessage(), [
            'trx_id' => $trx['id'],
            'user' => $user['username'],
            'context' => $e->getContext()
        ]);
        r2(U . "order/view/" . $trx['id'], 'e', Lang::T("Failed to check transaction status."));
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
    global $config, $_app_stage;
    
    try {
        $input = file_get_contents('php://input');
        // Log the raw callback data
        Log::put('MPESA', 'Callback received: ' . $input, '', '');
        
        $callback = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_error('mpesa_callback_parsing', 'Invalid JSON in callback', [
                'input' => $input,
                'json_error' => json_last_error_msg()
            ]);
            http_response_code(400);
            die('Invalid JSON');
        }

        // Log the parsed callback data
        Log::put('MPESA', 'Callback parsed', '', json_encode($callback));

        if (!isset($callback['Body']['stkCallback'])) {
            throw PaymentException::invalidCallback();
        }

        $result = $callback['Body']['stkCallback'];
        Log::put('MPESA', 'STK callback data', '', json_encode($result));
        
        $trx_id = $result['BillRefNumber'];
        Log::put('MPESA', 'Processing transaction: ' . $trx_id, '', '');
        
        if ($result['ResultCode'] === 0) {
            $trx = ORM::for_table('tbl_payment_gateway')
                ->where('id', $trx_id)
                ->find_one();
                
            if (!$trx) {
                Log::put('MPESA', 'Transaction not found: ' . $trx_id, '', '');
                throw PaymentException::transactionNotFound($trx_id);
            }

            Log::put('MPESA', 'Transaction found', '', json_encode($trx->as_array()));

            // Check if transaction has expired
            if (is_transaction_expired($trx)) {
                Log::put('MPESA', 'Transaction expired: ' . $trx_id, '', '');
                throw PaymentException::transactionExpired($trx_id);
            }

            $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
            if (!$user) {
                Log::put('MPESA', 'User not found for transaction: ' . $trx_id, '', '');
                throw new PaymentException(
                    'User not found for transaction: ' . $trx_id,
                    PaymentException::TRANSACTION_NOT_FOUND,
                    ['trx_id' => $trx_id]
                );
            }

            Log::put('MPESA', 'Processing payment for user: ' . $user['username'], '', '');
            
            // Extract payment details for logging
            $paymentItems = [];
            if (isset($result['CallbackMetadata']['Item']) && is_array($result['CallbackMetadata']['Item'])) {
                foreach ($result['CallbackMetadata']['Item'] as $item) {
                    if (isset($item['Name']) && isset($item['Value'])) {
                        $paymentItems[$item['Name']] = $item['Value'];
                    }
                }
            }
            
            Log::put('MPESA', 'Payment details', '', json_encode($paymentItems));
            
            process_successful_payment($trx, $user, $result);
            Log::put('MPESA', 'Payment processed successfully for transaction: ' . $trx_id, '', '');
        } else {
            log_error('mpesa_callback_failed', $result['ResultDesc'], [
                'trx_id' => $trx_id,
                'result' => $result
            ]);
        }
        
        http_response_code(200);
        die('OK');
        
    } catch (PaymentException $e) {
        log_error('mpesa_callback_error', $e->getMessage(), [
            'input' => $input ?? null,
            'context' => $e->getContext()
        ]);
        http_response_code(400);
        die('Error');
    } catch (Exception $e) {
        log_error('mpesa_callback_error', $e->getMessage(), [
            'input' => $input ?? null
        ]);
        http_response_code(400);
        die('Error');
    }
}

// Helper functions
function is_transaction_expired($trx)
{
    if (strtotime($trx['expired_date']) < time()) {
        $transaction = ORM::for_table('tbl_payment_gateway')->find_one($trx['id']);
        $transaction->status = MPesaConfig::EXPIRED_STATUS;
        $transaction->pg_paid_response = json_encode(['error' => 'Transaction expired']);
        $transaction->save();
        return true;
    }
    return false;
}

function process_successful_payment($trx, $user, $mpesaResult = null)
{
    if ($trx['status'] == MPesaConfig::PAID_STATUS) {
        Log::put('MPESA', 'Transaction already processed: ' . $trx['id'], '', '');
        return; // Already processed
    }

    Log::put('MPESA', 'Starting payment processing for transaction: ' . $trx['id'], '', '');

    // Validate payment amount
    if (isset($mpesaResult['Amount']) && $mpesaResult['Amount'] != $trx['price']) {
        Log::put('MPESA', 'Amount mismatch: expected ' . $trx['price'] . ', received ' . $mpesaResult['Amount'], '', '');
        throw PaymentException::amountMismatch($trx['price'], $mpesaResult['Amount']);
    }

    try {
        // Activate the package
        Log::put('MPESA', 'Activating package for user: ' . $user['username'] . ', plan: ' . $trx['plan_id'], '', '');
        $activated = Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], 'mpesa', 'M-Pesa');
        
        if (!$activated) {
            Log::put('MPESA', 'Package activation failed for transaction: ' . $trx['id'], '', '');
            throw PaymentException::packageActivationError('Failed to activate package');
        }
        
        Log::put('MPESA', 'Package activated successfully for user: ' . $user['username'], '', '');

        // Update transaction record
        Log::put('MPESA', 'Updating transaction record: ' . $trx['id'], '', '');
        $transaction = ORM::for_table('tbl_payment_gateway')->find_one($trx['id']);
        $transaction->pg_paid_response = $mpesaResult ? json_encode($mpesaResult) : null;
        $transaction->payment_method = 'M-Pesa';
        $transaction->payment_channel = 'STK Push';
        $transaction->paid_date = date('Y-m-d H:i:s');
        $transaction->status = MPesaConfig::PAID_STATUS;
        $transaction->save();
        
        Log::put('MPESA', 'Transaction updated to PAID: ' . $trx['id'], '', json_encode($transaction->as_array()));
        
        // Send notification if configured
        if (isset($config['mpesa_payment_notification']) && $config['mpesa_payment_notification'] == 'yes') {
            try {
                Log::put('MPESA', 'Sending payment notification for: ' . $trx['id'], '', '');
                $message = "Payment received: " . $trx['plan_name'] . " for " . $user['fullname'] . " (" . $user['username'] . "). Amount: " . $trx['price'];
                sendTelegram($message);
                Log::put('MPESA', 'Notification sent successfully', '', '');
            } catch (Exception $e) {
                Log::put('MPESA', 'Failed to send notification: ' . $e->getMessage(), '', '');
                // Continue processing even if notification fails
            }
        }
    } catch (Exception $e) {
        Log::put('MPESA', 'Error in payment processing: ' . $e->getMessage(), '', json_encode([
            'trace' => $e->getTraceAsString(),
            'transaction_id' => $trx['id'],
            'user_id' => $user['id']
        ]));
        throw $e; // Rethrow for proper error handling
    }
}

function reconcile_transactions()
{
    global $config, $_app_stage;
    
    Log::put('MPESA', 'Starting transaction reconciliation', '', '');
    
    $pending = ORM::for_table('tbl_payment_gateway')
        ->where('status', MPesaConfig::PENDING_STATUS)
        ->find_many();
    
    $count = count($pending);
    Log::put('MPESA', 'Found ' . $count . ' pending transactions', '', '');
    
    if (empty($pending)) {
        Log::put('MPESA', 'No pending transactions to reconcile', '', '');
        return;
    }
    
    // Create MPesa gateway once for all transactions
    try {
        Log::put('MPESA', 'Initializing M-Pesa gateway', '', '');
        $mpesa = new MPesaGateway([
            'consumer_key' => $config['mpesa_consumer_key'],
            'consumer_secret' => $config['mpesa_consumer_secret'],
            'shortcode' => $config['mpesa_shortcode'],
            'passkey' => $config['mpesa_passkey'],
            'environment' => $_app_stage == 'Live' ? 'production' : 'sandbox'
        ]);
        
        $processed = 0;
        $failed = 0;
        $expired = 0;
        
        foreach ($pending as $trx) {
            Log::put('MPESA', 'Reconciling transaction: ' . $trx['id'], '', json_encode([
                'transaction_id' => $trx['id'],
                'gateway_trx_id' => $trx['gateway_trx_id'],
                'amount' => $trx['price']
            ]));
            
            if (is_transaction_expired($trx)) {
                Log::put('MPESA', 'Transaction expired: ' . $trx['id'], '', '');
                $expired++;
                continue;
            }
            
            try {
                Log::put('MPESA', 'Querying transaction status: ' . $trx['gateway_trx_id'], '', '');
                $result = $mpesa->queryTransactionStatus($trx['gateway_trx_id']);
                Log::put('MPESA', 'Query result: ' . json_encode($result), '', '');
                
                if ($result['ResultCode'] === '0') {
                    Log::put('MPESA', 'Transaction successful, processing payment: ' . $trx['id'], '', '');
                    $user = ORM::for_table('tbl_customers')->find_one($trx['user_id']);
                    if ($user) {
                        process_successful_payment($trx, $user, $result);
                        $processed++;
                        Log::put('MPESA', 'Successfully processed transaction: ' . $trx['id'], '', '');
                    } else {
                        Log::put('MPESA', 'User not found for transaction: ' . $trx['id'], '', '');
                        $failed++;
                    }
                } else {
                    Log::put('MPESA', 'Transaction still pending: ' . $trx['id'] . ', ResultCode: ' . $result['ResultCode'], '', '');
                }
            } catch (PaymentException $e) {
                log_error('reconciliation_error', $e->getMessage(), [
                    'trx_id' => $trx['id'],
                    'context' => $e->getContext()
                ]);
                $failed++;
            } catch (Exception $e) {
                log_error('reconciliation_error', $e->getMessage(), [
                    'trx_id' => $trx['id']
                ]);
                $failed++;
            }
        }
        
        Log::put('MPESA', 'Reconciliation completed', '', json_encode([
            'total' => $count,
            'processed' => $processed,
            'expired' => $expired,
            'failed' => $failed
        ]));
    } catch (Exception $e) {
        Log::put('MPESA', 'Failed to initialize M-Pesa gateway: ' . $e->getMessage(), '', '');
        log_error('reconciliation_init_error', $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
    }
}

function log_error($action, $message, array $context = [])
{
    Log::put('MPESA', $action . ': ' . $message, '', json_encode([
        'message' => $message,
        'context' => $context
    ]));
    sendTelegram("M-Pesa $action: $message\n\n" . json_encode($context, JSON_PRETTY_PRINT));
}
