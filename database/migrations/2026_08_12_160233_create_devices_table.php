<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_device_id');
            $table->string('platform', 32);
            $table->string('app_version', 64)->nullable();
            $table->foreignId('personal_access_token_id')->nullable()->index();
            $table->timestampTz('last_seen_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_device_id']);
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
