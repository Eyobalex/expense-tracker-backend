<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('financial_transaction_id');
            $table->string('type', 32);
            $table->char('functional_currency_code', 3);
            $table->timestampTz('posted_at');
            $table->uuid('reversed_entry_id')->nullable();
            $table->uuid('correction_of_entry_id')->nullable();
            $table->uuid('idempotency_operation_id')->nullable();
            $table->string('integrity_hash', 64)->nullable();
            $table->timestamps();

            $table->foreign('financial_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('functional_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('idempotency_operation_id')->references('id')->on('idempotency_operations')->nullOnDelete();
            $table->unique('financial_transaction_id');
            $table->unique('reversed_entry_id');
        });
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->foreign('reversed_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('correction_of_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
        });

        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->restrictOnDelete();
            $table->foreign('reversal_of_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('correction_of_id')->references('id')->on('financial_transactions')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_reversal_distinct CHECK (reversed_entry_id IS NULL OR reversed_entry_id <> id)');
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropForeign(['reversed_entry_id', 'correction_of_entry_id']);
        });
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropForeign(['journal_entry_id', 'reversal_of_id', 'correction_of_id']);
        });
        Schema::dropIfExists('journal_entries');
    }
};
