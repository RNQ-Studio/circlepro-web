<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ManahPro Module 0 — tenant anchor. type=platform is the root ManahPro
 * tenant (national/global data). See database-design.md §2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            // Self-referential FK (PERPANI hierarchy) added after creation —
            // a self-reference inside create() fails on PostgreSQL because the
            // PK isn't yet visible to the deferred FK constraint.
            $table->ulid('parent_id')->nullable();
            $table->string('type', 30); // OrganizationType
            $table->string('name', 150);
            $table->string('slug', 160)->unique();
            $table->text('description')->nullable();
            $table->text('logo_url')->nullable();
            $table->text('banner_url')->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('founded_at')->nullable();
            $table->jsonb('settings')->nullable(); // white-label config
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index(['province', 'city']);
            $table->index('parent_id');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
