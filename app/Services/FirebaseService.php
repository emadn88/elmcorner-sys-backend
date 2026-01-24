<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class FirebaseService
{
    protected $projectId;
    protected $credentialsPath;
    protected $accessToken;
    protected $tokenExpiresAt;

    public function __construct()
    {
        $this->projectId = config('firebase.project_id');
        $this->credentialsPath = config('firebase.credentials_path');
    }

    /**
     * Send push notification to a single device
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string $priority
     * @return bool
     */
    public function sendNotification(
        string $fcmToken,
        string $title,
        string $body,
        array $data = [],
        string $priority = 'normal'
    ): bool {
        if (empty($fcmToken)) {
            Log::warning('FCM token is empty');
            return false;
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::error('Failed to get Firebase access token');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $message = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => $priority === 'high' ? 'high' : 'normal',
                    'notification' => [
                        'sound' => 'notification_sound',
                        'channel_id' => 'support_alerts',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->timeout(config('firebase.fcm.timeout', 30))
                ->post($url, $message);

            if ($response->successful()) {
                Log::info('FCM notification sent successfully', [
                    'token' => substr($fcmToken, 0, 20) . '...',
                    'title' => $title,
                ]);
                return true;
            }

            Log::error('FCM notification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('FCM notification exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send push notification to multiple devices
     *
     * @param array $fcmTokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string $priority
     * @return array ['success' => int, 'failed' => int]
     */
    public function sendBatchNotifications(
        array $fcmTokens,
        string $title,
        string $body,
        array $data = [],
        string $priority = 'normal'
    ): array {
        $success = 0;
        $failed = 0;

        foreach ($fcmTokens as $token) {
            if ($this->sendNotification($token, $title, $body, $data, $priority)) {
                $success++;
            } else {
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
        ];
    }

    /**
     * Get OAuth2 access token for Firebase
     *
     * @return string|null
     */
    protected function getAccessToken(): ?string
    {
        // Check if we have a valid cached token
        if ($this->accessToken && $this->tokenExpiresAt && $this->tokenExpiresAt->isFuture()) {
            return $this->accessToken;
        }

        if (!File::exists($this->credentialsPath)) {
            Log::error('Firebase credentials file not found', [
                'path' => $this->credentialsPath,
            ]);
            return null;
        }

        $credentials = json_decode(File::get($this->credentialsPath), true);

        if (!$credentials) {
            Log::error('Failed to parse Firebase credentials');
            return null;
        }

        $jwt = $this->createJWT($credentials);
        if (!$jwt) {
            return null;
        }

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();
                $this->accessToken = $tokenData['access_token'];
                $this->tokenExpiresAt = Carbon::now()->addSeconds($tokenData['expires_in'] - 60); // 1 min buffer

                return $this->accessToken;
            }

            Log::error('Failed to get Firebase access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception getting Firebase access token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create JWT for service account authentication
     *
     * @param array $credentials
     * @return string|null
     */
    protected function createJWT(array $credentials): ?string
    {
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;

        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if (!$privateKey) {
            Log::error('Failed to load Firebase private key');
            return null;
        }

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::error('Failed to sign JWT');
            openssl_free_key($privateKey);
            return null;
        }

        openssl_free_key($privateKey);

        $signatureEncoded = $this->base64UrlEncode($signature);

        return $signatureInput . '.' . $signatureEncoded;
    }

    /**
     * Base64 URL encode
     *
     * @param string $data
     * @return string
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
