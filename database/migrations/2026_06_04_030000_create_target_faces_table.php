<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_faces', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->string('image_path', 255)->nullable();
            $table->json('scoring_rules'); // list of possible score values, labels, color hexes, and X/M flags
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_faces');
    }
};
