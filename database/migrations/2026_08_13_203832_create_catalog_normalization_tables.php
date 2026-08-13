<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->uuid('merchant_id')->nullable()->after('category_id');
            $table->string('raw_merchant_text', 500)->nullable()->after('merchant_id');
            $table->foreign('merchant_id')->references('id')->on('merchants')->restrictOnDelete();
            $table->index(['user_id', 'merchant_id', 'occurred_at']);
        });
        Schema::table('transaction_splits', function (Blueprint $table): void {
            $table->uuid('canonical_item_id')->nullable()->after('category_id');
            $table->string('raw_item_text', 500)->nullable()->after('canonical_item_id');
            $table->foreign('canonical_item_id')->references('id')->on('items')->restrictOnDelete();
        });
        Schema::table('normalization_candidates', function (Blueprint $table): void {
            $table->foreign('line_item_id')->references('id')->on('line_items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('normalization_candidates', function (Blueprint $table): void {
            $table->dropForeign(['line_item_id']);
        });
        Schema::table('transaction_splits', function (Blueprint $table): void {
            $table->dropForeign(['canonical_item_id']);
            $table->dropColumn(['canonical_item_id', 'raw_item_text']);
        });
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'merchant_id', 'occurred_at']);
            $table->dropForeign(['merchant_id']);
            $table->dropColumn(['merchant_id', 'raw_merchant_text']);
        });
    }
};
