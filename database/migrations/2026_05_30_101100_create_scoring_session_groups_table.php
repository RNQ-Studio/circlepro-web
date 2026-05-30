<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared scoring sessions (group practice / friendly). Each participant gets
 * their own scoring_sessions row referencing this group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_session_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->string('title', 120)->nullable();
            $table->string('distance_category', 10); // DistanceCategory
            $table->smallInteger('distance_m');
            $table->string('environment', 10)->default('outdoor'); // ArcheryEnvironment
            $table->smallInteger('target_face_cm')->nullable();
            $table->smallInteger('num_ends');
            $table->smallInteger('arrows_per_end')->default(6);
            $table->string('join_code', 12)->unique();
            $table->string('status', 20)->default('in_progress'); // ScoringSessionStatus
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('host_user_id');
            $table->index('organization_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_session_groups');
    }
};
