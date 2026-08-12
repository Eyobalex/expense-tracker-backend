<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('category_id');
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('budget_timezone', 64);
            $table->timestampTz('period_start_at');
            $table->timestampTz('period_end_at');
            $table->string('status', 16)->default('open');
            $table->char('currency_code', 3);
            $table->bigInteger('base_limit_minor_units');
            $table->boolean('rollover_enabled_snapshot')->default(false);
            $table->boolean('overspend_carry_enabled_snapshot')->default(false);
            $table->boolean('borrowing_enabled_snapshot')->default(false);
            $table->bigInteger('borrowing_deduction_minor_units')->default(0);
            $table->bigInteger('positive_rollover_minor_units')->default(0);
            $table->bigInteger('negative_carry_minor_units')->default(0);
            $table->bigInteger('reallocation_in_minor_units')->default(0);
            $table->bigInteger('reallocation_out_minor_units')->default(0);
            $table->bigInteger('effective_limit_minor_units')->default(0);
            $table->bigInteger('actual_spent_minor_units')->default(0);
            $table->bigInteger('remaining_minor_units')->default(0);
            $table->timestampTz('initialized_at');
            $table->timestampTz('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['category_id', 'period_year', 'period_month']);
            $table->index(['user_id', 'period_year', 'period_month']);
            $table->index(['category_id', 'period_start_at']);
        });

        DB::statement('ALTER TABLE budget_periods ADD CONSTRAINT budget_periods_month_valid CHECK (period_month BETWEEN 1 AND 12)');
        DB::statement("ALTER TABLE budget_periods ADD CONSTRAINT budget_periods_status_valid CHECK (status IN ('initializing', 'open', 'closed'))");
        DB::statement('ALTER TABLE budget_periods ADD CONSTRAINT budget_periods_nonnegative_snapshots CHECK (base_limit_minor_units >= 0 AND borrowing_deduction_minor_units >= 0 AND positive_rollover_minor_units >= 0 AND negative_carry_minor_units >= 0 AND reallocation_in_minor_units >= 0 AND reallocation_out_minor_units >= 0)');
        DB::statement('ALTER TABLE budget_periods ADD CONSTRAINT budget_periods_boundaries_valid CHECK (period_start_at < period_end_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_periods');
    }
};
