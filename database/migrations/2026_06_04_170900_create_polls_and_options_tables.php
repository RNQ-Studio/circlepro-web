<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('question');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('poll_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->string('option_text');
            $table->timestamps();
        });

        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->foreignUlid('poll_option_id')->constrained('poll_options')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};
