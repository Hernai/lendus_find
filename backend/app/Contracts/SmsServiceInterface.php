<?php

namespace App\Contracts;

/**
 * Interface for SMS/messaging service providers.
 *
 * Allows swapping between Twilio, MessageBird, or other providers
 * while maintaining consistent API for the application.
 */
interface SmsServiceInterface
{
    /**
     * Send an SMS message.
     *
     * @param string $to Destination phone number (E.164 format)
     * @param string $message Message content
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendSms(string $to, string $message): array;

    /**
     * Send a WhatsApp message.
     *
     * @param string $to Destination phone number (E.164 format)
     * @param string $message Message content
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendWhatsApp(string $to, string $message): array;

    /**
     * Check if the service is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Check if SMS capability is available.
     */
    public function hasSmsCapability(): bool;

    /**
     * Check if WhatsApp capability is available.
     */
    public function hasWhatsAppCapability(): bool;
}
