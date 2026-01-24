<?php

namespace App\Services;

use App\Services\WhatsApp\WhatsAppInterface;
use App\Services\WhatsApp\Drivers\TwilioDriver;
use App\Services\WhatsApp\Drivers\MetaDriver;
use App\Services\WhatsApp\Drivers\NullDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $driver;

    public function __construct()
    {
        $provider = config('whatsapp.provider', 'null');

        switch ($provider) {
            case 'twilio':
                $this->driver = new TwilioDriver();
                break;
            case 'meta':
                $this->driver = new MetaDriver();
                break;
            case 'null':
            default:
                $this->driver = new NullDriver();
                break;
        }
    }

    /**
     * Send a simple text message
     */
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = []): bool
    {
        $success = $this->driver->sendMessage($phone, $message, $templateId, $params);

        // Log to database
        $this->logMessage($phone, 'reminder', $success ? 'sent' : 'failed', $success ? null : 'Failed to send message');

        return $success;
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(string $phone, string $templateName, array $variables = []): bool
    {
        $success = $this->driver->sendTemplateMessage($phone, $templateName, $variables);

        // Determine message type from template name
        $messageType = $this->getMessageTypeFromTemplate($templateName);

        // Log to database
        $this->logMessage($phone, $messageType, $success ? 'sent' : 'failed', $success ? null : 'Failed to send template message');

        return $success;
    }

    /**
     * Log message to whatsapp_logs table
     */
    protected function logMessage(string $phone, string $messageType, string $status, ?string $error = null): void
    {
        try {
            DB::table('whatsapp_logs')->insert([
                'recipient' => $phone,
                'message_type' => $messageType,
                'status' => $status,
                'sent_at' => $status === 'sent' ? now() : null,
                'error' => $error,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log WhatsApp message', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get message type from template name
     */
    protected function getMessageTypeFromTemplate(string $templateName): string
    {
        $mapping = [
            'lesson_reminder' => 'reminder',
            'package_finished' => 'bill',
            'bill_sent' => 'bill',
            'duty_assigned' => 'duty',
            'report_ready' => 'report',
            'reactivation_offer' => 'reactivation',
        ];

        return $mapping[$templateName] ?? 'reminder';
    }
}
