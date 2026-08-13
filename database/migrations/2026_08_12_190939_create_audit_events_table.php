<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_name', 96);
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id');
            $table->jsonb('summary')->nullable();
            $table->uuid('operation_id')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
