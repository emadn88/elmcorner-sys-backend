<?php

namespace App\Services\WhatsApp;

interface WhatsAppInterface
{
    /**
     * Send a simple text message
     *
     * @param string $phone Phone number in E.164 format
     * @param string $message Message text
     * @param string|null $templateId Optional template ID
     * @param array $params Optional parameters for template
     * @return bool Success status
     */
    public function sendMessage(string $phone, string $message, ?string $templateId = null, array $params = []): bool;

    /**
     * Send a template message
     *
     * @param string $phone Phone number in E.164 format
     * @param string $templateName Template name from config
     * @param array $variables Template variables
     * @return bool Success status
     */
    public function sendTemplateMessage(string $phone, string $templateName, array $variables = []): bool;

    /**
     * Send an image
     *
     * @param string $phone Phone number in E.164 format
     * @param string $imagePath Path to image file or image URL
     * @param string|null $caption Optional caption for the image
     * @return bool Success status
     */
    public function sendImage(string $phone, string $imagePath, ?string $caption = null): bool;
}
