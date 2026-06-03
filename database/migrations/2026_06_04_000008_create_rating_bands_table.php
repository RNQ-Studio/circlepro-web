<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_bands', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            
            $table->string('title', 50);
            $table->string('badge', 40)->nullable();
            $table->string('color', 20)->nullable();
            
            $table->integer('min_display_rating');
            $table->integer('max_display_rating')->nullable();
            $table->smallInteger('sort_order');
            $table->timestamps();

            $table->index(['organization_id', 'min_display_rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_bands');
    }
};
