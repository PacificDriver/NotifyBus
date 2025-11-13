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
        Schema::table('passengers', function (Blueprint $table) {
            if (!Schema::hasColumn('passengers', 'external_race_id')) {
                $table->string('external_race_id')->nullable()->after('trip_id');
                $table->index('external_race_id');
            }

            if (!Schema::hasColumn('passengers', 'ticket_uid')) {
                $table->string('ticket_uid')->nullable()->after('external_booking_id');
                $table->index('ticket_uid');
            }

            if (!Schema::hasColumn('passengers', 'external_order_id')) {
                $table->bigInteger('external_order_id')->nullable()->after('external_booking_id');
                $table->index('external_order_id');
            }

            $table->unique(['external_race_id', 'ticket_uid'], 'passengers_external_race_ticket_uid_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropUnique('passengers_external_race_ticket_uid_unique');
        });
    }
};


