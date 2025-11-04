<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departure_station_id')->constrained('stations')->onDelete('cascade');
            $table->foreignId('arrival_station_id')->constrained('stations')->onDelete('cascade');
            $table->string('route_number')->nullable(); // Номер маршрута
            $table->integer('duration_minutes')->nullable(); // Длительность поездки в минутах
            $table->decimal('distance_km', 8, 2)->nullable(); // Расстояние в км
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['departure_station_id', 'arrival_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};


