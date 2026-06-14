<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group practice (Latihan Bersama) foundation — "while the hand is in the
 * ALTER engine, lay the pipes for later floors too" (Sprint 01 / §3.2).
 *
 * - user_id becomes NULLABLE so a participant row can represent a GUEST
 *   (player without an account). Guest rows are excluded from stats/PB
 *   because every stats query filters on user_id (see §3.2 warning).
 * - guest_name / added_by_user_id support the host quick-add flow (Phase 0).
 * - participation_status distinguishes self / host_added / invited (Phase 1/2).
 * - target_butt / target_letter map a participant to a bantalan, the unit of
 *   parallel work (Phase 3). Mirrors event_registrations target assignment.
 *
 * Per-participant distance/target-face are NOT new columns: scoring_sessions
 * already has distance_m + target_face_cm + target_face_id, and since each
 * participant IS a scoring_sessions row, those existing columns already serve
 * as per-participant values (confirmed deviation from the literal doc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            // 1.1 — guest support: a participant row may have no owner yet.
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_name', 100)->nullable()->after('user_id');
            $table->foreignId('added_by_user_id')->nullable()->after('guest_name')
                ->constrained('users')->nullOnDelete();

            // 1.4 — how the participant joined (used Phase 1/2; prepared now).
            $table->string('participation_status', 20)->nullable()->after('scoring_session_group_id');

            // 1.2 — bantalan mapping per participant (used Phase 3; prepared now).
            $table->unsignedInteger('target_butt')->nullable()->after('target_face_id');
            $table->char('target_letter', 1)->nullable()->after('target_butt');

            // Roster lookups: participants of a group, with/without an account.
            $table->index(['scoring_session_group_id', 'user_id'], 'scoring_sessions_group_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('scoring_sessions', function (Blueprint $table): void {
            $table->dropIndex('scoring_sessions_group_user_index');
            $table->dropColumn(['target_letter', 'target_butt', 'participation_status', 'guest_name']);
            $table->dropConstrainedForeignId('added_by_user_id');

            // Restore the original NOT NULL ownership invariant.
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
