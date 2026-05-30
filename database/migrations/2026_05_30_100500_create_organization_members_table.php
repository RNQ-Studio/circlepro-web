<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot user<->organization with role + club membership. member_code is unique
 * per organization (PostgreSQL allows multiple NULLs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('member_code', 30)->nullable();
            $table->string('role', 20)->default('member'); // MemberRole
            $table->string('status', 20)->default('active'); // pending|active|left|removed
            $table->timestamp('joined_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->unique(['organization_id', 'member_code']);
            $table->index('user_id');
            $table->index(['organization_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_members');
    }
};
