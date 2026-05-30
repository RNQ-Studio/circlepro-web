<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('post_id')->constrained()->cascadeOnDelete();
            // Self-referential reply parent — plain nullable ULID (no FK) to avoid
            // the PostgreSQL self-reference-in-create limitation; integrity at app layer.
            $table->ulid('parent_id')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->integer('like_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['post_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
