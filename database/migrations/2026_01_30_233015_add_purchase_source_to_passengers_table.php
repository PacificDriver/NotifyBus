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
            $table->string('purchase_source', 50)->nullable()->after('data_source')
                ->comment('Источник покупки: vk_app, website, mobile_app, driver, cashier');
            $table->index('purchase_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropIndex(['purchase_source']);
            $table->dropColumn('purchase_source');
        });
    }
};
