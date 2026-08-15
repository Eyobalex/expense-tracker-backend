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
        Schema::table('devices', function (Blueprint $table): void {
            $table->unique(['id', 'user_id'], 'devices_id_user_unique');
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id');
            $table->uuid('device_id');
            $table->foreign(['device_id', 'user_id'])->references(['id', 'user_id'])->on('devices')->cascadeOnDelete();
            $table->uuid('client_operation_id');
            $table->string('entity', 64);
            $table->string('action', 32);
            $table->uuid('local_id')->nullable();
            $table->uuid('server_id')->nullable();
            $table->unsignedInteger('expected_version')->nullable();
            $table->char('payload_hash', 64);
            $table->string('status', 16);
            $table->jsonb('response_payload')->nullable();
            $table->jsonb('error_fields')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestampTz('client_occurred_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'device_id', 'client_operation_id'], 'sync_operations_user_device_operation_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropUnique('devices_id_user_unique');
        });
    }
};
