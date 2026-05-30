<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Social graph: follower → followee (both reference global users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('followee_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['follower_id', 'followee_id']);
            $table->index('followee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
