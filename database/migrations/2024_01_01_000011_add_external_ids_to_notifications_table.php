<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('external_message_id')->nullable()->after('metadata');
            $table->string('external_task_id')->nullable()->after('external_message_id');
            
            // Индексы для быстрого поиска по внешним ID
            $table->index('external_message_id');
            $table->index('external_task_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['external_message_id']);
            $table->dropIndex(['external_task_id']);
            $table->dropColumn(['external_message_id', 'external_task_id']);
        });
    }
};

