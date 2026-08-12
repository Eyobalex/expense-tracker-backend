<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('financial_account_id');
            $table->uuid('counterparty_account_id')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('related_transaction_id')->nullable();
            $table->string('type', 32);
            $table->string('state', 32)->default('draft');
            $table->string('source', 32)->default('manual');
            $table->string('adjustment_subtype', 32)->nullable();
            $table->string('adjustment_direction', 8)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestampTz('occurred_at');
            $table->string('occurred_timezone', 64);
            $table->bigInteger('original_amount_minor_units');
            $table->char('original_currency_code', 3);
            $table->bigInteger('counterparty_amount_minor_units')->nullable();
            $table->char('counterparty_currency_code', 3)->nullable();
            $table->bigInteger('base_amount_minor_units')->nullable();
            $table->char('base_currency_code', 3)->nullable();
            $table->decimal('reference_rate', 38, 18)->nullable();
            $table->decimal('used_rate', 38, 18)->nullable();
            $table->date('rate_date')->nullable();
            $table->string('rate_source', 64)->nullable();
            $table->string('rate_override_reason', 500)->nullable();
            $table->string('rounding_mode', 32)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('reversal_of_id')->nullable();
            $table->uuid('correction_of_id')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('posted_at')->nullable();
            $table->timestampTz('reversed_at')->nullable();
            $table->timestamps();

            $table->foreign('financial_account_id')->references('id')->on('financial_accounts')->restrictOnDelete();
            $table->foreign('counterparty_account_id')->references('id')->on('financial_accounts')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('related_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('original_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('counterparty_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['user_id', 'state', 'occurred_at', 'id']);
            $table->index(['user_id', 'financial_account_id', 'occurred_at']);
            $table->index(['user_id', 'category_id', 'occurred_at']);
            $table->unique('reversal_of_id');
        });

        DB::statement('ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_positive_original_amount CHECK (original_amount_minor_units > 0)');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_posted_fields CHECK (state NOT IN ('posted', 'reversed') OR (posted_at IS NOT NULL AND base_amount_minor_units IS NOT NULL AND base_currency_code IS NOT NULL AND journal_entry_id IS NOT NULL))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_adjustment_shape CHECK (type <> 'adjustment' OR (adjustment_subtype IS NOT NULL AND adjustment_direction IN ('debit', 'credit') AND reason IS NOT NULL))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_type_valid CHECK (type IN ('expense', 'income', 'transfer', 'credit_card_purchase', 'credit_card_repayment', 'refund', 'fee', 'opening_balance', 'adjustment'))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_state_valid CHECK (state IN ('draft', 'pending_review', 'posted', 'reversed', 'sync_conflict'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
