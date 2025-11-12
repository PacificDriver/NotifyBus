<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tripId = 4218865;

        if (!DB::table('trips')->where('id', $tripId)->exists()) {
            return;
        }

        $email = 'test.passenger+4218865@example.com';
        $bookingId = 'TEST-4218865';

        $exists = DB::table('passengers')
            ->where('trip_id', $tripId)
            ->where('email', $email)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('passengers')->insert([
            'trip_id' => $tripId,
            'external_booking_id' => $bookingId,
            'first_name' => 'Тест',
            'last_name' => 'Пассажир',
            'middle_name' => 'Рейсович',
            'email' => $email,
            'phone' => '+79954420305',
            'seat_number' => '1A',
            'ticket_price' => 1000.00,
            'ticket_status' => 'booked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $tripId = 4218865;
        $email = 'test.passenger+4218865@example.com';

        DB::table('passengers')
            ->where('trip_id', $tripId)
            ->where('email', $email)
            ->delete();
    }
};

