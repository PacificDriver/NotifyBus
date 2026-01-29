<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_manifests', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('РФБАС'); // Провайдер (РФБАС)
            $table->string('external_route_id'); // id_route из API
            $table->string('external_trip_id')->nullable(); // id из API (для справки)
            $table->dateTime('dt_depart'); // Время отправления
            $table->dateTime('dt_arrive')->nullable(); // Время прибытия
            $table->string('from_id')->nullable(); // ID станции отправления в API
            $table->string('to_id')->nullable(); // ID станции назначения в API
            $table->string('from_name')->nullable(); // Название станции отправления
            $table->string('to_name')->nullable(); // Название станции назначения
            $table->string('route_number')->nullable(); // Номер маршрута
            $table->string('bus_number')->nullable(); // Номер автобуса (госномер)
            $table->string('vehicle_model')->nullable(); // Модель транспорта
            $table->string('carrier_name')->nullable(); // Название перевозчика
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->timestamps();
            
            // Уникальный индекс для предотвращения дублей
            $table->unique(['provider', 'external_route_id', 'dt_depart'], 'unique_manifest');
            $table->index('external_route_id');
            $table->index('dt_depart');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_manifests');
    }
};
