<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('coach_profile_id')
                ->constrained('coach_profiles')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->integer('rating'); // 1 to 5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['coach_profile_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_reviews');
    }
};
