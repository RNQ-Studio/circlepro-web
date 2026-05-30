<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('avatar_url')->nullable();
            $table->text('banner_url')->nullable();
            $table->text('bio')->nullable();
            $table->string('gender', 10)->nullable();      // Gender
            $table->date('birth_date')->nullable();
            $table->string('age_group', 10)->nullable();    // AgeGroup (cached from birth_date)
            $table->string('province', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('primary_bow_class', 30)->nullable(); // BowClass
            $table->foreignUlid('home_club_id')->nullable()
                ->constrained('organizations')->nullOnDelete();
            $table->jsonb('social_links')->nullable();
            $table->string('peak_title', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
