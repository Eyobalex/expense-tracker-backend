<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('mfa_secret');
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('session_version')->default(1);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_users');
    }
};
