<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core North Star table. Owned by one user. Offline-first: id is a
 * client-generated ULID and client_uuid gives idempotent sync. Aggregates
 * are cached for fast dashboards. See database-design.md §8.
 *
 * event_division_id is a forward reference to Module 2 (not yet built), so it
 * is a plain nullable ULID column without an FK constraint for now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('equipment_profile_id')->nullable()
                ->constrained('equipment_profiles')->nullOnDelete();
            $table->foreignUlid('organization_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->ulid('event_division_id')->nullable(); // Module 2 (no FK yet)
            $table->foreignUlid('scoring_session_group_id')->nullable()
                ->constrained('scoring_session_groups')->nullOnDelete();
            $table->string('title', 120)->nullable();
            $table->string('bow_class', 30);          // BowClass
            $table->string('distance_category', 10);  // DistanceCategory
            $table->smallInteger('distance_m');
            $table->string('environment', 10)->default('outdoor'); // ArcheryEnvironment
            $table->smallInteger('target_face_cm')->nullable();
            $table->smallInteger('num_ends');
            $table->smallInteger('arrows_per_end')->default(6);
            $table->string('status', 20)->default('in_progress'); // ScoringSessionStatus
            // cached aggregates (from scoring_arrows)
            $table->integer('total_score')->default(0);
            $table->integer('max_possible_score')->default(0);
            $table->integer('arrows_shot')->default(0);
            $table->decimal('avg_per_arrow', 5, 2)->nullable();
            $table->smallInteger('x_count')->default(0);
            $table->smallInteger('ten_count')->default(0);
            $table->smallInteger('miss_count')->default(0);
            $table->boolean('is_personal_best')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            // offline-first sync
            $table->uuid('client_uuid')->nullable()->unique();
            $table->string('source', 10)->default('mobile'); // SyncSource
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'bow_class', 'distance_category']);
            $table->index('organization_id');
            $table->index('event_division_id');
            $table->index('scoring_session_group_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_sessions');
    }
};
