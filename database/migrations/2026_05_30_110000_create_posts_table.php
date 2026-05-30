<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ManahPro Module 5 (CONNECT) — community feed posts. `shared_type`/`shared_id`
 * are polymorphic (no FK) for sharing a scorecard/event/product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('visibility', 20)->default('public'); // PostVisibility
            $table->string('shared_type', 40)->nullable(); // scoring_session|event|product
            $table->ulid('shared_id')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('comment_count')->default(0);
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'created_at']);
            $table->index(['author_id', 'created_at']);
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
