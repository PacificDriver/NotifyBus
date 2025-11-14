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
        if (!Schema::hasTable('pb_order_item')) {
            return;
        }

        $columns = Schema::getColumnListing('pb_order_item');
        $columns = array_map('strtoupper', $columns);

        Schema::table('pb_order_item', function (Blueprint $table) use ($columns) {
            $addString = function (string $name, int $length = 255) use ($table, $columns): void {
                if (!in_array($name, $columns, true)) {
                    $table->string($name, $length)->nullable();
                }
            };

            $addDecimal = function (string $name) use ($table, $columns): void {
                if (!in_array($name, $columns, true)) {
                    $table->decimal($name, 12, 2)->nullable();
                }
            };

            $addDateTime = function (string $name) use ($table, $columns): void {
                if (!in_array($name, $columns, true)) {
                    $table->dateTime($name)->nullable();
                }
            };

            $addInteger = function (string $name) use ($table, $columns): void {
                if (!in_array($name, $columns, true)) {
                    $table->unsignedBigInteger($name)->nullable();
                }
            };

            $addInteger('REFUND_ID');
            $addString('TYPE', 64);
            $addString('STATUS', 64);
            $addInteger('FROM_ID');
            $addString('FROM_LABEL', 255);
            $addInteger('TO_ID');
            $addString('TO_LABEL', 255);
            $addDateTime('ROUTE_BEGIN');
            $addDateTime('ROUTE_END');
            $addString('RACE_NUMBER', 64);
            $addString('RACE_NAME', 255);
            $addString('RACE_BUS_GN', 64);
            $addString('RACE_BUS_MODEL', 128);
            $addString('RACE_PEREVOZ', 255);
            $addString('SEAT', 32);
            $addString('CLIENT_EMAIL', 255);
            $addString('CLIENT_PHONE', 64);
            $addDateTime('CLIENT_BIRTH');
            $addInteger('CLIENT_CITIZENSHIP_ID');
            $addString('CLIENT_DOC_ID', 128);
            $addString('CLIENT_DOC_SERIES', 128);
            $addString('CLIENT_DOC_NUMBER', 128);
            $addDateTime('CLIENT_DOC_DATE');
            $addString('CLIENT_NAME', 128);
            $addString('CLIENT_PATRONYMIC', 128);
            $addString('CLIENT_SURNAME', 128);
            $addDecimal('COST');
            $addDecimal('COST_TAX');
            $addDecimal('BAG_COST');
            $addDecimal('BAG_COST_TAX');
            $addInteger('BAG_COUNT');
            $addDecimal('TOTAL_COST');
            $addDecimal('TOTAL_COST_TAX');
            $addString('TICKET_REFUND_ID', 128);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pb_order_item')) {
            return;
        }

        Schema::table('pb_order_item', function (Blueprint $table) {
            $columns = [
                'REFUND_ID',
                'TYPE',
                'STATUS',
                'FROM_ID',
                'FROM_LABEL',
                'TO_ID',
                'TO_LABEL',
                'ROUTE_BEGIN',
                'ROUTE_END',
                'RACE_NUMBER',
                'RACE_NAME',
                'RACE_BUS_GN',
                'RACE_BUS_MODEL',
                'RACE_PEREVOZ',
                'SEAT',
                'CLIENT_EMAIL',
                'CLIENT_PHONE',
                'CLIENT_BIRTH',
                'CLIENT_CITIZENSHIP_ID',
                'CLIENT_DOC_ID',
                'CLIENT_DOC_SERIES',
                'CLIENT_DOC_NUMBER',
                'CLIENT_DOC_DATE',
                'CLIENT_NAME',
                'CLIENT_PATRONYMIC',
                'CLIENT_SURNAME',
                'COST',
                'COST_TAX',
                'BAG_COST',
                'BAG_COST_TAX',
                'BAG_COUNT',
                'TOTAL_COST',
                'TOTAL_COST_TAX',
                'TICKET_REFUND_ID',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('pb_order_item', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};


