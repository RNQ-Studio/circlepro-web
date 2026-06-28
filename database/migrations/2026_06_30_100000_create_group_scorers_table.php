<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — one scorer per bantalan for Latihan Bersama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_scorers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('scoring_session_group_id')
                ->constrained('scoring_session_groups')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedSmallInteger('target_butt');
            $table->string('assignment_type', 20)->default('assigned');
            $table->timestamps();

            $table->unique(['scoring_session_group_id', 'target_butt'], 'group_scorers_group_butt_unique');
            $table->index(['scoring_session_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_scorers');
    }
};
