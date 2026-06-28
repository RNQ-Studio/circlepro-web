<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 audit: who last wrote the participant score row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->foreignId('last_scored_by_user_id')->nullable()
                ->after('added_by_user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['scoring_session_group_id', 'last_scored_by_user_id'], 'scoring_sessions_group_last_scorer_idx');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->dropIndex('scoring_sessions_group_last_scorer_idx');
            $table->dropConstrainedForeignId('last_scored_by_user_id');
        });
    }
};
