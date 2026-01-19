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
        // Добавляем настройки для Startport API
        Setting::set(
            key: 'startport_url',
            value: 'https://startport.ru',
            group: 'startport',
            type: 'string',
            encrypted: false
        );

        Setting::set(
            key: 'startport_api_key',
            value: '', // Пустое значение, администратор заполнит через интерфейс
            group: 'startport',
            type: 'string',
            encrypted: true // Секретный ключ шифруется
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем настройки Startport
        Setting::where('key', 'startport_url')->delete();
        Setting::where('key', 'startport_api_key')->delete();
    }
};
