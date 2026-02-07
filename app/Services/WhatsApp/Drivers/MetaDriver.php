<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaDriver implements WhatsAppInterface
{
    protected $token;
    protected $phoneId;
    protected $businessAccountId;

    public function __construct()
    {
        $this->token = config('whatsapp.meta.token');
        $this->phoneId = config('whatsapp.meta.phone_id');
        $this->businessAccountId = config('whatsapp.meta.business_account_id');
    }

    /**
     * Send a simple text message via Meta WhatsApp API
     */
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = []): bool
    {
        try {
            if (!$this->token || !$this->phoneId) {
                Log::error('Meta WhatsApp credentials not configured');
                return false;
            }

            $url = "https://graph.facebook.com/v18.0/{$this->phoneId}/messages";

            $response = Http::withToken($this->token)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Meta WhatsApp send failed', [
                'phone' => $phone,
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a template message via Meta WhatsApp API
     */
    public function sendTemplateMessage(string $phone, string $templateName, array $variables = []): bool
    {
        try {
            if (!$this->token || !$this->phoneId) {
                Log::error('Meta WhatsApp credentials not configured');
                return false;
            }

            // Convert variables to Meta format
            $components = [];
            if (!empty($variables)) {
                $parameters = [];
                foreach ($variables as $value) {
                    $parameters[] = ['type' => 'text', 'text' => $value];
                }
                $components[] = [
                    'type' => 'body',
                    'parameters' => $parameters,
                ];
            }

            $url = "https://graph.facebook.com/v18.0/{$this->phoneId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'ar'], // Default to Arabic, can be made configurable
                ],
            ];

            if (!empty($components)) {
                $payload['template']['components'] = $components;
            }

            $response = Http::withToken($this->token)->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('Meta WhatsApp template send failed', [
                'phone' => $phone,
                'template' => $templateName,
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp template exception', [
                'phone' => $phone,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send an image via Meta WhatsApp API
     */
    public function sendImage(string $phone, string $imagePath, ?string $caption = null): bool
    {
        try {
            if (!$this->token || !$this->phoneId) {
                Log::error('Meta WhatsApp credentials not configured');
                return false;
            }

            // Check if imagePath is a URL or file path
            $imageUrl = $imagePath;
            if (!filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // It's a file path, need to upload to a publicly accessible URL
                // For now, assume it's already a public URL or use storage URL
                if (file_exists($imagePath)) {
                    // If it's a local file, we need to make it accessible
                    // For Meta API, we need a publicly accessible URL
                    $imageUrl = asset(str_replace(public_path(), '', $imagePath));
                }
            }

            $url = "https://graph.facebook.com/v18.0/{$this->phoneId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $phone,
                'type' => 'image',
                'image' => [
                    'link' => $imageUrl,
                ],
            ];

            if ($caption) {
                $payload['image']['caption'] = $caption;
            }

            $response = Http::withToken($this->token)->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('Meta WhatsApp image send failed', [
                'phone' => $phone,
                'response' => $response->json(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Meta WhatsApp image exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
