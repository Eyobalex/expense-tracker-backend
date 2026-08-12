<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_history_locks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('scope', 16);
            $table->timestampTz('first_posted_at');
            $table->timestamps();
            $table->unique('financial_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_history_locks');
    }
};
