<?php

namespace App\Services;

use App\Services\WhatsApp\WhatsAppInterface;
use App\Services\WhatsApp\Drivers\TwilioDriver;
use App\Services\WhatsApp\Drivers\MetaDriver;
use App\Services\WhatsApp\Drivers\WasenderDriver;
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
            case 'wasender':
                $this->driver = new WasenderDriver();
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
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = [], ?int $packageId = null, ?string $messageType = null): bool
    {
        $success = $this->driver->sendMessage($phone, $message, $templateId, $params);

        // Determine message type - if packageId is provided and no explicit type, default to 'bill' for package notifications
        $logMessageType = $messageType ?? ($packageId ? 'bill' : 'reminder');

        // Log to database
        $this->logMessage($phone, $logMessageType, $success ? 'sent' : 'failed', $success ? null : 'Failed to send message', $packageId);

        // Send copy to monitoring number if enabled
        $this->sendMonitoringCopy($phone, $message, $success);

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

        // Send copy to monitoring number if enabled
        $formattedMessage = $this->formatTemplateMessage($templateName, $variables);
        $this->sendMonitoringCopy($phone, $formattedMessage, $success);

        return $success;
    }

    /**
     * Send an image
     */
    public function sendImage(string $phone, string $imagePath, ?string $caption = null, ?string $messageType = 'trial_image'): bool
    {
        $success = $this->driver->sendImage($phone, $imagePath, $caption);

        // Log to database
        $this->logMessage($phone, $messageType, $success ? 'sent' : 'failed', $success ? null : 'Failed to send image');

        return $success;
    }

    /**
     * Log message to whatsapp_logs table
     */
    protected function logMessage(string $phone, string $messageType, string $status, ?string $error = null, ?int $packageId = null): void
    {
        try {
            DB::table('whatsapp_logs')->insert([
                'recipient' => $phone,
                'package_id' => $packageId,
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
                'package_id' => $packageId,
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

    /**
     * Format template message for monitoring
     */
    protected function formatTemplateMessage(string $templateName, array $variables): string
    {
        $template = config("whatsapp.templates.{$templateName}");

        if (!$template) {
            return "Template: {$templateName}";
        }

        $message = $template;
        foreach ($variables as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }

        return $message;
    }

    /**
     * Send a copy of the message to monitoring number
     */
    protected function sendMonitoringCopy(string $originalPhone, string $message, bool $success): void
    {
        try {
            $monitoringEnabled = config('whatsapp.monitoring.enabled', true);
            $monitoringPhone = config('whatsapp.monitoring.phone');

            if (!$monitoringEnabled || !$monitoringPhone) {
                return;
            }

            // Format monitoring message
            $status = $success ? '✅ تم الإرسال' : '❌ فشل الإرسال';
            $monitoringMessage = "📱 نسخة من الرسالة المرسلة:\n\n";
            $monitoringMessage .= "👤 إلى: {$originalPhone}\n";
            $monitoringMessage .= "📊 الحالة: {$status}\n";
            $monitoringMessage .= "⏰ الوقت: " . now()->format('Y-m-d H:i:s') . "\n\n";
            $monitoringMessage .= "📝 محتوى الرسالة:\n";
            $monitoringMessage .= "─────────────────\n";
            $monitoringMessage .= $message;
            $monitoringMessage .= "\n─────────────────";

            // Send to monitoring number (don't log this as it might cause infinite loop)
            $this->driver->sendMessage($monitoringPhone, $monitoringMessage);
        } catch (\Exception $e) {
            // Silently fail monitoring to not break main message sending
            Log::warning('Failed to send monitoring copy', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
