<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('base_currency_code', 3);
            $table->char('quote_currency_code', 3);
            $table->date('rate_date');
            $table->decimal('rate', 38, 18);
            $table->string('provider', 64);
            $table->timestampTz('provider_published_at')->nullable();
            $table->timestampTz('retrieved_at');
            $table->string('status', 16)->default('fresh');
            $table->jsonb('provider_metadata')->nullable();
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('quote_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['base_currency_code', 'quote_currency_code', 'rate_date', 'provider'], 'exchange_rates_pair_date_provider_unique');
            $table->index(['base_currency_code', 'quote_currency_code', 'rate_date'], 'exchange_rates_pair_date_index');
        });

        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_positive_rate CHECK (rate > 0)');
        DB::statement("ALTER TABLE exchange_rates ADD CONSTRAINT exchange_rates_status_valid CHECK (status IN ('fresh', 'stale', 'failed'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
