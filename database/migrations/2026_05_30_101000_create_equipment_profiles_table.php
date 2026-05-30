<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ManahPro Module 1 (TRACK) — per-user equipment/bow setups linked to sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('bow_class', 30); // BowClass
            $table->string('bow_model', 100)->nullable();
            $table->decimal('draw_weight_lbs', 5, 1)->nullable();
            $table->string('arrow_spec', 120)->nullable();
            $table->text('tuning_notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_profiles');
    }
};
