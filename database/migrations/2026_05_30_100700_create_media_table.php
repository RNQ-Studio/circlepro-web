<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 8 — generic polymorphic media gallery (posts/products/events/...).
 * Polymorphic columns intentionally have NO FK (see database-design.md §9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('mediable_type', 40);
            $table->ulid('mediable_id');
            $table->string('type', 10)->default('image'); // MediaType
            $table->text('url');
            $table->text('thumbnail_url')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->smallInteger('position')->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
