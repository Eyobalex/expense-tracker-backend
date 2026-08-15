<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("CREATE INDEX financial_transactions_posted_user_category_occurred_at_index ON financial_transactions (user_id, category_id, occurred_at, id) WHERE state = 'posted'");
        DB::statement("CREATE INDEX financial_transactions_posted_user_occurred_at_index ON financial_transactions (user_id, occurred_at, id) WHERE state = 'posted'");
        DB::statement('CREATE INDEX line_items_insight_lookup_index ON line_items (user_id, canonical_item_id, currency_code, financial_transaction_id) WHERE canonical_item_id IS NOT NULL AND financial_transaction_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS financial_transactions_posted_user_category_occurred_at_index');
        DB::statement('DROP INDEX IF EXISTS financial_transactions_posted_user_occurred_at_index');
        DB::statement('DROP INDEX IF EXISTS line_items_insight_lookup_index');
    }
};
