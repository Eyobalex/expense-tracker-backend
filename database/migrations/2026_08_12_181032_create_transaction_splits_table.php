<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_splits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('financial_transaction_id');
            $table->uuid('category_id')->nullable();
            $table->bigInteger('amount_minor_units');
            $table->char('currency_code', 3);
            $table->string('classification', 32)->default('category');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();

            $table->foreign('financial_transaction_id')->references('id')->on('financial_transactions')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['financial_transaction_id', 'sequence']);
        });

        DB::statement('ALTER TABLE transaction_splits ADD CONSTRAINT transaction_splits_positive_amount CHECK (amount_minor_units > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_splits');
    }
};
