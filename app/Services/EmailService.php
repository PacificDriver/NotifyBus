<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Отправить email
     */
    public function send(string $to, string $subject, string $body, array $metadata = []): bool
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject)
                        ->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        );
            });

            Log::info("Email sent successfully", [
                'to' => $to,
                'subject' => $subject,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send email", [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Отправить email с HTML шаблоном
     */
    public function sendHtml(string $to, string $subject, string $htmlBody, array $metadata = []): bool
    {
        try {
            Mail::send([], [], function ($message) use ($to, $subject, $htmlBody) {
                $message->to($to)
                        ->subject($subject)
                        ->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        )
                        ->html($htmlBody);
            });

            Log::info("HTML Email sent successfully", [
                'to' => $to,
                'subject' => $subject,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to send HTML email", [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Проверить валидность email адреса
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}


