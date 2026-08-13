<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('receipt_id')->nullable();
            $table->uuid('financial_transaction_id')->nullable();
            $table->uuid('canonical_item_id')->nullable();
            $table->string('raw_description', 500);
            $table->decimal('quantity', 20, 6)->nullable();
            $table->string('unit_code', 32)->nullable();
            $table->decimal('pack_size_value', 20, 6)->nullable();
            $table->string('pack_size_unit', 32)->nullable();
            $table->bigInteger('unit_price_minor_units')->nullable();
            $table->bigInteger('line_total_minor_units')->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->decimal('confidence', 8, 6)->nullable();
            $table->string('normalization_version', 64)->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->timestamps();
            $table->foreign('receipt_id')->references('id')->on('receipts')->cascadeOnDelete();
            $table->foreign('financial_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('canonical_item_id')->references('id')->on('items')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['receipt_id', 'sequence']);
            $table->index(['user_id', 'canonical_item_id']);
        });
        DB::statement('ALTER TABLE line_items ADD CONSTRAINT line_items_quantity_positive CHECK (quantity IS NULL OR quantity > 0)');
        DB::statement('ALTER TABLE line_items ADD CONSTRAINT line_items_pack_size_shape CHECK ((pack_size_value IS NULL AND pack_size_unit IS NULL) OR (pack_size_value > 0 AND pack_size_unit IS NOT NULL))');
        DB::statement('ALTER TABLE line_items ADD CONSTRAINT line_items_amounts_positive CHECK ((unit_price_minor_units IS NULL OR unit_price_minor_units > 0) AND (line_total_minor_units IS NULL OR line_total_minor_units > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
