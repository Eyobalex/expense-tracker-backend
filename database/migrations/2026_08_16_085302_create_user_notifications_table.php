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
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title', 160);
            $table->string('body', 500);
            $table->jsonb('payload');
            $table->boolean('in_app_enabled')->default(true);
            $table->uuid('insight_snapshot_id')->nullable();
            $table->string('deduplication_key', 160);
            $table->timestampTz('read_at')->nullable();
            $table->uuid('request_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign('insight_snapshot_id')->references('id')->on('insight_snapshots')->nullOnDelete();
            $table->unique(['user_id', 'deduplication_key']);
            $table->index(['user_id', 'in_app_enabled', 'read_at', 'created_at'], 'user_notifications_inbox_index');
            $table->index(['user_id', 'type', 'created_at'], 'user_notifications_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
