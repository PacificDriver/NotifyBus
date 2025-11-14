<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pb_order_item');

        Schema::create('pb_order_item', function (Blueprint $table) {
            $table->unsignedBigInteger('ID');
            $table->unsignedBigInteger('ORDER_ID');
            $table->unsignedBigInteger('REFUND_ID');
            $table->unsignedBigInteger('RACE_ID');
            $table->string('TYPE', 5);
            $table->string('STATUS', 9);
            $table->unsignedBigInteger('FROM_ID');
            $table->string('FROM_LABEL', 255);
            $table->unsignedBigInteger('TO_ID');
            $table->string('TO_LABEL', 255);
            $table->dateTime('ROUTE_BEGIN');
            $table->dateTime('ROUTE_END');
            $table->string('RACE_NUMBER', 255);
            $table->string('RACE_NAME', 255);
            $table->string('RACE_BUS_GN', 255);
            $table->string('RACE_BUS_MODEL', 255);
            $table->string('RACE_PEREVOZ', 255);
            $table->string('SEAT', 255);
            $table->string('CLIENT_EMAIL', 255);
            $table->string('CLIENT_PHONE', 255);
            $table->dateTime('CLIENT_BIRTH');
            $table->string('CLIENT_CITIZENSHIP_ID', 255);
            $table->string('CLIENT_DOC_ID', 255);
            $table->string('CLIENT_DOC_SERIES', 255);
            $table->string('CLIENT_DOC_NUMBER', 255);
            $table->dateTime('CLIENT_DOC_DATE');
            $table->string('CLIENT_NAME', 255);
            $table->string('CLIENT_PATRONYMIC', 255);
            $table->string('CLIENT_SURNAME', 255);
            $table->double('COST');
            $table->double('COST_TAX');
            $table->double('BAG_COST');
            $table->double('BAG_COST_TAX');
            $table->unsignedInteger('BAG_COUNT');
            $table->double('TOTAL_COST');
            $table->double('TOTAL_COST_TAX');
            $table->string('TICKET_REFUND_ID', 255)->nullable();

            $table->primary('ID');
            $table->index('RACE_ID');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pb_order_item');
    }
};


