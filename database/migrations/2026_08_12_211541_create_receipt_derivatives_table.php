<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_derivatives', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('receipt_id');
            $table->string('kind', 32);
            $table->string('object_key', 512)->unique();
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('checksum_sha256', 64);
            $table->string('preprocessing_version', 64);
            $table->jsonb('transforms')->nullable();
            $table->timestamps();
            $table->foreign('receipt_id')->references('id')->on('receipts')->cascadeOnDelete();
            $table->unique(['receipt_id', 'kind', 'preprocessing_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_derivatives');
    }
};
