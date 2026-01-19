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
            // Добавляем поле data_source для определения источника данных пассажира
            $table->enum('data_source', ['pb_order_item', 'startport'])
                ->default('pb_order_item')
                ->after('external_payload')
                ->comment('Источник данных: pb_order_item или startport');
            
            // Добавляем индекс для быстрого поиска по источнику
            $table->index('data_source');
        });
        
        // Обновляем существующие записи - устанавливаем pb_order_item для всех
        DB::table('passengers')->update(['data_source' => 'pb_order_item']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropIndex(['data_source']);
            $table->dropColumn('data_source');
        });
    }
};
