<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Название задачи
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->json('trip_ids'); // Массив ID рейсов
            $table->foreignId('template_id')->nullable()->constrained('message_templates')->onDelete('set null');
            $table->text('custom_message')->nullable(); // Кастомное сообщение (если не используется шаблон)
            $table->enum('status', ['draft', 'pending', 'processing', 'completed', 'failed'])->default('draft');
            $table->integer('total_recipients')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->dateTime('scheduled_at')->nullable(); // Запланированное время отправки
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_tasks');
    }
};


