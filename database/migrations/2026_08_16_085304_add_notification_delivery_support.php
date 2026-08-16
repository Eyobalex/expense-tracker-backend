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
        DB::statement("ALTER TABLE user_notifications ADD CONSTRAINT user_notifications_type_check CHECK (type IN ('budget_threshold', 'actual_budget_overspend', 'projected_budget_overspend', 'borrowing_consequence', 'fx_rate_stale', 'ocr_failed', 'report_ready', 'item_price_movement'))");
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_channel_check CHECK (channel IN ('in_app', 'local', 'push', 'email'))");
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT notification_deliveries_status_check CHECK (status IN ('queued', 'delivered', 'failed', 'skipped'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE notification_deliveries DROP CONSTRAINT IF EXISTS notification_deliveries_status_check');
        DB::statement('ALTER TABLE notification_deliveries DROP CONSTRAINT IF EXISTS notification_deliveries_channel_check');
        DB::statement('ALTER TABLE user_notifications DROP CONSTRAINT IF EXISTS user_notifications_type_check');
    }
};
