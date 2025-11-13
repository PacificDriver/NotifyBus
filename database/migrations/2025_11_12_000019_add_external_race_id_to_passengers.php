<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Добавляем external_race_id для связи с рейсами из внешней системы
     */
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            // ID рейса из внешней системы (для связи через Airbyte)
            $table->string('external_race_id')->nullable()->after('trip_id');
            $table->index('external_race_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropIndex(['external_race_id']);
            $table->dropColumn('external_race_id');
        });
    }
};


