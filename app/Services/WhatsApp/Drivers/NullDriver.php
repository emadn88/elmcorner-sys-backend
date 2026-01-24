<?php

namespace App\Services\WhatsApp\Drivers;

use App\Services\WhatsApp\WhatsAppInterface;

class NullDriver implements WhatsAppInterface
{
    /**
     * Send a simple text message (no-op for testing)
     */
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = []): bool
    {
        // Log for testing purposes
        \Log::info('WhatsApp NullDriver: Would send message', [
            'phone' => $phone,
            'message' => $message,
            'template_id' => $templateId,
            'params' => $params,
        ]);

        return true;
    }

    /**
     * Send a template message (no-op for testing)
     */
    public function sendTemplateMessage(string $phone, string $templateName, array $variables = []): bool
    {
        // Log for testing purposes
        \Log::info('WhatsApp NullDriver: Would send template message', [
            'phone' => $phone,
            'template' => $templateName,
            'variables' => $variables,
        ]);

        return true;
    }
}
