<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fx_provider_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('provider', 64);
            $table->char('base_currency_code', 3);
            $table->char('quote_currency_code', 3);
            $table->time('refresh_time');
            $table->unsignedInteger('monthly_quota');
            $table->unsignedSmallInteger('alert_after_hours');
            $table->unsignedSmallInteger('maximum_staleness_hours');
            $table->string('fallback_policy', 64);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('quote_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['provider', 'base_currency_code', 'quote_currency_code'], 'fx_provider_settings_pair_unique');
        });

        DB::statement('ALTER TABLE fx_provider_settings ADD CONSTRAINT fx_provider_settings_distinct_pair CHECK (base_currency_code <> quote_currency_code)');
        DB::statement('ALTER TABLE fx_provider_settings ADD CONSTRAINT fx_provider_settings_quota_positive CHECK (monthly_quota > 0)');
        DB::statement('ALTER TABLE fx_provider_settings ADD CONSTRAINT fx_provider_settings_alert_positive CHECK (alert_after_hours > 0)');
        DB::statement('ALTER TABLE fx_provider_settings ADD CONSTRAINT fx_provider_settings_maximum_staleness_valid CHECK (maximum_staleness_hours >= alert_after_hours)');
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_provider_settings');
    }
};
