<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->string('trip_number'); // Номер рейса
            $table->string('external_id')->nullable(); // ID в системе перевозчика
            $table->dateTime('departure_time'); // Время отправления
            $table->dateTime('arrival_time'); // Время прибытия
            $table->enum('status', ['scheduled', 'cancelled', 'delayed', 'completed'])->default('scheduled');
            $table->text('cancellation_reason')->nullable(); // Причина отмены
            $table->dateTime('cancelled_at')->nullable(); // Когда отменен
            $table->integer('delay_minutes')->nullable(); // Задержка в минутах
            $table->integer('total_seats')->nullable(); // Всего мест
            $table->integer('available_seats')->nullable(); // Доступно мест
            $table->timestamps();
            
            $table->index(['departure_time', 'status']);
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};


