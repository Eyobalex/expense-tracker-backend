<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 64)->unique();
            $table->jsonb('permissions');
            $table->timestamps();
        });
        Schema::create('staff_role_assignments', function (Blueprint $table): void {
            $table->foreignId('staff_user_id')->constrained('staff_users')->cascadeOnDelete();
            $table->foreignId('staff_role_id')->constrained('staff_roles')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('staff_users')->nullOnDelete();
            $table->timestampTz('valid_from')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->timestamps();
            $table->primary(['staff_user_id', 'staff_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_role_assignments');
        Schema::dropIfExists('staff_roles');
    }
};
