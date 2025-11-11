<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class DynamicSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->settingsTableExists()) {
            return;
        }

        $this->applyEmailSettings();
        $this->applyNotificationSettings();
    }

    protected function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Exception $e) {
            Log::debug('DynamicSettingsServiceProvider: settings table check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function applyEmailSettings(): void
    {
        try {
            $host = Setting::get('email_host');
            $port = Setting::get('email_port');
            $username = Setting::get('email_username');
            $password = Setting::get('email_password');
            $encryption = Setting::get('email_encryption');
            $fromAddress = Setting::get('email_from_address');
            $fromName = Setting::get('email_from_name');

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
        } catch (\Exception $e) {
            Log::warning('DynamicSettingsServiceProvider: failed to apply email settings', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function applyNotificationSettings(): void
    {
        try {
            $batchSize = (int) Setting::get('notification_batch_size', 10);
            $delaySeconds = (int) Setting::get('notification_delay_seconds', 2);

            if ($batchSize <= 0) {
                $batchSize = 10;
            }

            if ($delaySeconds < 0) {
                $delaySeconds = 0;
            }

            Config::set('notifications.batch_size', $batchSize);
            Config::set('notifications.delay_seconds', $delaySeconds);
        } catch (\Exception $e) {
            Log::warning('DynamicSettingsServiceProvider: failed to apply notification settings', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}


