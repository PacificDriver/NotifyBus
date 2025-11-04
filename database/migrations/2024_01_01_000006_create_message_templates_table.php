<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название шаблона
            $table->string('slug')->unique(); // Уникальный идентификатор
            $table->enum('type', ['cancellation', 'delay', 'general']); // Тип уведомления
            $table->text('subject')->nullable(); // Тема письма (для email)
            $table->text('body'); // Текст сообщения с переменными
            $table->json('available_variables')->nullable(); // Список доступных переменных
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};


