<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('financial_account_id')->nullable();
            $table->string('ledger_code', 64);
            $table->bigInteger('debit_minor_units')->default(0);
            $table->bigInteger('credit_minor_units')->default(0);
            $table->char('currency_code', 3);
            $table->bigInteger('base_debit_minor_units')->default(0);
            $table->bigInteger('base_credit_minor_units')->default(0);
            $table->char('base_currency_code', 3);
            $table->decimal('used_rate', 38, 18)->nullable();
            $table->date('rate_date')->nullable();
            $table->string('rate_source', 64)->nullable();
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->cascadeOnDelete();
            $table->foreign('financial_account_id')->references('id')->on('financial_accounts')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['journal_entry_id', 'sequence']);
            $table->index(['user_id', 'financial_account_id', 'journal_entry_id']);
        });

        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_native_side CHECK ((debit_minor_units > 0 AND credit_minor_units = 0) OR (credit_minor_units > 0 AND debit_minor_units = 0))');
        DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_base_side CHECK ((base_debit_minor_units > 0 AND base_credit_minor_units = 0) OR (base_credit_minor_units > 0 AND base_debit_minor_units = 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
