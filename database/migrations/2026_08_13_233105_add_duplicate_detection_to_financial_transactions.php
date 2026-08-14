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
        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->string('reference_number', 128)->nullable()->after('description');
            $table->uuid('duplicate_replaced_by_id')->nullable()->after('correction_of_id');
            $table->timestampTz('cancelled_at')->nullable()->after('reversed_at');
            $table->foreign('duplicate_replaced_by_id')->references('id')->on('financial_transactions')->restrictOnDelete();
            $table->index(['user_id', 'original_currency_code', 'original_amount_minor_units', 'occurred_at'], 'financial_transactions_duplicate_matching_index');
            $table->index(['user_id', 'reference_number'], 'financial_transactions_reference_number_index');
        });

        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_state_valid');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_state_valid CHECK (state IN ('draft', 'pending_review', 'posted', 'reversed', 'sync_conflict', 'cancelled'))");
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_cancelled_fields CHECK (state <> 'cancelled' OR cancelled_at IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_cancelled_fields');
        DB::statement('ALTER TABLE financial_transactions DROP CONSTRAINT financial_transactions_state_valid');
        DB::statement("ALTER TABLE financial_transactions ADD CONSTRAINT financial_transactions_state_valid CHECK (state IN ('draft', 'pending_review', 'posted', 'reversed', 'sync_conflict'))");

        Schema::table('financial_transactions', function (Blueprint $table): void {
            $table->dropForeign(['duplicate_replaced_by_id']);
            $table->dropIndex('financial_transactions_duplicate_matching_index');
            $table->dropIndex('financial_transactions_reference_number_index');
            $table->dropColumn(['reference_number', 'duplicate_replaced_by_id', 'cancelled_at']);
        });
    }
};
