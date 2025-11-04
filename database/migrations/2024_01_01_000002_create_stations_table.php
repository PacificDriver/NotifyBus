<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Название станции (например, "Смирных")
            $table->string('code')->unique()->nullable(); // Код станции в системе перевозчика
            $table->string('city')->nullable(); // Город
            $table->string('region')->nullable(); // Регион
            $table->decimal('latitude', 10, 7)->nullable(); // Широта
            $table->decimal('longitude', 10, 7)->nullable(); // Долгота
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};


