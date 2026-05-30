<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ManahPro Module 0 — extend the existing (bigint) users table with the
 * global-identity fields from database-design.dbml. The PK stays bigint
 * (hybrid strategy): Passport is bound to it and new ManahPro domain tables
 * reference users via a bigint FK while using ULID PKs themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 40)->nullable()->unique()->after('id');
            $table->string('full_name', 120)->nullable()->after('name');
            $table->string('system_role', 20)->default('user')->after('is_active');
            $table->timestamp('last_active_at')->nullable()->after('phone_verified_at');
            $table->softDeletes();

            $table->index('system_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['system_role']);
            $table->dropColumn(['username', 'full_name', 'system_role', 'last_active_at', 'deleted_at']);
        });
    }
};
