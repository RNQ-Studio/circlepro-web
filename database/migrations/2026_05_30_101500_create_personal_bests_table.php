<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal best per (bow_class, distance_category, num_arrows) so formats are
 * comparable (e.g. PB over 72 arrows).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_bests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bow_class', 30);          // BowClass
            $table->string('distance_category', 10);  // DistanceCategory
            $table->smallInteger('num_arrows');
            $table->integer('best_score');
            $table->foreignUlid('scoring_session_id')->nullable()
                ->constrained('scoring_sessions')->nullOnDelete();
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->unique(['user_id', 'bow_class', 'distance_category', 'num_arrows'], 'uq_personal_best_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_bests');
    }
};
