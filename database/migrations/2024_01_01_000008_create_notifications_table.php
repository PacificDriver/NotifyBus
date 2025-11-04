<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_task_id')->constrained('notification_tasks')->onDelete('cascade');
            $table->foreignId('passenger_id')->constrained('passengers')->onDelete('cascade');
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->enum('channel', ['email', 'whatsapp']); // Канал отправки
            $table->string('recipient'); // Email или номер телефона
            $table->text('subject')->nullable(); // Тема (для email)
            $table->text('message'); // Текст сообщения
            $table->enum('status', ['pending', 'queued', 'sent', 'failed', 'delivered'])->default('pending');
            $table->text('error_message')->nullable(); // Сообщение об ошибке
            $table->integer('retry_count')->default(0);
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->json('metadata')->nullable(); // Дополнительные данные
            $table->timestamps();
            
            $table->index(['notification_task_id', 'status']);
            $table->index(['passenger_id', 'channel']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};


