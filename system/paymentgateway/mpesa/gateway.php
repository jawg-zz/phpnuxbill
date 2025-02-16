<?php

/**
 * M-Pesa Payment Gateway for PHPNuxBill
 * @author Spidmax Technologies
 * @copyright 2024
 */

class Mpesa {
    public function __construct()
    {
        global $config;
        $this->config = $config;
    }

    public function description()
    {
        return [
            'name' => 'M-Pesa Payment Gateway',
            'author' => 'Spidmax Technologies',
            'version' => '1.0',
            'description' => 'M-Pesa STK Push Integration for PHPNuxBill'
        ];
    }

    private function getAccessToken()
    {
        $consumerKey = $this->config['mpesa_consumer_key'];
        $consumerSecret = $this->config['mpesa_consumer_secret'];
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        
        $ch = curl_init('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        return $result['access_token'] ?? null;
    }

    public function createPayment($trx)
    {
        global $config;
        
        try {
            if(empty($this->config['mpesa_consumer_key']) || 
               empty($this->config['mpesa_consumer_secret']) || 
               empty($this->config['mpesa_shortcode']) || 
               empty($this->config['mpesa_passkey'])) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway not configured properly'
                ];
            }

            $phone = $this->formatPhoneNumber($trx['phone']);
            if(!$phone) {
                return [
                    'success' => false,
                    'message' => 'Invalid phone number format'
                ];
            }

            $timestamp = date('YmdHis');
            $password = base64_encode($this->config['mpesa_shortcode'] . $this->config['mpesa_passkey'] . $timestamp);
            
            $token = $this->getAccessToken();
            if(!$token) {
                return [
                    'success' => false,
                    'message' => 'Could not connect to M-Pesa'
                ];
            }

            $data = [
                'BusinessShortCode' => $this->config['mpesa_shortcode'],
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => ceil($trx['price']),
                'PartyA' => $phone,
                'PartyB' => $this->config['mpesa_shortcode'],
                'PhoneNumber' => $phone,
                'CallBackURL' => U . 'plugin/mpesa/callback',
                'AccountReference' => $trx['id'],
                'TransactionDesc' => 'Payment for Order #' . $trx['id']
            ];

            $ch = curl_init('https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);

            if(isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
                // Store checkout request ID for verification
                $d = ORM::for_table('tbl_payment_gateway')
                    ->where('username', $trx['username'])
                    ->find_one();
                $d->gateway_trx_id = $result['CheckoutRequestID'];
                $d->save();

                return [
                    'success' => true,
                    'message' => 'Please check your phone to complete payment',
                    'redirect_url' => U . 'order/view/' . $trx['id']
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $result['errorMessage'] ?? 'Failed to initiate payment'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    private function formatPhoneNumber($phone)
    {
        // Remove any non-digit characters
        $phone = preg_replace('/\D/', '', $phone);
        
        // Check if number starts with 0 or 254
        if(strlen($phone) == 10 && substr($phone, 0, 1) == '0') {
            $phone = '254' . substr($phone, 1);
        } else if(strlen($phone) == 12 && substr($phone, 0, 3) == '254') {
            return $phone;
        }
        
        return false;
    }

    public function handleCallback($data)
    {
        _log('M-Pesa Callback: ' . json_encode($data));
        
        try {
            if(isset($data['Body']['stkCallback'])) {
                $callback = $data['Body']['stkCallback'];
                $requestId = $callback['CheckoutRequestID'];
                
                $d = ORM::for_table('tbl_payment_gateway')
                    ->where('gateway_trx_id', $requestId)
                    ->find_one();
                
                if($d) {
                    if($callback['ResultCode'] == 0) {
                        // Payment successful
                        $amount = 0;
                        foreach($callback['CallbackMetadata']['Item'] as $item) {
                            if($item['Name'] == 'Amount') {
                                $amount = $item['Value'];
                            }
                        }
                        
                        $d->payment_status = 'Success';
                        $d->amount_in = $amount;
                        $d->save();
                        
                        // Process the order
                        Package::rechargeUser($d['username'], $amount, $d['plan_id']);
                        
                        return true;
                    } else {
                        // Payment failed
                        $d->payment_status = 'Failed';
                        $d->save();
                    }
                }
            }
        } catch (Exception $e) {
            _log('M-Pesa Callback Error: ' . $e->getMessage());
        }
        
        return false;
    }
}