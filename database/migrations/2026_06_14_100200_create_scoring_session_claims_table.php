<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Host-approved claim queue (§3.2). A signed-in archer claims a guest
 * participant slot ("Ini Saya"); the host approves or rejects. On approval
 * (Phase 2 / Sprint 13) ownership transfers to the claimant. Guest+group FKs
 * are denormalised so the host inbox can be queried per group without a join.
 *
 * ID strategy follows §6: ManahPro domain rows use ULID; user FKs are bigint
 * (Passport binds users to bigint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_session_claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('scoring_session_id')
                ->constrained('scoring_sessions')->cascadeOnDelete();
            $table->foreignUlid('scoring_session_group_id')
                ->constrained('scoring_session_groups')->cascadeOnDelete();
            $table->foreignId('claimant_user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // ClaimStatus
            $table->text('message')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // One pending/active claim per (slot, claimant): anti double-claim.
            $table->unique(['scoring_session_id', 'claimant_user_id'], 'session_claimant_unique');

            $table->index('scoring_session_group_id'); // host inbox per group
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_session_claims');
    }
};
