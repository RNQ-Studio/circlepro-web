<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bio');
            $table->jsonb('specialties');
            $table->string('certification')->nullable();
            $table->integer('experience_years')->default(0);
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->string('whatsapp_number')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->jsonb('availability')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_profiles');
    }
};
