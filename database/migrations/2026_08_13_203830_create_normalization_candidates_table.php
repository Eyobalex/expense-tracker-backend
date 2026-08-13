<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normalization_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('receipt_id')->nullable();
            $table->uuid('line_item_id')->nullable();
            $table->string('entity_type', 16);
            $table->uuid('candidate_merchant_id')->nullable();
            $table->uuid('candidate_item_id')->nullable();
            $table->string('raw_value', 500);
            $table->string('normalized_search_key', 500);
            $table->decimal('score', 8, 6);
            $table->string('normalization_version', 64);
            $table->string('status', 32)->default('suggested');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign('receipt_id')->references('id')->on('receipts')->cascadeOnDelete();
            $table->foreign('candidate_merchant_id')->references('id')->on('merchants')->restrictOnDelete();
            $table->foreign('candidate_item_id')->references('id')->on('items')->restrictOnDelete();
            $table->unique(['receipt_id', 'entity_type', 'normalized_search_key', 'candidate_merchant_id'], 'normalization_receipt_merchant_unique');
            $table->unique(['line_item_id', 'entity_type', 'normalized_search_key', 'candidate_item_id'], 'normalization_line_item_item_unique');
            $table->index(['user_id', 'status', 'entity_type', 'score']);
        });
        DB::statement("ALTER TABLE normalization_candidates ADD CONSTRAINT normalization_candidates_entity_shape CHECK ((entity_type = 'merchant' AND candidate_merchant_id IS NOT NULL AND candidate_item_id IS NULL AND line_item_id IS NULL) OR (entity_type = 'item' AND candidate_item_id IS NOT NULL AND candidate_merchant_id IS NULL AND line_item_id IS NOT NULL))");
        DB::statement("ALTER TABLE normalization_candidates ADD CONSTRAINT normalization_candidates_status_valid CHECK (status IN ('suggested', 'accepted', 'rejected', 'superseded'))");
        DB::statement('ALTER TABLE normalization_candidates ADD CONSTRAINT normalization_candidates_score_valid CHECK (score >= 0 AND score <= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('normalization_candidates');
    }
};
