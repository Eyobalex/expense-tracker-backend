<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_name', 180);
            $table->string('normalized_search_key', 200);
            $table->string('unit_code', 32)->nullable();
            $table->decimal('pack_size_value', 20, 6)->nullable();
            $table->string('pack_size_unit', 32)->nullable();
            $table->uuid('merged_into_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('normalization_version', 64);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'normalized_search_key']);
            $table->index(['user_id', 'is_active', 'canonical_name']);
        });

        Schema::table('items', function (Blueprint $table): void {
            $table->foreign('merged_into_id')->references('id')->on('items')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_pack_size_shape CHECK ((pack_size_value IS NULL AND pack_size_unit IS NULL) OR (pack_size_value > 0 AND pack_size_unit IS NOT NULL))');
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_not_merged_into_self CHECK (merged_into_id IS NULL OR merged_into_id <> id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
