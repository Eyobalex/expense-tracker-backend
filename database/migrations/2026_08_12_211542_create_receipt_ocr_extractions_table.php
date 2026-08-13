<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_ocr_extractions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('receipt_id');
            $table->uuid('receipt_derivative_id')->nullable();
            $table->unsignedSmallInteger('attempt');
            $table->string('status', 32);
            $table->string('provider', 64);
            $table->string('provider_version', 128);
            $table->string('model_version', 128);
            $table->string('parser_version', 64);
            $table->string('locale', 32)->nullable();
            $table->jsonb('raw_response')->nullable();
            $table->jsonb('normalized_data')->nullable();
            $table->jsonb('confidence')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->string('request_id', 36)->nullable();
            $table->string('job_id', 64)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('receipt_id')->references('id')->on('receipts')->cascadeOnDelete();
            $table->foreign('receipt_derivative_id')->references('id')->on('receipt_derivatives')->nullOnDelete();
            $table->unique(['receipt_id', 'attempt']);
            $table->index(['receipt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_ocr_extractions');
    }
};
