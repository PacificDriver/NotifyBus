<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->string('external_booking_id')->nullable(); // ID бронирования в системе перевозчика
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('seat_number')->nullable();
            $table->decimal('ticket_price', 10, 2)->nullable();
            $table->enum('ticket_status', ['booked', 'paid', 'cancelled', 'refunded'])->default('booked');
            $table->timestamps();
            
            $table->index('trip_id');
            $table->index('external_booking_id');
            $table->index(['email', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passengers');
    }
};


