<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_divisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')
                ->constrained('events')->cascadeOnDelete();
            $table->string('bow_class', 30);          // BowClass
            $table->string('gender', 15);             // Gender
            $table->string('age_group', 20);          // AgeGroup
            $table->string('distance_category', 10);  // DistanceCategory
            $table->smallInteger('distance_m');
            $table->smallInteger('num_arrows');
            $table->integer('max_score');
            $table->bigInteger('entry_fee')->default(0); // IDR
            $table->integer('capacity')->nullable();
            $table->integer('num_participants')->default(0);
            $table->decimal('sof_avg_rating', 8, 2)->nullable();
            $table->string('rating_status', 20)->default('unrated');
            $table->timestamp('rated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['event_id', 'bow_class', 'gender', 'age_group', 'distance_category'],
                'event_division_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_divisions');
    }
};
