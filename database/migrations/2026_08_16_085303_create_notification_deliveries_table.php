<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_notification_id')->constrained('user_notifications')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('status', 32)->default('queued');
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('job_id', 128)->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();

            $table->index(['user_notification_id', 'channel', 'device_id'], 'notification_deliveries_lookup_index');
            $table->index(['status', 'created_at'], 'notification_deliveries_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
