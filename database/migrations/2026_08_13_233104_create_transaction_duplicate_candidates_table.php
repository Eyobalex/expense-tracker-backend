<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_duplicate_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('financial_transaction_id');
            $table->uuid('candidate_transaction_id');
            $table->decimal('score', 8, 6);
            $table->jsonb('score_breakdown');
            $table->string('algorithm_version', 64);
            $table->string('status', 32)->default('suggested');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('financial_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->foreign('candidate_transaction_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->unique(['financial_transaction_id', 'candidate_transaction_id'], 'transaction_duplicate_candidate_pair_unique');
            $table->index(['user_id', 'financial_transaction_id', 'status'], 'transaction_duplicate_candidates_source_status_index');
        });

        DB::statement('ALTER TABLE transaction_duplicate_candidates ADD CONSTRAINT transaction_duplicate_candidates_distinct_transactions CHECK (financial_transaction_id <> candidate_transaction_id)');
        DB::statement('ALTER TABLE transaction_duplicate_candidates ADD CONSTRAINT transaction_duplicate_candidates_score_range CHECK (score >= 0 AND score <= 1)');
        DB::statement("ALTER TABLE transaction_duplicate_candidates ADD CONSTRAINT transaction_duplicate_candidates_status_valid CHECK (status IN ('suggested', 'viewed', 'keep_both', 'replaced', 'cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_duplicate_candidates');
    }
};
