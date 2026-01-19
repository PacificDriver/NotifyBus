<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Обновляем существующие значения HTTP на HTTPS для startport_url
        $setting = Setting::where('key', 'startport_url')->first();
        
        if ($setting && str_starts_with($setting->value, 'http://startport.ru')) {
            $setting->value = str_replace('http://', 'https://', $setting->value);
            $setting->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Откатываем изменения обратно на HTTP (если нужно)
        $setting = Setting::where('key', 'startport_url')->first();
        
        if ($setting && str_starts_with($setting->value, 'https://startport.ru')) {
            $setting->value = str_replace('https://', 'http://', $setting->value);
            $setting->save();
        }
    }
};
