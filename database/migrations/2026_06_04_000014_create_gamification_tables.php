<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_stats', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->integer('xp')->default(0);
            $table->integer('level')->default(1);
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->timestamp('last_session_at')->nullable();
            $table->timestamps();
        });

        Schema::create('badges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('description');
            $table->string('icon_code'); // Icon identifier (e.g. 'local_fire_department', 'military_tech')
            $table->string('requirement_type'); // 'sessions', 'level', 'streak'
            $table->integer('requirement_value');
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->timestamp('unlocked_at');
            $table->timestamps();

            $table->unique(['user_id', 'badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('user_stats');
    }
};
