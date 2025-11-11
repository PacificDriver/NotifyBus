<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->string('passenger_type', 50)->nullable()->after('external_booking_id');
            $table->bigInteger('external_order_id')->nullable()->after('passenger_type');
            $table->string('ticket_uid')->nullable()->after('external_booking_id');
            $table->string('ticket_number')->nullable()->after('ticket_uid');
            $table->dateTime('ticket_purchased_at')->nullable()->after('ticket_number');

            $table->date('birth_date')->nullable()->after('phone');
            $table->string('document_type')->nullable()->after('birth_date');
            $table->string('document_series')->nullable()->after('document_type');
            $table->string('document_number')->nullable()->after('document_series');
            $table->date('document_issued_at')->nullable()->after('document_number');

            $table->decimal('ticket_service_fee', 10, 2)->nullable()->after('ticket_price');
            $table->decimal('ticket_total_price', 10, 2)->nullable()->after('ticket_service_fee');
            $table->decimal('ticket_discount', 10, 2)->nullable()->after('ticket_total_price');

            $table->json('external_payload')->nullable()->after('ticket_status');

            $table->index('ticket_uid');
            $table->index('external_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropIndex(['ticket_uid']);
            $table->dropIndex(['external_order_id']);

            $table->dropColumn([
                'passenger_type',
                'external_order_id',
                'ticket_uid',
                'ticket_number',
                'ticket_purchased_at',
                'birth_date',
                'document_type',
                'document_series',
                'document_number',
                'document_issued_at',
                'ticket_service_fee',
                'ticket_total_price',
                'ticket_discount',
                'external_payload',
            ]);
        });
    }
};


