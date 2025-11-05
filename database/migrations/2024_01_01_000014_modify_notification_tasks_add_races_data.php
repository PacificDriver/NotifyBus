<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_tasks', function (Blueprint $table) {
            // Добавляем поле races_data для хранения данных отмененных рейсов
            $table->json('races_data')->nullable()->after('title');
            // Оставляем trip_ids для обратной совместимости, но основной источник - races_data
        });
    }

    public function down(): void
    {
        Schema::table('notification_tasks', function (Blueprint $table) {
            $table->dropColumn('races_data');
        });
    }
};

