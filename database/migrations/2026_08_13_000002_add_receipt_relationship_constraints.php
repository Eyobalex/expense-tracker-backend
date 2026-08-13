<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->foreign('processing_derivative_id')->references('id')->on('receipt_derivatives')->nullOnDelete();
            $table->foreign('review_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropForeign(['processing_derivative_id', 'review_transaction_id']);
        });
    }
};
