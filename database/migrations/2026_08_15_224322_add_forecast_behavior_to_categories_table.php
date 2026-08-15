<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('forecast_behavior', 16)->default('variable')->after('budget_enabled');
        });

        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_forecast_behavior_valid CHECK (forecast_behavior IN ('fixed', 'periodic', 'variable'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('forecast_behavior');
        });
    }
};
