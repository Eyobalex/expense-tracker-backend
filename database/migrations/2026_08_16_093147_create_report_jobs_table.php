<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('format', 16);
            $table->jsonb('filters')->default('{}');
            $table->char('base_currency_code', 3);
            $table->string('timezone', 64);
            $table->string('status', 16)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('private_object_key', 500)->nullable();
            $table->string('artifact_filename', 255)->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->bigInteger('byte_size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->string('request_id', 64)->nullable();
            $table->string('job_id', 128)->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        DB::statement("ALTER TABLE report_jobs ADD CONSTRAINT report_jobs_type_valid CHECK (type IN ('transaction', 'account_statement', 'budget', 'expense', 'income', 'merchant', 'item_price', 'multi_currency', 'full_json_data_export', 'full_account_export'))");
        DB::statement("ALTER TABLE report_jobs ADD CONSTRAINT report_jobs_format_valid CHECK (format IN ('csv', 'xlsx', 'pdf', 'json', 'zip'))");
        DB::statement("ALTER TABLE report_jobs ADD CONSTRAINT report_jobs_status_valid CHECK (status IN ('queued', 'processing', 'completed', 'failed', 'expired'))");
        DB::statement('ALTER TABLE report_jobs ADD CONSTRAINT report_jobs_progress_valid CHECK (progress BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('report_jobs');
    }
};
