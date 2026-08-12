<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_account_mappings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->uuid('entity_id')->nullable();
            $table->string('mapping_key', 64);
            $table->string('ledger_code', 64);
            $table->timestamps();
            $table->unique(['user_id', 'entity_type', 'entity_id', 'mapping_key'], 'ledger_account_mapping_entity_unique');
            $table->unique(['user_id', 'ledger_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_account_mappings');
    }
};
