<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->foreignId('to_station_id')->nullable()->constrained('stations')->nullOnDelete();
            $table->string('from_station_name')->nullable();
            $table->string('to_station_name')->nullable();
            $table->date('trip_date')->nullable();
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('result_count')->default(0);
            $table->string('search_type')->default('cancelled');
            $table->json('query_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['search_type', 'cancelled_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_histories');
    }
};


