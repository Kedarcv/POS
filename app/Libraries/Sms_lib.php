<?php

declare(strict_types=1);

namespace App\Libraries;

use Throwable;

/**
 * WhatsApp messaging library — sends messages via WhatsApp Cloud API.
 *
 * Replaces the legacy SMS gateway. Uses the same credentials configured
 * in .env (WHATSAPP_ACCESS_TOKEN, WHATSAPP_PHONE_NUMBER_ID).
 */
class Sms_lib
{
    private string $accessToken;
    private string $phoneNumberId;

    public function __construct()
    {
        $this->accessToken   = getenv('WHATSAPP_ACCESS_TOKEN') ?: '';
        $this->phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID') ?: '';
    }

    /**
     * Send a WhatsApp text message.
     *
     * @param string $phone    Recipient phone (with country code, no + prefix)
     * @param string $message  Text message body
     *
     * @return bool  true on success, false on failure
     */
    public function sendSMS(string $phone, string $message): bool
    {
        if ($this->accessToken === '' || $this->phoneNumberId === '') {
            log_message('error', '[WhatsApp] Not configured — message to ' . $phone . ' skipped');

            return false;
        }

        if (empty($phone) || empty($message)) {
            log_message('error', '[WhatsApp] Empty phone or message — skipped');

            return false;
        }

        // Normalise phone: strip +, spaces, dashes
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Ensure leading country code (default to 263 = Zimbabwe if local)
        if (strlen($phone) <= 9) {
            $phone = '263' . ltrim($phone, '0');
        }

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $phone,
                'type'              => 'text',
                'text'              => [
                    'preview_url' => false,
                    'body'        => $message,
                ],
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => "https://graph.facebook.com/v21.0/{$this->phoneNumberId}/messages",
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $body     = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error) {
                log_message('error', '[WhatsApp] cURL error: ' . $error . ' — to: ' . $phone);

                return false;
            }

            $decoded = json_decode($body, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                log_message('info', '[WhatsApp] Message sent to ' . $phone . ' (ID: ' . ($decoded['messages'][0]['id'] ?? 'unknown') . ')');

                return true;
            }

            log_message('error', '[WhatsApp] API error (HTTP ' . $httpCode . '): ' . $body . ' — to: ' . $phone);

            return false;
        } catch (Throwable $e) {
            log_message('error', '[WhatsApp] Exception: ' . $e->getMessage() . ' — to: ' . $phone);

            return false;
        }
    }
}
