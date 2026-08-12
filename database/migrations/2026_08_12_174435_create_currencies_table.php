<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->string('code', 3)->primary();
            $table->string('display_name', 120);
            $table->string('symbol', 16)->nullable();
            $table->unsignedTinyInteger('minor_unit_exponent');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE currencies ADD CONSTRAINT currencies_minor_unit_exponent_range CHECK (minor_unit_exponent BETWEEN 0 AND 9)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_base_currency_code_foreign FOREIGN KEY (base_currency_code) REFERENCES currencies (code)');
        DB::statement('ALTER TABLE financial_accounts ADD CONSTRAINT financial_accounts_currency_code_foreign FOREIGN KEY (currency_code) REFERENCES currencies (code)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE financial_accounts DROP CONSTRAINT financial_accounts_currency_code_foreign');
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_base_currency_code_foreign');
        Schema::dropIfExists('currencies');
    }
};
