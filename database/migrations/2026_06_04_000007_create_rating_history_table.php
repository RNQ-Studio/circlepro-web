<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_history', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('rating_id')->constrained('ratings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('event_division_id')->nullable()->constrained('event_divisions')->nullOnDelete();
            $table->foreignUlid('rating_period_id')->nullable()->constrained('rating_periods')->nullOnDelete();

            $table->decimal('mu_before', 8, 4);
            $table->decimal('mu_after', 8, 4);
            $table->decimal('phi_before', 8, 4);
            $table->decimal('phi_after', 8, 4);
            $table->decimal('sigma_before', 8, 6);
            $table->decimal('sigma_after', 8, 6);
            $table->decimal('display_before', 8, 2);
            $table->decimal('display_after', 8, 2);

            $table->integer('score_achieved')->nullable();
            $table->decimal('nps', 8, 2)->nullable();
            $table->smallInteger('placement')->nullable();
            $table->integer('num_participants')->nullable();
            $table->string('event_tier', 5)->nullable();
            $table->decimal('k_effective', 8, 4)->nullable();
            $table->boolean('is_manual_override')->default(false);

            $table->timestamp('computed_at');
            $table->timestamps();

            $table->index('rating_id');
            $table->index(['user_id', 'computed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_history');
    }
};
