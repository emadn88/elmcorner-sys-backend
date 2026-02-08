<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class PayPalService
{
    protected $clientId;
    protected $clientSecret;
    protected $mode;
    protected $baseUrl;

    public function __construct()
    {
        // Try to get from env first, then config (in case config cache is stale)
        $this->clientId = env('PAYPAL_CLIENT_ID') ?: config('paypal.client_id');
        $this->clientSecret = env('PAYPAL_CLIENT_SECRET') ?: config('paypal.client_secret');
        $this->mode = env('PAYPAL_MODE') ?: config('paypal.mode', 'sandbox');
        
        // Validate credentials
        if (empty($this->clientId) || empty($this->clientSecret)) {
            \Log::warning('PayPal credentials are empty. Client ID: ' . ($this->clientId ? 'set' : 'empty') . ', Secret: ' . ($this->clientSecret ? 'set' : 'empty'));
        }
        
        $this->baseUrl = $this->mode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
    }

    /**
     * Get access token from PayPal
     */
    protected function getAccessToken()
    {
        try {
            // Validate credentials are set
            if (empty($this->clientId) || empty($this->clientSecret)) {
                Log::error('PayPal credentials are missing. Client ID: ' . ($this->clientId ? 'set' : 'empty') . ', Client Secret: ' . ($this->clientSecret ? 'set' : 'empty'));
                throw new \Exception('PayPal credentials are not configured. Please check your .env file.');
            }

            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'en_US',
                ])
                ->withoutVerifying() // Disable SSL verification for development (remove in production)
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['access_token'])) {
                    return $data['access_token'];
                }
                Log::error('PayPal Access Token Response missing access_token: ' . json_encode($data));
                throw new \Exception('PayPal response missing access token');
            }

            $statusCode = $response->status();
            $errorBody = $response->body();
            $errorJson = $response->json();
            
            Log::error('PayPal Access Token Error - Status: ' . $statusCode);
            Log::error('PayPal Access Token Error - Body: ' . $errorBody);
            Log::error('PayPal Access Token Error - JSON: ' . json_encode($errorJson));
            
            $errorMessage = 'Failed to get PayPal access token';
            if (isset($errorJson['error_description'])) {
                $errorMessage .= ': ' . $errorJson['error_description'];
            } elseif (isset($errorJson['error'])) {
                $errorMessage .= ': ' . $errorJson['error'];
            }
            
            throw new \Exception($errorMessage);
        } catch (\Exception $e) {
            Log::error('PayPal Access Token Exception: ' . $e->getMessage());
            Log::error('PayPal Access Token Exception Trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Create a PayPal payment
     */
    public function createPayment($amount, $currency, $description, $returnUrl, $cancelUrl, $invoiceId = null)
    {
        try {
            $accessToken = $this->getAccessToken();

            $paymentData = [
                'intent' => 'sale',
                'payer' => [
                    'payment_method' => 'paypal',
                ],
                'transactions' => [
                    [
                        'amount' => [
                            'total' => number_format($amount, 2, '.', ''),
                            'currency' => $currency,
                        ],
                        'description' => $description,
                    ],
                ],
                'redirect_urls' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
                'application_context' => [
                    'brand_name' => 'ElmCorner Academy',
                    'landing_page' => 'billing', // Shows billing page which includes guest checkout option
                    'user_action' => 'pay_now', // Shows "Pay Now" button
                ],
            ];

            if ($invoiceId) {
                $paymentData['transactions'][0]['invoice_number'] = $invoiceId;
            }

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->withoutVerifying() // Disable SSL verification for development (remove in production)
                ->post($this->baseUrl . '/v1/payments/payment', $paymentData);

            if ($response->successful()) {
                $payment = $response->json();
                $approvalUrl = null;

                // Find approval URL in links
                foreach ($payment['links'] as $link) {
                    if ($link['rel'] === 'approval_url') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                return [
                    'success' => true,
                    'payment_id' => $payment['id'],
                    'approval_url' => $approvalUrl,
                ];
            }

            $error = $response->json();
            Log::error('PayPal Payment Creation Error: ' . json_encode($error));
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Failed to create PayPal payment',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Payment Creation Exception: ' . $e->getMessage());
            Log::error('PayPal Error Details: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute a PayPal payment
     */
    public function executePayment($paymentId, $payerId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->withoutVerifying() // Disable SSL verification for development (remove in production)
                ->post($this->baseUrl . '/v1/payments/payment/' . $paymentId . '/execute', [
                    'payer_id' => $payerId,
                ]);

            if ($response->successful()) {
                $payment = $response->json();

                if ($payment['state'] === 'approved') {
                    $transaction = $payment['transactions'][0];
                    $transactionId = null;

                    // Extract transaction ID from related resources
                    if (isset($transaction['related_resources'][0]['sale']['id'])) {
                        $transactionId = $transaction['related_resources'][0]['sale']['id'];
                    }

                    return [
                        'success' => true,
                        'payment_id' => $payment['id'],
                        'transaction_id' => $transactionId,
                        'amount' => $transaction['amount']['total'],
                        'currency' => $transaction['amount']['currency'],
                        'state' => $payment['state'],
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'Payment not approved. State: ' . $payment['state'],
                ];
            }

            $error = $response->json();
            Log::error('PayPal Payment Execution Error: ' . json_encode($error));
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Failed to execute PayPal payment',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Payment Execution Exception: ' . $e->getMessage());
            Log::error('PayPal Error Details: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get payment details
     */
    public function getPayment($paymentId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->withoutVerifying() // Disable SSL verification for development (remove in production)
                ->get($this->baseUrl . '/v1/payments/payment/' . $paymentId);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('PayPal Get Payment Error: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('PayPal Get Payment Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create order for PayPal Smart Buttons
     */
    public function createOrder($amount, $currency, $description, $invoiceId = null)
    {
        try {
            $accessToken = $this->getAccessToken();

            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                        'description' => $description,
                    ],
                ],
                'application_context' => [
                    'brand_name' => 'ElmCorner Academy',
                    'landing_page' => 'BILLING',
                    'user_action' => 'PAY_NOW',
                ],
            ];

            if ($invoiceId) {
                $orderData['purchase_units'][0]['invoice_id'] = $invoiceId;
            }

            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->withoutVerifying() // Disable SSL verification for development (remove in production)
                ->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if ($response->successful()) {
                $order = $response->json();
                return [
                    'success' => true,
                    'order_id' => $order['id'],
                ];
            }

            $error = $response->json();
            Log::error('PayPal Order Creation Error: ' . json_encode($error));
            return [
                'success' => false,
                'error' => $error['message'] ?? 'Failed to create PayPal order',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Order Creation Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Capture order for PayPal Smart Buttons
     */
    public function captureOrder($orderId)
    {
        try {
            $accessToken = $this->getAccessToken();

            // PayPal v2 API capture endpoint - POST with empty JSON body
            $url = $this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture';
            
            // Use Guzzle directly to have full control over the request
            $client = new Client([
                'verify' => false, // Disable SSL verification for development
            ]);
            
            try {
                $guzzleResponse = $client->request('POST', $url, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                        'Prefer' => 'return=representation',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => '{}',
                ]);
                
                $statusCode = $guzzleResponse->getStatusCode();
                $responseBody = $guzzleResponse->getBody()->getContents();
                $order = json_decode($responseBody, true);
                
                // Create a response object compatible with our existing code
                $response = new class($statusCode, $order) {
                    public $statusCode;
                    public $data;
                    
                    public function __construct($statusCode, $data) {
                        $this->statusCode = $statusCode;
                        $this->data = $data;
                    }
                    
                    public function successful() {
                        return $this->statusCode >= 200 && $this->statusCode < 300;
                    }
                    
                    public function json() {
                        return $this->data;
                    }
                    
                    public function status() {
                        return $this->statusCode;
                    }
                    
                    public function body() {
                        return json_encode($this->data);
                    }
                };
            } catch (ClientException $e) {
                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 400;
                $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
                $errorData = json_decode($responseBody, true) ?: ['message' => $responseBody];
                
                $response = new class($statusCode, $errorData) {
                    public $statusCode;
                    public $data;
                    
                    public function __construct($statusCode, $data) {
                        $this->statusCode = $statusCode;
                        $this->data = $data;
                    }
                    
                    public function successful() {
                        return false;
                    }
                    
                    public function json() {
                        return $this->data;
                    }
                    
                    public function status() {
                        return $this->statusCode;
                    }
                    
                    public function body() {
                        return json_encode($this->data);
                    }
                };
            } catch (\Exception $e) {
                Log::error('PayPal Capture Exception: ' . $e->getMessage());
                throw $e;
            }

            if ($response->successful()) {
                $order = $response->json();
                
                Log::info('PayPal Capture Response: ' . json_encode($order));
                
                // Check if order is completed
                if (isset($order['status']) && $order['status'] === 'COMPLETED') {
                    if (isset($order['purchase_units'][0]['payments']['captures'][0])) {
                        $purchaseUnit = $order['purchase_units'][0];
                        $capture = $purchaseUnit['payments']['captures'][0];
                        
                        return [
                            'success' => true,
                            'order_id' => $order['id'],
                            'transaction_id' => $capture['id'],
                            'amount' => $capture['amount']['value'],
                            'currency' => $capture['amount']['currency_code'],
                            'status' => $order['status'],
                        ];
                    } else {
                        Log::error('PayPal Capture: No capture found in response');
                        return [
                            'success' => false,
                            'error' => 'No capture found in order response',
                        ];
                    }
                }

                return [
                    'success' => false,
                    'error' => 'Order not completed. Status: ' . ($order['status'] ?? 'unknown'),
                ];
            }

            $statusCode = $response->status();
            $errorBody = $response->body();
            $errorJson = $response->json();
            
            Log::error('PayPal Order Capture Error - Status: ' . $statusCode);
            Log::error('PayPal Order Capture Error - Body: ' . $errorBody);
            Log::error('PayPal Order Capture Error - JSON: ' . json_encode($errorJson));
            
            $errorMessage = 'Failed to capture PayPal order';
            if (isset($errorJson['details'][0]['description'])) {
                $errorMessage = $errorJson['details'][0]['description'];
            } elseif (isset($errorJson['message'])) {
                $errorMessage = $errorJson['message'];
            } elseif (isset($errorJson['error_description'])) {
                $errorMessage = $errorJson['error_description'];
            }
            
            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            Log::error('PayPal Order Capture Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
