<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trip_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->enum('old_status', ['scheduled', 'cancelled', 'delayed', 'completed'])->nullable();
            $table->enum('new_status', ['scheduled', 'cancelled', 'delayed', 'completed']);
            $table->string('reason', 255)->nullable();
            $table->timestamp('new_departure_at')->nullable();
            $table->foreignId('changed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('changed_at')->useCurrent();
            
            $table->index('trip_id');
            $table->index('changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_status_changes');
    }
};
