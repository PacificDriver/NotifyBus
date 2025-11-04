<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Ключ настройки (например: whatsapp_api_token)
            $table->text('value')->nullable(); // Значение настройки
            $table->string('group')->default('general'); // Группа настроек (whatsapp, email, carrier_api, etc.)
            $table->string('type')->default('string'); // Тип: string, integer, boolean, json
            $table->text('description')->nullable(); // Описание настройки
            $table->boolean('is_encrypted')->default(false); // Зашифровано ли значение (для токенов)
            $table->timestamps();
            
            $table->index('group');
            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

