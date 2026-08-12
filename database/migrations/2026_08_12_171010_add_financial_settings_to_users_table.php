<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('base_currency_code', 3)->nullable()->after('uuid');
            $table->string('timezone', 64)->default('UTC')->after('base_currency_code');
            $table->string('budget_timezone', 64)->default('UTC')->after('timezone');
            $table->boolean('onboarding_completed')->default(false)->after('budget_timezone');
            $table->jsonb('notification_preferences')->nullable()->after('onboarding_completed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['base_currency_code', 'timezone', 'budget_timezone', 'onboarding_completed', 'notification_preferences']);
        });
    }
};
