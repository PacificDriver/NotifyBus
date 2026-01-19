<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\Setting;

class EmailService
{
    /**
     * Применить настройки email из БД перед отправкой
     * 
     * @param string|null $group Группа настроек email (email или startport_email)
     */
    protected function applyEmailSettingsFromDb(?string $group = null): void
    {
        try {
            // Если группа не указана, используем обычную группу email
            $prefix = $group && $group !== 'email' ? "{$group}_" : 'email_';
            
            $host = Setting::get($prefix . 'host');
            $port = Setting::get($prefix . 'port');
            $username = Setting::get($prefix . 'username');
            $password = Setting::get($prefix . 'password');
            $encryption = Setting::get($prefix . 'encryption');
            $fromAddress = Setting::get($prefix . 'from_address');
            $fromName = Setting::get($prefix . 'from_name');

            if (!empty($host)) {
                Config::set('mail.mailers.smtp.host', $host);
            }

            if (!empty($port)) {
                Config::set('mail.mailers.smtp.port', (int) $port);
            }

            if (!empty($username)) {
                Config::set('mail.mailers.smtp.username', $username);
            }

            if (!empty($password)) {
                Config::set('mail.mailers.smtp.password', $password);
            }

            if (!empty($encryption)) {
                Config::set('mail.mailers.smtp.encryption', $encryption);
            }

            if (!empty($fromAddress)) {
                Config::set('mail.from.address', $fromAddress);
            }

            if (!empty($fromName)) {
                Config::set('mail.from.name', $fromName);
            }

            // Убеждаемся, что используется SMTP, а не log
            if (config('mail.default') === 'log') {
                Config::set('mail.default', 'smtp');
            }
        } catch (\Exception $e) {
            Log::warning('EmailService: failed to apply email settings from DB', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Отправить email
     * 
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $body Текст письма
     * @param array $metadata Метаданные для логирования
     * @param string|null $emailGroup Группа настроек email (email или startport_email)
     */
    public function send(string $to, string $subject, string $body, array $metadata = [], ?string $emailGroup = null): bool
    {
        try {
            // Применяем настройки из БД перед отправкой
            $this->applyEmailSettingsFromDb($emailGroup);

            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)
                        ->subject($subject)
                        ->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        );
            });

            Log::info("✅ EMAIL ОТПРАВЛЕН УСПЕШНО", [
                'channel' => 'email',
                'to' => $to,
                'from' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'subject' => $subject,
                'body_length' => strlen($body),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
                'smtp_username' => config('mail.mailers.smtp.username') ? '***' : null,
                'metadata' => $metadata,
                'timestamp' => now()->toDateTimeString(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("❌ EMAIL НЕ ОТПРАВЛЕН - ОШИБКА", [
                'channel' => 'email',
                'to' => $to,
                'from' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'subject' => $subject,
                'body_length' => strlen($body),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
                'smtp_username' => config('mail.mailers.smtp.username') ? '***' : null,
                'mailer' => config('mail.default'),
                'metadata' => $metadata,
                'timestamp' => now()->toDateTimeString(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Отправить email с HTML шаблоном
     * 
     * @param string $to Email получателя
     * @param string $subject Тема письма
     * @param string $htmlBody HTML тело письма
     * @param array $metadata Метаданные для логирования
     * @param string|null $emailGroup Группа настроек email (email или startport_email)
     */
    public function sendHtml(string $to, string $subject, string $htmlBody, array $metadata = [], ?string $emailGroup = null): bool
    {
        try {
            // Применяем настройки из БД перед отправкой
            $this->applyEmailSettingsFromDb($emailGroup);

            Mail::send([], [], function ($message) use ($to, $subject, $htmlBody) {
                $message->to($to)
                        ->subject($subject)
                        ->from(
                            config('mail.from.address'),
                            config('mail.from.name')
                        )
                        ->html($htmlBody);
            });

            Log::info("✅ HTML EMAIL ОТПРАВЛЕН УСПЕШНО", [
                'channel' => 'email',
                'type' => 'html',
                'to' => $to,
                'from' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'subject' => $subject,
                'body_length' => strlen($htmlBody),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
                'smtp_username' => config('mail.mailers.smtp.username') ? '***' : null,
                'metadata' => $metadata,
                'timestamp' => now()->toDateTimeString(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("❌ HTML EMAIL НЕ ОТПРАВЛЕН - ОШИБКА", [
                'channel' => 'email',
                'type' => 'html',
                'to' => $to,
                'from' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'subject' => $subject,
                'body_length' => strlen($htmlBody),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
                'smtp_username' => config('mail.mailers.smtp.username') ? '***' : null,
                'mailer' => config('mail.default'),
                'metadata' => $metadata,
                'timestamp' => now()->toDateTimeString(),
                'trace' => $e->getTraceAsString(),
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


