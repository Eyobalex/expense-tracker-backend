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
        Schema::create('insight_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('formula_name', 80);
            $table->unsignedSmallInteger('formula_version');
            $table->timestampTz('covered_from_at')->nullable();
            $table->timestampTz('covered_until_at')->nullable();
            $table->string('timezone', 64);
            $table->char('base_currency_code', 3)->nullable();
            $table->jsonb('inputs');
            $table->jsonb('result');
            $table->timestampTz('calculated_at');
            $table->timestamps();

            $table->foreign('base_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['user_id', 'formula_name', 'calculated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('insight_snapshots');
    }
};
