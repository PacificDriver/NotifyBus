<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_manifest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manifest_id')->constrained('trip_manifests')->onDelete('cascade');
            $table->foreignId('passenger_id')->constrained('passengers')->onDelete('cascade');
            $table->boolean('checked_in')->nullable()->default(null); // NULL = не отмечен, true = явился, false = не явился
            $table->dateTime('checked_in_at')->nullable(); // Когда отметили
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete(); // Кто отметил
            $table->timestamps();
            
            // Уникальный индекс: один пассажир = одна запись в ведомости
            $table->unique(['manifest_id', 'passenger_id']);
            $table->index('manifest_id');
            $table->index('passenger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_manifest_items');
    }
};
