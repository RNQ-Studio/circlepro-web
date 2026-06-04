<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('bow_class', 30);
            $table->string('gender', 10);
            $table->string('age_group', 10);
            $table->string('distance_category', 10);

            $table->decimal('mu', 8, 4)->default(1500.0000);
            $table->decimal('phi', 8, 4)->default(350.0000);
            $table->decimal('sigma', 8, 6)->default(0.060000);
            $table->decimal('display_rating', 8, 2)->default(800.00);

            $table->string('status', 20)->default('provisional');
            $table->integer('events_count')->default(0);
            $table->decimal('peak_display_rating', 8, 2)->nullable();
            $table->date('last_event_date')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate ratings for same user division scope
            $table->unique(
                ['organization_id', 'user_id', 'bow_class', 'gender', 'age_group', 'distance_category'],
                'uq_rating_scope'
            );

            // Index for leaderboard sorting
            $table->index(
                ['organization_id', 'bow_class', 'gender', 'age_group', 'distance_category', 'display_rating'],
                'idx_leaderboard'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
