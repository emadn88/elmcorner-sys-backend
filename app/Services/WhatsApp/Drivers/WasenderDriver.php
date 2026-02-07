<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WasenderDriver implements WhatsAppInterface
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('whatsapp.wasender.api_key');
        $this->baseUrl = config('whatsapp.wasender.base_url', 'https://wasenderapi.com/api');
    }

    /**
     * Send a simple text message via Wasender API
     */
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = []): bool
    {
        try {
            if (!$this->apiKey) {
                Log::error('Wasender API key not configured');
                return false;
            }

            // Remove + from phone number for Wasender (they use E.164 without +)
            $phoneNumber = ltrim($phone, '+');

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/send-message", [
                'to' => $phoneNumber,
                'text' => $message,
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Wasender WhatsApp send failed', [
                'phone' => $phone,
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Wasender WhatsApp exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a template message via Wasender API
     */
    public function sendTemplateMessage(string $phone, string $templateName, array $variables = []): bool
    {
        $template = config("whatsapp.templates.{$templateName}");

        if (!$template) {
            Log::error("WhatsApp template not found: {$templateName}");
            return false;
        }

        // Replace template variables
        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $this->sendMessage($phone, $message);
    }

    /**
     * Send an image via Wasender API
     */
    public function sendImage(string $phone, string $imagePath, ?string $caption = null): bool
    {
        try {
            if (!$this->apiKey) {
                Log::error('Wasender API key not configured');
                return false;
            }

            // Remove + from phone number for Wasender
            $phoneNumber = ltrim($phone, '+');

            // Check if imagePath is a URL or file path
            $imageUrl = $imagePath;
            if (!filter_var($imagePath, FILTER_VALIDATE_URL)) {
                // It's a file path, need to make it publicly accessible
                if (file_exists($imagePath)) {
                    $imageUrl = asset(str_replace(public_path(), '', $imagePath));
                }
            }

            $payload = [
                'to' => $phoneNumber,
                'image' => $imageUrl,
            ];

            if ($caption) {
                $payload['caption'] = $caption;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/send-image", $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('Wasender WhatsApp image send failed', [
                'phone' => $phone,
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Wasender WhatsApp image exception', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
