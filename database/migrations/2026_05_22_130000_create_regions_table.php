<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->enum('type', ['country', 'state', 'city', 'district', 'village']);
            $table->string('code', 20)->nullable();
            $table->string('name');
            $table->string('phone_code', 20)->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index('parent_id');
            $table->index('type');
            $table->index('code');
            $table->index('phone_code');
            $table->index(['type', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
