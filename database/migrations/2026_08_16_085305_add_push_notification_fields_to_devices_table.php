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
        Schema::table('devices', function (Blueprint $table) {
            $table->text('push_token')->nullable()->after('app_version');
            $table->string('push_token_provider', 32)->nullable()->after('push_token');
            $table->timestampTz('push_token_updated_at')->nullable()->after('push_token_provider');
            $table->index(['user_id', 'revoked_at', 'push_token_provider'], 'devices_active_push_token_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('devices_active_push_token_index');
            $table->dropColumn(['push_token', 'push_token_provider', 'push_token_updated_at']);
        });
    }
};
