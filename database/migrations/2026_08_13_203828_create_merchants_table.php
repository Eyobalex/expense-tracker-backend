<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name', 160);
            $table->string('normalized_search_key', 180);
            $table->string('location', 180)->nullable();
            $table->uuid('merged_into_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('normalization_version', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'normalized_search_key']);
            $table->index(['user_id', 'is_active', 'display_name']);
        });

        Schema::table('merchants', function (Blueprint $table): void {
            $table->foreign('merged_into_id')->references('id')->on('merchants')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE merchants ADD CONSTRAINT merchants_not_merged_into_self CHECK (merged_into_id IS NULL OR merged_into_id <> id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
