<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppInterface;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;

class TwilioDriver implements WhatsAppInterface
{
    protected $client;
    protected $from;

    public function __construct()
    {
        $accountSid = config('whatsapp.twilio.account_sid');
        $authToken = config('whatsapp.twilio.auth_token');
        $this->from = config('whatsapp.twilio.from');

        if ($accountSid && $authToken) {
            $this->client = new TwilioClient($accountSid, $authToken);
        }
    }

    /**
     * Send a simple text message via Twilio
     */
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = []): bool
    {
        try {
            if (!$this->client) {
                Log::error('Twilio client not initialized');
                return false;
            }

            $this->client->messages->create(
                "whatsapp:{$phone}",
                [
                    'from' => "whatsapp:{$this->from}",
                    'body' => $message,
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::error('Twilio WhatsApp send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a template message via Twilio
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
}
