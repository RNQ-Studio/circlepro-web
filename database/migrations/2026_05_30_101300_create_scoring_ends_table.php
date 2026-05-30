<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_ends', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('scoring_session_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('end_number');
            $table->smallInteger('end_total')->default(0);
            $table->timestamps();

            $table->unique(['scoring_session_id', 'end_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_ends');
    }
};
