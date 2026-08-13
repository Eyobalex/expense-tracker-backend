<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_adjustments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('category_id');
            $table->uuid('budget_period_id');
            $table->string('type', 32);
            $table->bigInteger('amount_minor_units');
            $table->char('currency_code', 3);
            $table->uuid('paired_adjustment_id')->nullable();
            $table->uuid('source_period_id')->nullable();
            $table->uuid('target_period_id')->nullable();
            $table->uuid('source_transaction_id')->nullable();
            $table->uuid('source_adjustment_id')->nullable();
            $table->uuid('idempotency_operation_id')->nullable();
            $table->bigInteger('base_limit_snapshot_minor_units')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->string('reason', 500);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('budget_period_id')->references('id')->on('budget_periods')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('source_period_id')->references('id')->on('budget_periods')->restrictOnDelete();
            $table->foreign('target_period_id')->references('id')->on('budget_periods')->restrictOnDelete();
            $table->foreign('source_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('idempotency_operation_id')->references('id')->on('idempotency_operations')->nullOnDelete();
            $table->index(['budget_period_id', 'type']);
            $table->index(['source_period_id', 'type']);
            $table->index(['source_transaction_id', 'type']);
        });

        Schema::table('budget_adjustments', function (Blueprint $table): void {
            $table->foreign('paired_adjustment_id')->references('id')->on('budget_adjustments')->restrictOnDelete();
            $table->foreign('source_adjustment_id')->references('id')->on('budget_adjustments')->restrictOnDelete();
        });

        DB::statement("ALTER TABLE budget_adjustments ADD CONSTRAINT budget_adjustments_type_valid CHECK (type IN ('rollover', 'underflow', 'reallocation_in', 'reallocation_out', 'borrowing_in', 'borrowing_reserved', 'correction'))");
        DB::statement('ALTER TABLE budget_adjustments ADD CONSTRAINT budget_adjustments_nonzero_amount CHECK (amount_minor_units <> 0)');
        DB::statement("CREATE UNIQUE INDEX budget_adjustments_single_borrowing_reservation ON budget_adjustments (category_id, target_period_id) WHERE type = 'borrowing_reserved'");
    }

    public function down(): void
    {
        Schema::table('budget_adjustments', function (Blueprint $table): void {
            $table->dropForeign(['paired_adjustment_id', 'source_adjustment_id']);
        });
        Schema::dropIfExists('budget_adjustments');
    }
};
