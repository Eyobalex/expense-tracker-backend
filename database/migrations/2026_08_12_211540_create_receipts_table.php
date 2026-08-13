<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('original_object_key', 512)->unique();
            $table->string('original_filename', 255)->nullable();
            $table->string('mime_type', 128);
            $table->string('extension', 12);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum_sha256', 64);
            $table->string('request_id', 36)->nullable();
            $table->uuid('processing_derivative_id')->nullable();
            $table->uuid('review_transaction_id')->nullable();
            $table->timestampTz('uploaded_at');
            $table->timestampTz('processing_started_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('abandoned_at')->nullable();
            $table->timestampTz('deleted_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['user_id', 'checksum_sha256']);
        });

        DB::statement("ALTER TABLE receipts ADD CONSTRAINT receipts_status_valid CHECK (status IN ('uploaded', 'processing', 'needs_review', 'failed', 'abandoned', 'deleted'))");
        DB::statement('ALTER TABLE receipts ADD CONSTRAINT receipts_safe_image_size CHECK (byte_size > 0 AND width > 0 AND height > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
